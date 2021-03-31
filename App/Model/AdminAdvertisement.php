<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class AdminAdvertisement  extends AbstractModel{

    const STATUS_NORMAL = 1;
    protected $tableName  = "admin_advertisement";

}