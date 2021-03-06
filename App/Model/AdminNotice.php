<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class AdminNotice extends AbstractModel
{
    protected $tableName = "admin_notice";

    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}