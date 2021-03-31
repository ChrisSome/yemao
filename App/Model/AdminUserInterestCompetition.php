<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class AdminUserInterestCompetition extends AbstractModel
{
    const FOOTBALL_TYPE = 1;
    const BASKETBALL_TYPE = 2;
    protected $tableName = 'admin_user_interest_competition';
}