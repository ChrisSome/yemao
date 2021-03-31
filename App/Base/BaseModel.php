<?php

namespace App\Base;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Component\Pool\PoolManager;

abstract class BaseModel extends AbstractModel
{
	protected $db;
//    private static $instance=[];

    static function getInstance(...$args)
    {
        return self::create();
    }







}