<?php

namespace Espo\Modules\Leady\Controllers;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;

class ImperLead extends \Espo\Core\Controllers\Record
{
    public function actionConvert($params, $data)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }
        $entity = $this->getRecordService()->convert($data->id);

        if (!empty($entity)) {
            return $entity->getValueMap();
        }

        throw new Error();
    }
}
