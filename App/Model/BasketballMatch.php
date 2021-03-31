<?php

namespace App\Model;

use EasySwoole\ORM\AbstractModel;

class BasketballMatch extends AbstractModel
{
    protected $tableName = "basketball_match_list";

    public function getCompetition()
    {
        return $this->hasOne(BasketBallCompetition::class, null, 'competition_id', 'competition_id');
    }

    public function getLimit($page, $limit)
    {
        return $this->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
}
