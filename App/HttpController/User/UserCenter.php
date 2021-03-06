<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Model\AdminUserFeedBack;
use App\Model\AdminUserFoulCenter;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserPost;
use App\Model\AdminUserSerialPoint;
use App\Model\AdminUserSetting;
use App\Model\ChatHistory;
use App\Model\UserBlock;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\Validate\Validate;

use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;

/**
 * 用户个人中心
 * Class UserCenter
 * @package App\HttpController\User
 */
class UserCenter   extends FrontUserController{

    public $needCheckToken = true;
    public $isCheckSign = false;


    /**
     * 个人中心首页
     * @Api(name="个人中心",path="/api/user/UserCenter",version="3.0")
     * @ApiDescription(value="serverClient for UserCenter")
     * @Method(allow="{GET}")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "user_info": {
    "id": 4,
    "nickname": "Hdhdh",
    "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
    "level": 3,
    "is_offical": 0,
    "point": 1010
    },
    "fans_count": 5,
    "follow_count": 10,
    "fabolus_count": 20,
    "d_value": 490,
    "t_value": 500
    }
    })
     */
    public function UserCenter()
    {

        $uid = $this->auth['id'];

        $user_info = AdminUser::create()->where('id', $uid)->field(['id', 'nickname', 'photo', 'level', 'is_offical', 'point'])->get();
        //我的粉丝数
        $fansCount = count(AppFunc::getUserFans($uid));

        //我的关注数
        $followCount = count(AppFunc::getUserFollowing($uid));

        $fabolus_number = AdminMessage::create()->where('user_id', $uid)->where('type', 2)->where('item_type', [1,2,4], 'in')->where('status', AdminMessage::STATUS_DEL, '<>')->count();
        $data = [
            'user_info' => $user_info,
            'fans_count' => AppFunc::changeToWan($fansCount, ''),
            'follow_count' => AppFunc::changeToWan($followCount, ''),
            'fabolus_count' => AppFunc::changeToWan($fabolus_number, ''),
            'd_value' => AppFunc::getPointsToNextLevel($user_info),
            't_value' => AppFunc::getPointOfLevel((int)$user_info->level)
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);


    }


