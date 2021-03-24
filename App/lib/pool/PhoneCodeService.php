<?php
namespace App\lib\pool;

use App\lib\Tool;

class PhoneCodeService{

    const STATUS_SUCCESS = "0";   //发送成功


    private $codeUrl = 'http://api.sms1086.com/Api/verifycode.aspx';              //语音地址
    private $username    = '18806918409'; //用户名
    private $password = 'yemao123456';      //密码

    public static $copying = '尊敬的用户您好，本次动态验证码为 %s,10分钟内有效【夜猫体育】';     //短信模板





    /**
     * 发送短信验证码
     * @param $mobile
     * @param $content
     * @return mixed
     */

    public  function sendMess($mobile,$content){

        $params = [
            'username' => iconv('UTF-8','GB2312//IGNORE',$this->username),
            'password' => md5($this->password . date("Y-m-d H:i:s")),
            'mobiles' => $mobile,
            'content' => iconv('UTF-8','GB2312//IGNORE',$content),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return Tool::getInstance()->postApi($this->codeUrl, 'POST', $params);


    }




}
