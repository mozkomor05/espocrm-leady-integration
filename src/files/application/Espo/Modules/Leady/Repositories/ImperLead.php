<?php

namespace Espo\Modules\Leady\Repositories;

use Espo\ORM\Entity;

    class ImperLead extends \Espo\Core\Repositories\Database
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);
    }
}