    /**
     * 收藏夹
     * @Api(name="收藏夹",path="/api/user/userBookMark",version="3.0")
     * @ApiDescription(value="serverClient for userBookMark")
     * @Method(allow="{GET}")
     * @Param(name="mobile",type="key_word",required="",description="关键字")
     * @Param(name="type",type="int",required="",description="类型 1帖子 2资讯")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "list": [
    {
    "id": 397,
    "hit": 0,
    "user_id": 4,
    "title": "还好哈哈哈",
    "cat_id": 3,
    "status": 1,
    "created_at": "2021-01-06 13:13:15",
    "is_refine": 0,
    "respon_number": 0,
    "updated_at": "2021-01-06 13:13:05",
    "fabolus_number": 1,
    "collect_number": 1,
    "content": "空军建军节",
    "is_me": true,
    "cat_name": "足球天地",
    "cat_color": {
    "background": "#ECEFF9",
    "font": "#4B6EE4"
    },
    "imgs": [],
    "user_info": {
    "id": "4",
    "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
    "nickname": "Hdhdh",
    "level": "3",
    "is_offical": "0"
    },
    "is_follow": false,
    "is_collect": true,
    "lasted_resp": "2021-01-06 13:13:15",
    "is_fabolus": true
    }
    ],
    "count": 37
    }
    })
     */
    public function userBookMark()
    {
        $user_id = (int)$this->auth['id'];
        $key_word = trim($this->params['key_word']);
        $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 10;
        $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1;

        if ($type == 1) { //收藏的帖子
            $model = AdminUserOperate::create()->alias('o')->join('admin_user_posts as p', 'o.item_id=p.id and o.author_id=p.user_id', 'inner')
                ->field(['p.*'])->where('o.user_id', $user_id)->where('o.type', 2)->where('item_type', 1)->where('o.is_cancel', 0);
            if ($key_word) {
                $model = $model->where('p.title', '%' . $key_word . '%', 'like');
            }
            $model = $model->getLimit($page, $size, 'o.created_at');
            $postList = $model->all();
            $total = $model->lastQueryResult()->getTotalCount();
            if (!$postList) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
            $formatPost = FrontService::handPosts($postList, $user_id);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $formatPost, 'count' => $total]);
        } else { //收藏的资讯
            $model = AdminUserOperate::create()->alias('o')->join('admin_information as i', 'o.item_id=i.id and o.author_id=i.user_id', 'inner')
                ->field(['i.*', 'o.item_id'])->where('o.user_id', $user_id)->where('o.type', 2)->where('item_type', 3)->where('o.is_cancel', 0);
            if ($key_word) {
                $model = $model->where('i.title', '%' . $key_word . '%', 'like');
            }
            $model = $model->getLimit($page, $size, 'o.created_at');
            $operates = $model->all(null);
            $count = $model->lastQueryResult()->getTotalCount();
            $informationIds = array_column($operates, 'item_id');
            if (!$informationIds) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0]);
            $informationInStr = implode(",", $informationIds);
            $informations = AdminInformation::create()->where('id', $informationIds, 'in')->order("FIND_IN_SET( id, '" . $informationInStr . "')", 'ASC')->all();
            $formatInformation = FrontService::handInformation($informations, $user_id);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $formatInformation, 'count' => $count]);

        }

    }


    /**
     * 草稿箱
     * @Api(name="草稿箱",path="/api/user/drafts",version="3.0")
     * @ApiDescription(value="serverClient for drafts")
     * @Method(allow="{GET}")
     * @Param(name="page",type="int",required="",description="页面")
     * @Param(name="size",type="int",required="",description="密码")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "data": [
    {
    "id": 396,
    "hit": 0,
    "user_id": 4,
    "title": "是很好",
    "cat_id": 3,
    "status": 4,
    "created_at": "2020-12-31 14:39:38",
    "is_refine": 0,
    "respon_number": 0,
    "updated_at": "2020-12-31 14:39:38",
    "fabolus_number": 0,
    "collect_number": 0,
    "content": "hill撸他进",
    "is_me": true,
    "cat_name": "足球天地",
    "cat_color": {
    "background": "#ECEFF9",
    "font": "#4B6EE4"
    },
    "imgs": [],
    "user_info": {
    "id": "4",
    "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
    "nickname": "Hdhdh",
    "level": "3",
    "is_offical": "0"
    },
    "is_follow": false,
    "is_collect": false,
    "lasted_resp": "2020-12-31 14:39:38",
    "is_fabolus": false
    }
    ],
    "count": 1
    }
    })
     */
    public function drafts()
    {

        $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 20;

        $model = AdminUserPost::create()->where('status', AdminUserPost::NEW_STATUS_SAVE)->where('user_id', $this->auth['id'])->getLimit($page, $size);

        $list = $model->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $format = FrontService::handPosts($list, $this->auth['id']);
        $returnData = ['data' => $format, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
    }



    /**
     * 用户编辑资料
     * @Api(name="用户编辑资料",path="/api/user/editUser",version="3.0")
     * @ApiDescription(value="serverClient for editUser")
     * @Method(allow="{POST}")
     * @Param(name="type",type="int",required="",description="类型 1昵称 2头像 3密码 4手机号")
     * @Param(name="nickname",type="string",description="昵称")
     * @Param(name="photo",type="string",description="头像")
     * @Param(name="old_password",type="string",description="旧密码")
     * @Param(name="new_password",type="string",description="新密码")
     * @Param(name="mobile",type="string",description="手机号")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
     */
    public function editUser()
    {

        $params = $this->params;
        $uid = $this->auth['id'];
        $type = (int)$this->params['type'];
        $validate = new Validate();
        $update_data = [];

        if (!$user = AdminUser::create()->where('id', $uid)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        if (isset($params['nickname']) && $type == 1) {
            $isExists = AdminUser::create()->where('nickname', $this->params['nickname'])
                ->where('id', $this->auth['id'], '<>')
                ->count();

            if ($isExists) {
                return $this->writeJson(Status::CODE_USER_DATA_EXIST, Status::$msg[Status::CODE_USER_DATA_EXIST]);
            }
            $validate->addColumn('nickname', '申请昵称')->required()->lengthMax(32)->lengthMin(4);
            $update_data = ['nickname' => $params['nickname']];
        }
        if (isset($params['photo']) && $type == 2) {
            $validate->addColumn('photo', '申请头像')->required()->lengthMax(128);
            $update_data = ['photo' => $params['photo']];

        }

        if (isset($params['old_password']) && $type == 3 && isset($params['new_password'])) {
            $password = $this->params['new_password'];
            $res = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
            if (!$res) {
                return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
            }
            $user = AdminUser::create()->where('id', $this->auth['id'])->get();
            if (!PasswordTool::getInstance()->checkPassword($params['old_password'], $user->password_hash)) {
                return $this->writeJson(Status::CODE_W_FORMAT_PASS, '旧密码输入错误');

            }

            $password_hash = PasswordTool::getInstance()->generatePassword($password);
            $update_data = ['password_hash' => $password_hash];

        }

        if (isset($params['mobile']) && $type == 4) {
            $code = trim($this->params['code']);
            $phoneCode = AdminUserPhonecode::create()->getLastCodeByMobile(trim($this->params['mobile']));
            if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $code) {
                return $this->writeJson(Status::CODE_W_PHONE_CODE, Status::$msg[Status::CODE_W_PHONE_CODE]);
            } else if (AdminUser::create()->where('mobile', $params['mobile'])->get()) {
                return $this->writeJson(Status::CODE_PHONE_EXIST, Status::$msg[Status::CODE_PHONE_EXIST]);
            }else if(!preg_match("/^1[3456789]\d{9}$/", $params['mobile'])) {
                return $this->writeJson(Status::CODE_W_PHONE, Status::$msg[Status::CODE_W_PHONE]);
            }
            $phoneCode->status = AdminUserPhonecode::STATUS_USED;
            $phoneCode->update();
            $update_data = ['mobile' => $params['mobile']];

        }

        if (!isset($update_data)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        if (AdminUser::create()->update($update_data, ['id' => $uid])) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }


    }



    /**
     * 消息中心
     * @Api(name="消息中心",path="/api/user/messageCenter",version="3.0")
     * @ApiDescription(value="serverClient for messageCenter")
     * @Method(allow="{GET}")
     * @Param(name="type",type="int",required="",description="类型 0统计消息 1系统消息 2赞的消息 3评论与回复 4关注消息")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "sys_un_read_count": 1,
    "fabolus_un_read_count": 6,
    "comment_un_read_count": 5,
    "interest_un_read_count": 2,
    "last_sys_message": {
    "id": 591,
    "content": "您发布的帖子【上课的分内事】包含敏感词【sm】，未发送成功，已移交至草稿箱，请检查修改后再提交",
    "created_at": "2021-01-09 20:45:46"
    }
    }
    })
     */
    public function messageCenter()
    {
        $type = !empty($this->params['type']) ? $this->params['type'] : 0;
        $uid = $this->auth['id'];
        $page = isset($this->params['page']) ? $this->params['page'] : 1;
        $size = isset($this->params['size']) ? $this->params['size'] : 10;
        if (!$type) {
            //所有消息
            $allMessage = AdminMessage::create()->field(['id', 'content', 'created_at', 'status', 'type'])
                ->where('status', AdminMessage::STATUS_UNREAD)
                ->where('user_id', $uid)->order('created_at', 'DESC')->all();

            array_walk($allMessage, function($v, $k) use (&$sysMessage, &$fabolusMessage, &$commentMessage, &$interestMessage) {
                if ($v->type == 1) {
                    $sysMessage[] = $v;
                } else if ($v->type == 2) {
                    $fabolusMessage[] = $v;
                } else if ($v->type == 3) {
                    $commentMessage[] = $v;
                } else if ($v->type == 4) {
                    $interestMessage[] = $v;
                }
            });
            $sys_un_read_count = !empty($sysMessage) ? count($sysMessage) : 0;
            $fabolus_un_read_count = !empty($fabolusMessage) ? count($fabolusMessage) : 0;
            $comment_un_read_count = !empty($commentMessage) ? count($commentMessage) : 0;
            $interest_un_read_count = !empty($interestMessage) ? count($interestMessage) : 0;
            //首条通知
            $data = [
                'sys_un_read_count' => $sys_un_read_count,  //系统消息未读数
                'fabolus_un_read_count' => $fabolus_un_read_count,//点赞未读
                'comment_un_read_count' => $comment_un_read_count,//评论回复未读
                'interest_un_read_count' => $interest_un_read_count,//关注未读
                'last_sys_message' => !empty($sysMessage[0]) ? $sysMessage[0] : null
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else if ($type == 1) {
            //系统消息
            $page = $this->params['page'] ?: 1;
            $size = $this->params['size'] ?: 10;

            //我的通知
            $model = AdminMessage::create()->where('status', AdminMessage::STATUS_DEL, '<>')
                ->where('type', 1)
                ->where('user_id', $uid)->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            //系统消息未读
            $format_data = [];
            foreach ($list as $item) {
                $post = AdminUserPost::create()->where('id', $item['item_id'])->get();
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];
                $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'created_at' => $post->created_at] : [];
                $data['content'] = $item['content'];
                $data['title'] = $item['title'];
                $data['status'] = $item['status'];
                $format_data[] = $data;
                unset($data);
            }

            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else if ($type == 2) {
            $model = AdminMessage::create()->where('user_id', $uid)->where('type', 2)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();

            $format_data = [];

            foreach ($list as $item) {
                $data['item_type'] = $item['item_type'];
                $data['created_at'] = $item['created_at'];
                $data['status'] = $item['status'];
                $data['message_id'] = $item['id'];
                $user_info = AdminUser::create()->where('id', $item['did_user_id'])->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->get();
                $data['user_info'] = $user_info ? $user_info : [];

                if ($item['item_type'] == 1) { //赞我的帖子
                    if ($post = AdminUserPost::create()->where('id', $item['item_id'])->get()) {
                        $data['post_info'] = ['id' => $post->id, 'title' => $post->title, 'content' => base64_decode($post->content)];

                    } else {
                        continue;
                    }
                } else if ($item['item_type'] == 2) { //赞帖子回复
                    $post_comment = AdminPostComment::create()->where('id', $item['item_id'])->get();

                    if ($post_comment) {
                        $post = $post_comment->postInfo();
                        $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                        $data['post_info'] = ['id' => $post->id, 'title' => $post->title, 'content' => base64_decode($post->content)];
                    } else {
                        continue;
                    }

                } else if ($item['item_type'] == 4) { //赞资讯回复
                    if ($information_commnet = AdminInformationComment::create()->where('id', $item['item_id'])->get()) {
                        $information = $information_commnet->getInformation();
                        $data['information_comment_info'] = $information_commnet ? ['id' => $information_commnet->id, 'content' => mb_substr(base64_decode($information_commnet->content), 0, 20)] : [];
                        $data['information_info'] = $information ? ['id' => $information->id, 'title' => $information->title, 'content' => mb_substr($information->content, 0, 20)] : [];
                    } else {
                        continue;
                    }

                } else {
                    continue;
                }
                $format_data[] = $data;
                unset($data);
            }


            $formatData = ['data' => $format_data, 'count' => $total];

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);

        } else if ($type == 3) { //评论与回复
            $model = AdminMessage::create()->where('user_id', $uid)->where('type', 3)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();

            $format_data = [];
            foreach ($list as $item) {


                if ($item['item_type'] == 1) {//帖子
                    if (!$post_comment = AdminPostComment::create()->where('id', $item['item_id'])->get()) continue;
                    $post = $post_comment->postInfo();
                    $data['item_type'] = $item['item_type'];
                    $data['user_info'] = $post_comment->uInfo();
                    $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                    $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'content' => base64_decode($post->content)] : [];
                    $data['status'] = $item['status'];

                } else if ($item['item_type'] == 2) { //帖子评论
                    if (!$post_comment = AdminPostComment::create()->where('id', $item['item_id'])->get()) continue;
                    $post = $post_comment->postInfo();
                    $data['item_type'] = $item['item_type'];
                    $data['parent_comment_info'] = $post_comment->getParentContent();
                    $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                    $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'content' => mb_substr(base64_decode($post->content), 0, 30)] : [];
                    $data['user_info'] = $post_comment->uInfo();
                    $data['status'] = $item['status'];

                } else if ($item['item_type'] == 4) { //资讯回复

                    if (!$information_comment = AdminInformationComment::create()->where('id', $item['item_id'])->get()) continue;
                    $information = $information_comment->getInformation();
                    $data['information_comment_info'] = $information_comment ? ['id' => $information_comment->id, 'content' => base64_decode($information_comment->content)] : [];
                    $data['information_info'] = $information ? ['id' => $information->id, 'title' => $information->title, 'content' => mb_substr(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($information->content)), 0, 30)] : [];
                    $data['item_type'] = $item['item_type'];
                    $data['status'] = $item['status'];
                    $data['user_info'] = $information_comment->getUserInfo();
                } else {
                    continue;
                }
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];

                $format_data[] = $data;
                unset($data);
            }
            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else if ($type == 4) {//用户关注我
            $model = AdminMessage::create()->where('user_id', $uid)->where('type', 4)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            $format_data = [];
            foreach ($list as $item) {
                $user = AdminUser::create()->where('id', $item['did_user_id'])->get();
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];
                $data['user_info'] = $user ? ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo] : [];
                $data['status'] = $item['status'];
                $format_data[] = $data;
            }

            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
    }

    /**
     * 读消息
     * @Api(name="读消息",path="/api/user/readMessage",version="3.0")
     * @ApiDescription(value="serverClient for readMessage")
     * @Method(allow="{POST}")
     * @Param(name="type",type="int",required="",description="1读消息 2一键已读")
     * @Param(name="message_id",type="int",description="消息id")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
     */
    public function readMessage()
    {

        $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1;
        if ($type == 1) {
            $message_id = $this->params['message_id'];

            if (!$message = AdminMessage::create()->where('id', $message_id)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            } else {
                $message->status = AdminMessage::STATUS_READ;
                $message->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
            }
        } else if ($type == 2) {
            AdminMessage::create()->update(['status'=>AdminMessage::STATUS_READ], ['user_id' => $this->auth['id']]);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }

    }

    /**
     * 用户注销
     */
    public function userCancel()
    {
        $user = AdminUser::create()->where('id', $this->auth['id'])->get();
        $user->status = AdminUser::STATUS_CANCEL;
        $user->update();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

    }

    /**
     * 用户浏览历史记录，只记录帖子及咨询
     */
    public function userHistory()
    {
        $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1;
        //帖子记录
        $format = null;
        if ($type == 1) {
            if ($informationHistory = Cache::get('user-history-information-' . $this->auth['id'])) {
                $format = json_decode($informationHistory, true);
            }
        } else if ($type == 2) {
            if ($informationHistory = Cache::get('user-history-post-' . $this->auth['id'])) {
                $format = json_decode($informationHistory, true);
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format);

    }


    /**
     * 足球相关配置
     * @Api(name="足球相关配置",path="/api/user/userSetting",version="3.0")
     * @ApiDescription(value="serverClient for userSetting")
     * @Method(allow="{GET｜POST}")
     * @Param(name="type",type="int",required="",description="类型 //1notice 2push 3private")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "only_notice_my_interest": 0,
    "start": 1,
    "goal": 1,
    "over": 1,
    "show_time_axis": 1,
    "yellow_card": 1,
    "red_card": 1
    }
    })
     */
    public function userSetting()
    {
        if (!$type = (int)$this->params['type']) { //1notice 2push 3private
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->request()->getMethod() == 'GET') {
            if (!$setting = AdminUserSetting::create()->where('user_id', $this->auth['id'])->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
            if ($type == 1) {
                $data = json_decode($setting->notice, true);
            } else if ($type == 2) {
                $data = json_decode($setting->push, true);
            } else if ($type == 3) {
                $data = json_decode($setting->private, true);
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            if ($type == 1) {
                $decode = json_decode($this->params['notice'], true);
                if (!isset($decode['start']) || !isset($decode['goal']) || !isset($decode['over']) || !isset($decode['only_notice_my_interest'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'notice';
                $data = $this->params['notice'];//start goal over only_notice_my_interest
            } else if ($type == 2) {
                $decode = json_decode($this->params['push'], true);
                if (!isset($decode['start']) || !isset($decode['goal']) || !isset($decode['over']) || !isset($decode['open_push']) || !isset($decode['information'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'push';
                $data = $this->params['push'];//start goal over
            } else if ($type == 3) {
                $decode = json_decode($this->params['private'], true);
                if (!isset($decode['see_my_post']) || !isset($decode['see_my_post_comment']) || !isset($decode['see_my_information_comment'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

                }
                $column = 'private';
                $data = $this->params['private'];//see_my_post(1所有 2我关注的 3我的粉丝 4仅自己)  see_my_post_comment(1所有 2我关注的 3我的粉丝 4仅自己) see_my_information_comment(1所有 2我关注的 3我的粉丝 4仅自己)
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            AdminUserSetting::create()->update([$column=>$data], ['user_id' => $this->auth['id']]);

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);


        }
    }

    /**
     * 拉黑逻辑
     */
    public function userBlock()
    {
        $actionType = !empty($this->params['action_type']) ? trim($this->params['action_type']) : '';
        if (empty($actionType) || !in_array($actionType, ['add', 'del'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $blockUserId = !empty($this->params['block_user_id']) ? (int)$this->params['block_user_id'] : 0;
        if (!$blockUserId) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        if ($actionType == 'add') {
            if (!$blockUser = UserBlock::create()->where('user_id', (int)$this->auth['id'])->get()) {
                $inserData = ['user_id' => (int)$this->auth['id'], 'block_user_ids' => json_encode([$blockUserId])];
                $res = UserBlock::create($inserData)->save();
            } else {
                $blockUserIds = json_decode($blockUser->block_user_ids, true);
                if (is_array($blockUserIds)) {
                    $blockUserIds[] = $blockUserId;
                    $blockUser->block_user_ids = json_encode($blockUserIds);
                    $res = $blockUser->update();
                } else {
                    return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
                }
            }
        } else {
            if (!$blockUser = UserBlock::create()->where('user_id', (int)$this->auth['id'])->get()) {
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
            } else {
                $blockUserIds = json_decode($blockUser->block_user_ids, true);
                if (!$blockUserIds || !is_array($blockUserIds)) {
                    return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
                } else {
                    foreach ($blockUserIds as $k => $value) {
                        if ($blockUserId == $blockUserId) {
                            unset($blockUserIds[$k]);
                        }
                    }
                    $blockUser->block_user_ids = json_encode($blockUserIds);
                    $res = $blockUser->update();
                }
            }
        }
        if ($res) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
    }

    public function blockList()
    {
        $authId = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;
        $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 20;
        $blockUsers = null;
        $return = ['list' => null, 'count' => 0];
        if ($block = UserBlock::create()->where('user_id', $authId)->get()) {
            $blockUserIds = json_decode($block->block_user_ids, true);
            if ($blockUserIds) {
                $blockUsers = AdminUser::create()->field(['id', 'nickname', 'photo', 'level'])->where('id', $blockUserIds, 'in')->limit(($page - 1) * $size, $size)->withTotalCount();
                $list = $blockUsers->all(null);
                $count = $blockUsers->lastQueryResult()->getTotalCount();
                $return = ['list' => $list, 'count' => $count];
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 篮球相关配置
     * @Api(name="篮球相关配置",path="/api/user/basketballSetting",version="3.0")
     * @ApiDescription(value="serverClient for basketballSetting")
     * @Method(allow="{GET｜POST}")
     * @Param(name="type",type="int",required="",description="类型 //1notice 2push")
     * @ApiSuccess({"code":0,"msg":"ok","data":null})
     */
    public function basketballSetting()
    {
        if (!$type = $this->params['type']) { //1notice 2push
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->request()->getMethod() == 'GET') {
            if (!$setting = AdminUserSetting::create()->where('user_id', (int)$this->auth['id'])->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            }
            if ($type == 1) {
                $data = json_decode($setting->basketball_notice, true);
            } else if ($type == 2) {
                $data = json_decode($setting->basketball_push, true);
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            if ($type == 1) {
                $decode = json_decode($this->params['notice'], true);
                if (!isset($decode['start']) || !isset($decode['over']) || !isset($decode['only_notice_my_interest'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'basketball_notice';
                $data = $this->params['notice'];
            } else if ($type == 2) {
                $decode = json_decode($this->params['basketball_push'], true);
                if (!isset($decode['start']) || !isset($decode['over']) || !isset($decode['open_push'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'basketball_push';
                $data = $this->params['push'];//start goal over
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            }
            AdminUserSetting::create()->update([$column=>$data], ['user_id' => $this->auth['id']]);

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
        }
    }

    /**
     * 用户设置
     * @return bool
     */
    public function userBasketballSetting()
    {
        if (!$type = $this->params['type']) { //1notice 2push
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->request()->getMethod() == 'GET') {
            if (!$setting = AdminUserSetting::create()->where('user_id', $this->auth['id'])->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
            if ($type == 1) {
                $data = json_decode($setting->basketball_notice, true);
            } else if ($type == 2) {
                $data = json_decode($setting->basketball_push, true);
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            if ($type == 1) {
                $decode = json_decode($this->params['basketball_notice'], true);
                if (!isset($decode['start']) || !isset($decode['over']) || !isset($decode['only_notice_my_interest'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'basketball_notice';
                $data = $this->params['basketball_notice'];//start goal over only_notice_my_interest
            } else if ($type == 2) {
                $decode = json_decode($this->params['basketball_push'], true);
                if (!isset($decode['start']) || !isset($decode['over']) || !isset($decode['open_push'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'basketball_push';
                $data = $this->params['basketball_push'];//start goal over
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            AdminUserSetting::create()->update([$column=>$data], ['user_id' => $this->auth['id']]);

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);


        }
    }

    /**
     * 修改密码
     * @return bool
     * @throws \Exception
     */
    public function changePassword()
    {
        if (!isset($this->params['new_pass'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $password = $this->params['new_pass'];
        $res = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
        if (!$res) {
            return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
        }
        $phoneCode = AdminUserPhonecode::create()->getLastCodeByMobile($this->params['mobile']);

        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {

            return $this->writeJson(Status::CODE_W_PHONE_CODE, Status::$msg[Status::CODE_W_PHONE_CODE]);

        }
        $user = AdminUser::create()->where('id', $this->auth['id'])->get();
        $password_hash = PasswordTool::getInstance()->generatePassword($password);

        $user->password_hash = $password_hash;
        if ($user->update()) {

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }

    }

    /**
     * 点赞详情
     * @Api(name="点赞详情",path="/api/user/myFabolusInfo",version="3.0")
     * @ApiDescription(value="serverClient for myFabolusInfo")
     * @Method(allow="{GET}")
     * @Param(name="mobile",type="string",required="",description="手机号")
     * @Param(name="password",type="string",required="",description="密码")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": [
    {
    "created_at": "2021-01-10 14:50:49",
    "item_type": "1",
    "user_id": "72",
    "id": "410",
    "title": "上课的分内事",
    "user_info": {
    "id": 72,
    "nickname": "number",
    "photo": "https://www.gravatar.com/avatar/b1bc248a7ff2b2e95569f56de68615df?s=120&d=identicon"
    }
    }
    ]
    })
     */
    public function myFabolusInfo()
    {

        $uid = $this->auth['id'];
        //帖子
        $posts = AdminUserOperate::create()->func(function ($builder) use($uid){
            $builder->raw('select o.created_at, o.item_type, o.user_id, p.id, p.title from `admin_user_operates` o left join `admin_user_posts` p on o.author_id=p.user_id where o.item_id=p.id and o.type=? and o.item_type=?   and o.author_id=? ',[1, 1, $uid]);
            return true;
        });
        if ($posts) {
            foreach ($posts as $k=>$post) {
                $user = AdminUser::create()->where('id', $post['user_id'])->get();
                $posts[$k]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo];
            }
        }
        //帖子评论
        $post_comments = AdminUserOperate::create()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, p.title from `admin_user_post_comments` c left join `admin_user_posts` p on  c.post_id=p.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 2, $uid]);
            return true;
        });
        if ($post_comments) {
            foreach ($post_comments as $kc=>$comment) {
                $user = AdminUser::create()->where('id', $comment['user_id'])->get();

                $post_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }

        //资讯评论
        $information_comments = AdminUserOperate::create()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, i.title from `admin_information_comments` c left join `admin_information` i on  c.information_id=i.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 4, $uid]);
            return true;
        });

        if ($information_comments) {
            foreach ($information_comments as $ic=>$icomment) {
                $user = AdminUser::create()->where('id', $icomment['user_id'])->get();

                $information_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }
        $result = array_merge($posts, $post_comments, $information_comments);
        $creates_at = array_column($result, 'created_at');
        array_multisort($creates_at, SORT_DESC, $result);



        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }

    public function foulCenter()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $res = AdminUserFoulCenter::create()->field(['id', 'reason', 'info', 'created_at', 'item_type', 'item_id', 'item_punish_type', 'user_punish_type'])
            ->where('user_id', $this->auth['id'])->order('created_at', 'DESC')
            ->limit(($page - 1) * $size, $size)->withTotalCount();
        $list = $res->all(null);
        $total = $res->lastQueryResult()->getTotalCount();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'count' => $total]);


    }

    /**
     * 站务中心
     * @return bool
     */
    public function foulCenterOne()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $operates = AdminUserOperate::create()->where('author_id', $this->auth['id'])->where('type', 3)->where('item_type', [1,2,4,5], 'in')->getLimit($page, $size);
        $list = $operates->all(null);
        $total = $operates->lastQueryResult()->getTotalCount();
        if ($list) {

            foreach ($list as $item) {

                if (!$item['res_item'] && !$item['res_user']) {

                    continue;
                } else {
                    //1帖子 2帖子评论 3资讯 4资讯评论 5直播间发言
                    if ($item['item_type'] == 1) { //帖子
                        if ($post = AdminUserPost::create()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 1;
                            $data['item_id'] = $post->id;
                            $data['post_title'] = $post->title;
                            $data['created_at'] = $item->created_at;

                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;

                        } else {
                            continue;
                        }
                    } else if ($item['item_type'] == 2) {
                        if ($post_comment = AdminPostComment::create()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 2;
                            $data['item_id'] = $post_comment->id;
                            $data['item_content'] = $post_comment->comment;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }

                    } else if ($item['item_type'] == 4) {
                        if ($information_comment = AdminInformationComment::create()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 4;
                            $data['item_id'] = $information_comment->id;
                            $data['item_content'] = $information_comment->content;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }
                    } else if ($item['item_type'] == 5) {
                        if ($chat_message = ChatHistory::create()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 5;
                            $data['item_id'] = $chat_message->id;
                            $data['item_content'] = $chat_message->comment;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }
                    }
                    $data['id'] = $item['id'];

                    if (!isset($data)) {
                        continue;
                    }
                    if ($item['res_item'] == AdminUserOperate::TYPE_RES_ITEM_DELETE || $item['res_user'] == AdminUserOperate::TYPE_RES_USER_FOBIDDEN || $item['res_user'] == AdminUserOperate::TYPE_RES_USER_BAN) {
                        $datas[] = $data;
                    }
                }
            }
            $return_data = ['list' => $datas, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0]);

        }


    }

    /**
     * 违规记录详情
     */
    public function foulItemInfo()
    {
        $id = $this->params['operate_id'];
        if (!$operate = AdminUserFoulCenter::create()->where('id', $id)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $data = [];
        if ($operate->item_type == 1 && $post = AdminUserPost::create()->where('id', $operate->item_id)->field(['id', 'content'])->get()) {
            $data = ['item_id' => $id, 'item_type' => 1, 'content' => base64_decode($post->content), 'title' => $post->title];
        } else if ($operate->item_type == 2 && $post_comment = AdminPostComment::create()->where('id', $id)->field(['id', 'content'])->get()) {
            $data = ['item_id' => $id, 'item_type' => 2, 'content' => $post_comment->content];
        } else if ($operate->item_type == 4 && $information_comment = AdminInformationComment::create()->where('id', $id)->field(['id', 'content'])->get()) {
            $data = ['item_id' => $id, 'item_type' => 3, 'content' => $information_comment->content];
        } else if ($operate->item_type== 5 && $chat_message = ChatHistory::create()->where('id', $id)->get()) {
            $data = ['item_id' => $id, 'item_type' => 5, 'content' => $chat_message->content];
        }
        $data['info'] = $operate->info;
        $data['reason'] = $operate->reason;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 任务列表
     * @Api(name="任务列表",path="/api/user/getAvailableTask",version="3.0")
     * @ApiDescription(value="serverClient for getAvailableTask")
     * @Method(allow="{GET}")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "user_info": {
    "id": 4,
    "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
    "level": 3,
    "is_offical": 0,
    "point": 1010
    },
    "task_list": {
    "1": {
    "id": 1,
    "name": "每日签到",
    "status": 1,
    "times_per_day": 1,
    "icon": "http://test.ymtyadmin.com/image/system/2020/10/a9372031e438e7d4.jpg",
    "points_per_time": 100,
    "done_times": 0
    },
    "2": {
    "id": 2,
    "name": "社区发帖",
    "status": 1,
    "times_per_day": 5,
    "icon": "http://test.ymtyadmin.com/image/system/2020/10/2a78faed402807f5.jpg",
    "points_per_time": 5,
    "done_times": 0
    },
    "3": {
    "id": 3,
    "name": "评论回帖",
    "status": 1,
    "times_per_day": 5,
    "icon": "http://test.ymtyadmin.com/image/system/2020/10/31d1ca095ae6613a.jpg",
    "points_per_time": 5,
    "done_times": 0
    },
    "4": {
    "id": 4,
    "name": "分享好友",
    "status": 1,
    "times_per_day": 5,
    "icon": "http://test.ymtyadmin.com/image/system/2020/10/7775b4a856bcef57.jpg",
    "points_per_time": 10,
    "done_times": 0
    }
    },
    "d_value": 490,
    "t_value": 500,
    "special": {
    "id": 4,
    "name": "完善资料",
    "status": 1,
    "times_per_day": 1,
    "icon": "http://test.ymtyadmin.com/image/system/2020/10/7775b4a856bcef57.jpg",
    "points_per_time": 200
    }
    }
    })
     */
    public function getAvailableTask()
    {
        $user_tasks = AdminUserSerialPoint::USER_TASK;
        foreach ($user_tasks as $k => $task) {
            if ($task['status'] != AdminUserSerialPoint::TASK_STATUS_NORMAL) {
                continue;
            }
            $done_times = AdminUserSerialPoint::create()->where('task_id', $task['id'])->where('created_at', date('Y-m-d'))->where('user_id', $this->auth['id'])->count();
            $user_tasks[$k]['done_times'] = $done_times;
        }


        $user_info = AdminUser::create()->field(['id', 'photo', 'level', 'is_offical', 'level', 'point', 'third_wx_unionid'])->where('id', $this->auth['id'])->get();

        $return = ['user_info' => $user_info, 'task_list' => $user_tasks];
        $return['d_value'] = AppFunc::getPointsToNextLevel($user_info);
        $return ['t_value'] = AppFunc::getPointOfLevel((int)$user_info->level);
        if (empty($user_info->third_wx_unionid)) {
            $special_status = 1; //可用
        } else {
            $special_status = 0; //不可用
        }
        $return['special'] = ['id' => 5, 'name' => '完善资料', 'status' => $special_status, 'times_per_day' => 1, 'icon' =>'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/a83ffbe56572911e.png', 'points_per_time' => 200];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 做任务加积分
     * @Api(name="做任务加积分",path="/api/user/userDoTask",version="3.0")
     * @ApiDescription(value="serverClient for userDoTask")
     * @Method(allow="{POST}")
     * @Param(name="task_id",type="int",required="",description="任务id")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "level": 3,
    "point": 1110,
    "d_value": 390,
    "t_value": 500
    }
    })
     */
    public function userDoTask()
    {
        $task_id = !empty($this->params['task_id']) ? (int)$this->params['task_id'] : 0;
        $user_id = $this->auth['id'];
        if (!in_array($task_id, [1, 4])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $user_task_list = AdminUserSerialPoint::USER_TASK;
        if (!$user_task = $user_task_list[$task_id]) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $user_did_task = AdminUserSerialPoint::create()->where('user_id', $this->auth['id'])->where('task_id', $task_id)->where('created_at', date('Y-m-d'))->count();
        if ($user_task['times_per_day'] <= $user_did_task) {
            return $this->writeJson(Status::CODE_TASK_LIMIT, Status::$msg[Status::CODE_TASK_LIMIT]);

        }
        $intvalModel = AdminUserSerialPoint::create();
        $intvalModel->task_id = $task_id;
        $intvalModel->user_id = $user_id;
        $intvalModel->point = $user_task['points_per_time'];
        $intvalModel->task_name = $user_task['name'];
        $intvalModel->type = 1;
        $intvalModel->created_at = date('Y-m-d');
        $intvalModel->save();

        $user = AdminUser::create()->where('id', $user_id)->get();
        $point = ((int)$user->point + (int)$user_task['points_per_time']);
        $level = AppFunc::getUserLvByPoint($point);
        $user->point = $point;
        $user->level = $level;
        $user->update();
        $user_info = [
            'level' => $level,
            'point' => $point,
            'd_value' => AppFunc::getPointsToNextLevel($user),
            't_value' => AppFunc::getPointOfLevel((int)$user->level)
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user_info);



    }

    /**
     * 积分列表
     * @Api(name="积分列表",path="/api/user/getPointList",version="3.0")
     * @ApiDescription(value="serverClient for getPointList")
     * @Method(allow="{GET}")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
    "code": 0,
    "msg": "ok",
    "data": {
    "list": [
    {
    "id": 164,
    "task_name": "每日签到",
    "type": 1,
    "point": 100,
    "created_at": "2021-01-10"
    }
    ],
    "total": 16
    }
    })
     */
    public function getPointList()
    {
        $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 10;
        $model = AdminUserSerialPoint::create()->where('user_id', $this->auth['id'])
            ->field(['id', 'task_name', 'type', 'point', 'created_at'])
            ->getLimit($page, $size);
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'total' => $total]);

    }

    /**
     * 删除
     * @return bool
     */
    public function delItem()
    {
        if ((!$type = $this->params['type']) || (!$item_id = $this->params['item_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $uid = $this->auth['id'];
        if ($type == 1) {//删除帖子
            if (!$post = AdminUserPost::create()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            } else {
                $post->status = AdminUserPost::NEW_STATUS_DELETED;
                $post->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else if ($type == 2) {//帖子评论
            if (!$post_comment = AdminPostComment::create()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            } else {
                $post_comment->status = AdminPostComment::STATUS_DEL;
                $post_comment->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else if ($type == 3) {
            if (!$information_comment = AdminInformationComment::create()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            } else {
                $information_comment->status = AdminInformationComment::STATUS_DELETE;
                $information_comment->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

    }



    /**
     * 用户反馈
     */
    public function userFeedBack()
    {

        $validator = new Validate();
        $validator->addColumn('content')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if (AppFunc::have_special_char($this->params['content'])) {
            return $this->writeJson(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);

        }
        $data['content'] = addslashes(htmlspecialchars($this->params['content']));
        $data['user_id'] = $this->auth['id'];
        if ($this->params['img']) {
            $data['img'] = $this->params['img'];

        }
        if (AdminUserFeedBack::create($data)->save()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_ERR, '提交失败，请联系客服');

        }

    }






}