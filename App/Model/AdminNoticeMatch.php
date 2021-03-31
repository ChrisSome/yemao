<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class AdminNoticeMatch  extends AbstractModel{

    const STATUS_NORMAL = 1;
    protected $tableName  = "admin_notice_match";

}