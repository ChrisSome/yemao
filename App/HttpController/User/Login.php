<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\lib\Tool;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserSetting;
use App\Task\PhoneTask;
use App\Task\SerialPointTask;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\RedisPool as RedisPool;
use EasySwoole\Validate\Validate;
use App\Utility\Message\Status as Statuses;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;

/**
 * Class Login
 * @package App\HttpController\User
 */
class Login extends FrontUserController
{
    protected $isCheckSign = false;
    public $needCheckToken = false;


    const DEFAULT_PHOTO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/859c3661cbcc2902.jpg';



    /**
     * 用户登录
     * @Api(name="用户登录",path="/api/user/doLogin",version="3.0")
     * @ApiDescription(value="serverClient for UserLogin")
     * @Method(allow="{POST}")
     * @Param(name="mobile",type="string",required="",lengthMin="11",description="手机号")
     * @Param(name="type",type="int",description="登录类型")
     * @Param(name="code",type="string",description="验证码")
     * @Param(name="password",type="string",description="密码")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"id":4,"nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","point":1110,"level":3,"is_offical":0,"mobile":"17343214247","user_setting":{"notice":{"only_notice_my_interest":0,"start":1,"goal":1,"over":1,"show_time_axis":1,"yellow_card":1,"red_card":1},"basketball_notice":{"only_notice_my_interest":0,"start":1,"over":1}},"wx_name":"秋秋","status":1,"front_token":"c622c364d316ba7bfe696a8504498d85","front_time":1610360707}})
     */
    public function userLogin()
    {
        $params = $this->params;
        if (empty($params['type']) || empty($params['mobile'])) return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        if (!$user = AdminUser::create()->where('mobile', trim($params['mobile']))->get()) {
            return $this->writeJson(Statuses::CODE_USER_NOT_EXIST, Statuses::$msg[Statuses::CODE_USER_NOT_EXIST]);
        }
        if (in_array($user->status, [AdminUser::STATUS_BAN, AdminUser::STATUS_CANCEL])) {
            return $this->writeJson(Statuses::CODE_WRONG_STATUS, Statuses::$msg[Statuses::CODE_WRONG_STATUS]);
        }
        if ($params['type'] == 1) { //验证码登陆
            if (empty($params['code']) || !$mobile_code = AdminUserPhonecode::create()->getLastCodeByMobile($this->params['mobile'])) {
                return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);
            }
            if ($mobile_code['code'] != trim($params['code'])) {
                return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);
            }
            $mobile_code->status = AdminUserPhonecode::STATUS_USED;
            $mobile_code->update();
        } else if ($params['type'] == 2) {//密码登陆
            $password = !empty($params['password']) ? trim($params['password']) : '';
            if (!$password || !PasswordTool::getInstance()->checkPassword($password, $user->password_hash)) {
                return $this->writeJson(Statuses::CODE_W_PHONE, Statuses::$msg[Statuses::CODE_W_PHONE]);
            }
        } else {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        }
        //验证码登陆货账号密码登陆验证通过
        if (!empty($params['cid']) && trim($params['cid']) != $user->cid) {
            $user->cid = trim($params['cid']);
            $user->update();
        }
        $time = time();
        $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
        $uid = $user->id;
        RedisPool::invoke('redis', function(Redis $redis) use ($uid, $token) {
            $redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $uid);
        });
        //用户设置
        $userSetting = $user->userSetting();
        $formatUserSetting = [
            'notice' => isset($userSetting->notice) ? json_decode($userSetting->notice, true) : null,
            'basketball_notice' => isset($userSetting->basketball_notice) ? json_decode($userSetting->basketball_notice, true) : null,
            'push' => isset($userSetting->push) ? json_decode($userSetting->push, true) : null,
            'basketball_push' => isset($userSetting->basketball_push) ? json_decode($userSetting->basketball_push, true) : null,
        ];
        $user_info = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'photo' => $user->photo,
            'point' => $user->point,
            'level' => $user->level,
            'is_offical' => $user->is_offical,
            'mobile' => $user->mobile,
            'user_setting' => $formatUserSetting,
            'wx_name' => $user->wx_name,
            'status' => $user->status,
            'front_token' => $token,
            'front_time' => $time

        ];
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $user_info);

    }

    /**
     * 退出登录
     * @Api(name="退出登录",path="/api/user/logout",version="3.0")
     * @ApiDescription(value="serverClient for UserLoginOut")
     * @Method(allow="{POST}")
     * @ApiSuccess({"code":0,"msg":"ok","data":null})
     */
    public function doLogout()
    {

        $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $this->auth['front_token']);
        $key = sprintf(UserModel::USER_TOKEN_KEY, Cache::get($sUserKey));
        RedisPool::invoke('redis', function(Redis $redis) use ($key) {
            $redis->del($key);
        });
        $this->response()->setCookie('front_token', '');
        $this->response()->setCookie('front_id', '');
        $this->response()->setCookie('front_time', '');

        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }



    /**
     * 发送验证码
     * @Api(name="发送验证码",path="/api/user/userSendSmg",version="3.0")
     * @ApiDescription(value="serverClient for sendMsg")
     * @Method(allow="{GET}")
     * @Param(name="mobile",type="string",required="",lengthMin="11",description="手机号")
     * @ApiSuccess({"code":0,"msg":"验证码以发送至尾号0962手机","data":72})
     */
    public function userSendSmg()
    {

        $valitor = new Validate();
        $valitor->addColumn('mobile', '手机号码')->required('手机号不为空')
            ->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');

        if ($valitor->validate($this->params)) {
            $mobile = $this->params['mobile'];
        } else {
            return $this->writeJson(Statuses::CODE_W_PARAM, $valitor->getError()->__toString());
        }
        if (Cache::get('user-send-msg-' . $mobile) >= 10) {
            return $this->writeJson(Statuses::CODE_PHONE_CODE_LIMIT, Statuses::$msg[Statuses::CODE_PHONE_CODE_LIMIT]);
        } else if (Cache::get('user_send_msg_' . $mobile)) {
            return $this->writeJson(Statuses::CODE_PHONE_CODE_LIMIT_TIME, Statuses::$msg[Statuses::CODE_PHONE_CODE_LIMIT_TIME]);

        }

        $code = Tool::getInstance()->generateCode();
        //异步task

        $res = TaskManager::getInstance()->async(new PhoneTask(['code' => $code, 'mobile' => $mobile, 'name' => '短信验证码']));
        if (!$mobileCodeCache = Cache::get('user-send-msg-' . $mobile)) {
            Cache::set('user-send-msg-' . $mobile, 1, 60 * 60 * 24);
        } else {
            Cache::inc('user-send-msg-' . $mobile, 1);
        }
        Cache::set('user_send_msg_' . $mobile, 1, 60);
        return $this->writeJson(Statuses::CODE_OK, '验证码以发送至尾号' . substr($mobile, -4) .'手机', $res);

    }



    /**
     * 绑定微信
     * @Api(name="绑定微信",path="/api/user/thirdLogin",version="3.0")
     * @ApiDescription(value="serverClient for userBindWx")
     * @Method(allow="{POST}")
     * @Param(name="access_token",type="string",required="",description="")
     * @Param(name="open_id",type="string",required="",description="")
     * @ApiSuccess({"code":0,"msg":"验证码以发送至尾号0962手机","data":72})
     */
    public function bindWx()
    {
        $params = $this->params;
        $valitor = new Validate();
        //验证参数
        $valitor->addColumn('access_token')->required('access_token不能为空');
        $valitor->addColumn('open_id')->required('open_id不能为空');
        $uid = $this->request()->getCookieParams('front_id');
        $user = AdminUser::create()->get(['id'=>$uid]);
        if (!$user || !empty($user->third_wx_unionid)) {
            return $this->writeJson(Statuses::CODE_LOGIN_ERR, Statuses::$msg[Statuses::CODE_LOGIN_ERR]);

        }
        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_ERR, $valitor->getError()->__toString());
        }

        //获取三方微信账户信息
        $mThirdWxInfo = AdminUser::create()->getWxUser($params['access_token'], $params['open_id']);
        $aWxInfo = json_decode($mThirdWxInfo, true);
        if (json_last_error()) {
            return $this->writeJson(Statuses::CODE_ERR, 'json parse error');
        }
        if (!empty($aWxInfo['errcode'])) {
            return $this->writeJson(Statuses::CODE_ERR, $aWxInfo['errmsg']);
        } else {
            if (AdminUser::create()->where('third_wx_unionid', base64_encode($aWxInfo['unionid']))->get()) {
                return $this->writeJson(Statuses::CODE_BIND_WX, Statuses::$msg[Statuses::CODE_BIND_WX]);

            }
            $wxInfo = [
                'wx_photo' => $aWxInfo['headimgurl'],
                'wx_name'  => $aWxInfo['nickname'],
                'third_wx_unionid' => base64_encode($aWxInfo['unionid']),
                'photo' => $aWxInfo['headimgurl']
            ];
            $bool = $user->update($wxInfo);
            if (!$bool) {
                return $this->writeJson(Statuses::CODE_BINDING_ERR, Statuses::$msg[Statuses::CODE_BINDING_ERR]);
            } else {
                //绑定完时候加积分
                $data['task_id'] = 'special';
                $data['user_id'] = $this->auth['id'];

                TaskManager::getInstance()->async(new SerialPointTask($data));

                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $wxInfo);

            }


        }

    }

    /**
     * 微信登录
     * @Api(name="微信登录",path="/api/user/wxLogin",version="3.0")
     * @ApiDescription(value="serverClient for wxLogin")
     * @Method(allow="{POST}")
     * @Param(name="access_token",type="string",required="",description="")
     * @Param(name="open_id",type="string",required="",description="")
     * @ApiSuccess({"code":0,"msg":"ok","data":null})
     */
    public function wxLogin()
    {
        $params = $this->params;
        //获取三方微信账户信息
        $mThirdWxInfo = AdminUser::create()->getWxUser($params['access_token'], $params['open_id']);
        $aWxInfo = json_decode($mThirdWxInfo, true);
        if (json_last_error()) {
            return $this->writeJson(Statuses::CODE_ERR, 'json parse error');
        }
        if (!empty($aWxInfo['errcode'])) {
            return $this->writeJson(Statuses::CODE_ERR, $aWxInfo['errmsg']);
        } else {
            $wxInfo = [
                'wx_photo' => $aWxInfo['headimgurl'],
                'wx_name'  => $aWxInfo['nickname'],
                'third_wx_unionid' => base64_encode($aWxInfo['unionid']),
            ];
            if (!$user = AdminUser::create()->where('third_wx_unionid', base64_encode($aWxInfo['unionid']))->get()) {
                return $this->writeJson(Statuses::CODE_UNBIND_WX, Statuses::$msg[Statuses::CODE_UNBIND_WX], $wxInfo);
            } else {
                if ($cid = $this->params['cid']) {
                    $user->cid = $this->params['cid'];
                    $user->device_type = $this->params['device_type'];
                    $user->update();
                }
                $time = time();
                $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
                $uid = $user->id;
                RedisPool::invoke('redis', function(Redis $redis) use ($uid, $token) {
                    $redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $uid);
                });

                $userSetting = $user->userSetting();
                $formatUserSetting = [
                    'notice' => isset($userSetting->notice) ? json_decode($userSetting->notice, true) : null,
                    'basketball_notice' => isset($userSetting->basketball_notice) ? json_decode($userSetting->basketball_notice, true) : null,
                    'push' => isset($userSetting->push) ? json_decode($userSetting->push, true) : null,
                    'basketball_push' => isset($userSetting->basketball_push) ? json_decode($userSetting->basketball_push, true) : null,

                ];
                $time = time();
                $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
                $user_info = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'photo' => $user->photo,
                    'point' => $user->point,
                    'level' => $user->level,
                    'is_offical' => $user->is_offical,
                    'mobile' => $user->mobile,
                    'user_setting' => $formatUserSetting,
                    'wx_name' => $user->wx_name,
                    'status' => $user->status,
                    'front_time' => $time,
                    'front_token' => $token

                ];
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $user_info);

            }


        }
    }

    /**
     * 用户注册
     * @Api(name="用户注册",path="/api/user/logon",version="3.0")
     * @ApiDescription(value="serverClient for logon")
     * @Method(allow="{POST}")
     * @Param(name="nickname",type="string",required="",description="昵称")
     * @Param(name="mobile",type="string",required="",description="手机号")
     * @Param(name="password",type="string",required="",description="密码")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"id":4,"nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","point":1010,"level":3,"is_offical":0,"mobile":"17343214247","user_setting":{"notice":{"only_notice_my_interest":0,"start":1,"goal":1,"over":1,"show_time_axis":1,"yellow_card":1,"red_card":1},"basketball_notice":{"only_notice_my_interest":0,"start":1,"over":1}},"wx_name":"秋秋","status":1,"front_token":"748eadf3c9d24127eabfe63dd5a5cef1","front_time":1610182754}})
     */
    public function logon()
    {


        $validator = new Validate();
        $validator->addColumn('nickname')->required();
        $validator->addColumn('mobile')->required();
        $validator->addColumn('password')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        }


        if ($sensitive = AdminSensitive::create()->where('word', '%' . trim($this->params['nickname']) . '%', 'like')->get()) {
            //敏感词
            return $this->writeJson(Statuses::CODE_ADD_POST_SENSITIVE, sprintf(Statuses::$msg[Statuses::CODE_ADD_POST_SENSITIVE], $sensitive->word));
        } else if (AppFunc::have_special_char($this->params['nickname'])) {
            //是否utf8编码
            return $this->writeJson(Statuses::CODE_UNVALID_CODE, Statuses::$msg[Statuses::CODE_UNVALID_CODE], $sensitive->word);

        } else if (AdminUser::create()->where('nickname', $this->params['nickname'])->get()) {
            //是否重复
            return $this->writeJson(Statuses::CODE_USER_DATA_EXIST, Statuses::$msg[Statuses::CODE_USER_DATA_EXIST]);

        }
        if (AdminUser::create()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_PHONE_EXIST, Statuses::$msg[Statuses::CODE_PHONE_EXIST]);

        }
        $password = $this->params['password'];
        if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        try{
            $ip = $this->request()->getHeaders()['x-real-ip'][0];
            $result = \Ritaswc\ZxIPAddress\IPv4Tool::query($ip);
            if ($result['addr'][0]) {
                $arr = explode('省', $result['addr'][0]);
                $province = $arr[0];
                $city = isset($arr[1]) ? $arr[1] : '';
                list($provinceCode, $cityCode) = AppFunc::getProvinceAndCityCode($province, $city);
            }
            $userData = [
                'nickname' => $this->params['nickname'],
                'password_hash' => $password_hash,
                'mobile' => $this->params['mobile'],
                'photo' => !empty($this->params['wx_photo']) ? $this->params['wx_photo'] : self::DEFAULT_PHOTO,
                'sign_at' => date('Y-m-d H:i:s'),
                'cid' => isset($this->params['cid']) ? $this->params['cid'] : '',
                'wx_photo' => !empty($this->params['wx_photo']) ? $this->params['wx_photo'] : '',
                'wx_name' => !empty($this->params['wx_name']) ? $this->params['wx_name'] : '',
                'third_wx_unionid' => !empty($this->params['third_wx_unionid']) ? $this->params['third_wx_unionid'] : '',
                'city_code' => !empty($cityCode) ? $cityCode : 0,
                'province_code' => !empty($provinceCode) ? $provinceCode : 0,
                'device_type' => $this->params['device_type']
            ];
            $rs = AdminUser::create($userData)->save();
            $time = time();
            $token = md5($rs . Config::getInstance()->getConf('app.token') . $time);
            $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $token);
            RedisPool::invoke('redis', function(Redis $redis) use ($sUserKey, $rs) {
                $redis->set($sUserKey, $rs);
            });
            $logon = true;
            //足球设置
            $notice = System::NOTICE;
            //篮球设置
            $basketball_notice = System::BASKETBALL_NOTICE;
            $push = System::PUSH;
            $basketball_push = System::BASKETBALL_PUSH;
            $private = System::PRIVATE;
            TaskManager::getInstance()->async(function () use($rs, $notice, $push, $private, $basketball_notice, $basketball_push){

                $settingData = [
                    'user_id'    => $rs,
                    'notice' => json_encode($notice),
                    'push' => json_encode($push),
                    'private' => json_encode($private),
                    'basketball_notice' => json_encode($basketball_notice),
                    'basketball_push' => json_encode($basketball_push)
                ];
                AdminUserSetting::create($settingData)->save();
                //写用户关注赛事
                if ($recCompetitionRes = AdminSysSettings::create()->where('sys_key', 'array_competition')->get()) {
                    $userInterestComData = [
                        'competition_ids' => $recCompetitionRes->sys_value,
                        'user_id' => $rs,
                        'type' => 1
                    ];
                    AdminUserInterestCompetition::create($userInterestComData)->save();
                }
                //写篮球关注赛事
                if ($defaultRes = AdminSysSettings::create()->where('sys_key', AdminSysSettings::BASKETBALL_COMPETITION)->get()) {
                    $userInterestBasCom = [
                        'competition_ids' => $defaultRes->sys_value,
                        'user_id' => $rs,
                        'type' => 2
                    ];
                    AdminUserInterestCompetition::create($userInterestBasCom)->save();

                }

            });
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');

        }
        $user = AdminUser::create()->where('id', $rs)->get();
        $userSetting = $user->userSetting();
        $formatUserSetting = [
            'notice' => isset($userSetting->notice) ? json_decode($userSetting->notice, true) : null,
            'basketball_notice' => isset($userSetting->basketball_notice) ? json_decode($userSetting->basketball_notice, true) : null,
            'push' => isset($userSetting->push) ? json_decode($userSetting->push, true) : null,
            'basketball_push' => isset($userSetting->basketball_push) ? json_decode($userSetting->basketball_push, true) : null,

        ];
        $time = time();
        $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
        $user_info = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'photo' => $user->photo,
            'point' => $user->point,
            'level' => $user->level,
            'is_offical' => $user->is_offical,
            'mobile' => $user->mobile,
            'user_setting' => $formatUserSetting,
            'wx_name' => $user->wx_name,
            'front_time' => $time,
            'front_token' => $token
        ];
        if ($logon) return $this->writeJson(Statuses::CODE_OK, 'OK', $user_info);
        return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');


    }

    /**
     * 检查验证码
     * @Api(name="检查验证码",path="/api/user/checkPhoneCode",version="3.0")
     * @ApiDescription(value="serverClient for checkPhoneCode")
     * @Method(allow="{GET}")
     * @Param(name="code",type="string",required="",description="验证码")
     * @Param(name="mobile",type="string",required="",description="手机号")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
     */
    public function checkPhoneCode()
    {
        if (empty($this->params['code']) || empty($this->params['mobile'])) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        } else {
            $phoneCode = AdminUserPhonecode::create()->getLastCodeByMobile($this->params['mobile']);
            if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['code']) {
                return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);
            }
        }
        $phoneCode->status = AdminUserPhonecode::STATUS_USED;
        $phoneCode->update();
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);


    }


    /**
     * 忘记密码
     * @Api(name="忘记密码",path="/api/user/forgetPass",version="3.0")
     * @ApiDescription(value="serverClient for forgetPass")
     * @Method(allow="{POST}")
     * @Param(name="mobile",type="string",required="",description="手机号")
     * @Param(name="password",type="string",required="",description="密码")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
     */
    public function forgetPass()
    {
        if (!$user = AdminUser::create()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_USER_NOT_EXIST, Statuses::$msg[Statuses::CODE_USER_NOT_EXIST]);
        }
        $phoneCode = AdminUserPhonecode::create()->getLastCodeByMobile($this->params['mobile']);
        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {

            return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);

        }

        $password = $this->params['password'];
        if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        $user->password_hash = $password_hash;
        $user->update();
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }

    public function checkUserInfo()
    {
        $type = isset($this->params['type']) ? (int)$this->params['type'] : 0;
        if (!$type || !in_array($type, [1,2])) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        }
        if ($type == 1) {//校验用户名
            if (empty($this->params['nickname'])) {
                return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
            }
            if (AdminUser::create()->where('nickname', trim($this->params['nickname']))->get()) {
                $response = [
                    'code' => Statuses::CODE_RES_EXIST,
                    'msg' => Statuses::$msg[Statuses::CODE_RES_EXIST],
                ];
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $response);
            } else {
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);
            }
        } else if ($type == 2) { //校验手机号
            if (empty($this->params['mobile'])) {
                return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
            }

            if (AdminUser::create()->where('mobile', trim($this->params['mobile']))->get()) {
                $response = [
                    'code' => Statuses::CODE_RES_EXIST,
                    'msg' => Statuses::$msg[Statuses::CODE_RES_EXIST],
                ];
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $response);
            } else {
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

            }
        }
        else {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        }
    }







}