<?php

namespace App\Model;

use App\Base\BaseModel;

// 登录日志记录
class AdminLoginLog extends BaseModel
{
    protected $tableName = "admin_login_log";


    public function findAll($page, $limit)
    {
        $data = $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit,  $limit)
            ->all();

        return $data;
    }
}
