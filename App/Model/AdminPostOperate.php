<?php

namespace App\Model;

use App\Base\BaseModel;
use App\HttpController\Admin\User\Comment;
use App\lib\Tool;
use EasySwoole\ORM\AbstractModel;

class AdminPostOperate extends AbstractModel
{
    const ACTION_TYPE_COLLECT = 2; //收藏
    protected $tableName = "admin_post_operates";


    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit);
    }

    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    public function limitData($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit);
    }
    public function postInfo()
    {
        return $this->hasOne(AdminUserPost::class, null, 'post_id', 'id')->field(['title', 'content',  'created_at']);

    }



    //帖子或评论作者信息
    public function userInfo()
    {
        return $this->hasOne(AdminUser::class, null, 'author_id', 'id')->field(['nickname', 'photo']);

    }

    //操作人信息
    public function uInfo()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['nickname', 'photo']);

    }

    public function commentInfo()
    {
        return $this->hasOne(AdminPostComment::class, null, 'comment_id', 'id');

    }
}
