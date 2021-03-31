<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class BasketballTeam extends AbstractModel
{
    protected $tableName = "basketball_team";

    public function competitionInfo()
    {
        if ($competition = $this->hasOne(BasketBallCompetition::class, null, 'competition_id', 'competition_id')) {
            return $competition;
        } else {
            return [];
        }

    }
}
