<?php

namespace Espo\Modules\Leady\Services;

use Cassandra\Exception\AlreadyExistsException;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\Error;

class ImperLead extends \Espo\Services\Record
{

    const IMPER_TOKEN = "";

    private function leadyAPI(string $method, string $url, string $token, $data = false)
    {
        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        $header = [];
        $header[] = 'Content-type: application/json; charset=utf-8';
        $header[] = 'Authorization: Token ' . $token;


        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);

        return json_decode($result, true);
    }

    public function fetchFromLeadAPI()
    {
        $lastLead = $this->getEntityManager()->getRepository('ImperLead')->order('lastTimestamp', 'DESC')->findOne();

        $response = $this->leadyAPI('GET', 'https://api.leady.com/v1/resources/wpdistro.cz/sessions/',
            self::IMPER_TOKEN, $lastLead === null ? [] : [
                'period_start' => date('Y-m-d H:i:s', strtotime($lastLead->get('lastTimestamp')))
            ]);
        foreach ($response['results'] as $r) {
            if (!empty($r['company'])) {
                $duplicate = $this->getEntityManager()->getRepository('ImperLead')->where([
                    'sessionId' => $r['id']
                ])->findOne();

                if ($duplicate !== null) {
                    $GLOBALS['log']->debug("Found duplicate with ID ${$r['id']}. Skipping.");
                    continue;
                }

                $actionResponse = $this->leadyAPI('GET', $r['action_list_url'], self::IMPER_TOKEN);

//            $GLOBALS['log']->debug('actionList', [$actionResponse]);
//            break;

                try {
                    $this->getEntityManager()->createEntity('ImperLead', [
                        'sessionId' => $r['id'],
                        'companyContacts' => join(',', $r['company']['contact_persons']),
                        'companyId' => $r['company']['id'],
                        'companyName' => $r['company']['name'],
                        'companyTerritory' => $r['company']['territory'],
                        'companyEmail' => $r['company']['email'],
                        'companyWebsite' => $r['company']['website'],
                        'actionList' => array_map(function ($action) {
                            return sprintf('%s - %s sec', $action['location'], strval($action['seconds_spent'] ?: 0));
                        }, $actionResponse['results']),
                        'firstTimestamp' => $r['first_timestamp'],
                        'lastTimestamp' => $r['last_timestamp'],
                        'companyEndpoint' => $r['company']['detail_url'],
                        'referrer' => $r['referrer'],
                        'queryString' => $r['search_engine_query'],
                    ]);
                } catch (\Exception $e) {
                    $GLOBALS['log']->error('Job ImportLeadSessions: Exception while importing session [' . $r['id'] . ']:' . $e->getMessage());
                }
            }
        }
    }

    public function convert($id)
    {
        $imper_lead = $this->getEntity($id);

        if (empty($imper_lead)) {
            throw new NotFound();
        }

        $company_endpoint = $imper_lead->get('companyEndpoint');

        if (empty($company_endpoint)) {
            throw new Error("ImperLead record doesn't contain companyEndpoint.");
        }

        $response = $this->leadyAPI('GET', $company_endpoint, self::IMPER_TOKEN);
        $duplicate = $this->getEntityManager()->getRepository('Lead')->where([
            'imperLeadCompanyId' => $response['id']
        ])->findOne();

        if (!empty($duplicate)) {
            throw new Forbidden("Converted Lead already exists.");
        }

        $data = [
            'imperLeadCompanyId' => $response['id'],
            'accountName' => $response['name'],
            'turnoverDetail' => $response['turnover'],
            'year2019' => $response['turnover_numeric'],
            'employees' => $response['magnitude'],
            'emailAddress' => $response['email'],
            'website' => $response['website'],
            'phoneNumberData' => array_map(function ($num, $i) {
                return (object)[
                    'phoneNumber' => $num,
                    'primary' => $i === 0
                ];
            }, $response['phones'], array_keys($response['phones'])),
            'addressStreet' => $response['address'],
            'addressCity' => $response['city'],
            "addressState" => $response['region'],
            'addressCountry' => $response['global_country'],
            'addressPostalCode' => $response['zipcode'],
        ];

        if (!empty($response['contact_persons'])) {
            $contact_persons = $response['contact_persons'];
            $first_person = explode(' ', $contact_persons[0]);

            $data['lastName'] = array_pop($first_person);
            $data['firstName'] = implode(' ', $first_person);
            $data['description'] = "Contact persons:\n\n" . implode(PHP_EOL, $contact_persons);
        }

        return $this->getEntityManager()->createEntity('Lead', $data);
    }
}
