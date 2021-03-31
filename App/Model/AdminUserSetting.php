<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class AdminUserSetting extends AbstractModel
{

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;
    protected $tableName = "admin_user_setting";


}