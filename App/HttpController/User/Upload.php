<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\ClassArr;
use App\lib\Utils;
use App\Utility\Message\Status;
use App\lib\OssService;

class Upload extends FrontUserController
{
    public $needCheckToken = false;
    public $isCheckSign = false;
    function index()
    {

        // TODO: Implement index() method.
        $request = $this->request();
        $file = $request->getUploadedFile('file');
        $sUploadType = $request->getRequestParam('type');
        if (!$file) {
            return $this->writeJson(Status::CODE_ERR, '上传图片为空');
        }
        $isImage = getimagesize($file->getTempName());
        if ($isImage) {
            $type = 'image';
        }

        if (empty($type)) {
            return $this->writeJson(400, '上传文件不合法');
        }
        try {
            $classObj = new ClassArr();
            $classStats = $classObj->uploadClassStat();
            $uploadObj = $classObj->initClass($type, $classStats, [$request, $type]);
            $uploadObj->upload_type = $sUploadType;
            $file = $uploadObj->upload();
        } catch (\Exception $e) {
            return $this->writeJson(400, $e->getMessage(), []);
        }
        if (empty($file)) {
            return $this->writeJson(400, "上传失败", []);
        }
        //return $this->writeJson(200, "OK", $data);
        $data = ['code' => Status::CODE_OK, 'data' => [
            'src' => $file,
            'title' => '上传图片'
        ]];
        return $this->writeJson(Status::CODE_ERR, '未知的上传类型', $data);
    }


    public function ossUpload()
    {
        $request = $this->request();
        $file = $request->getUploadedFile('file');
        $tempFile = $file->getTempName();

        $sUploadType = $request->getRequestParam('type');
        if (!$sUploadType || !in_array($sUploadType, ['avatar', 'system', 'option', 'other'])) {
            return $this->writeJson(Status::CODE_ERR, '未知的上传类型');

        }

        $fileName = $file->getClientFileName();

        $extension = pathinfo($fileName)['extension'];

        if (!in_array(strtolower($extension), ['jpg', 'png', 'gif', 'jpeg', 'ico'])) {
            return $this->writeJson(Status::CODE_ERR, '不支持的类型');

        }
        $baseName = Utils::getFileKey($fileName) . '.' .$extension;

        $ossClient = new OssService($sUploadType);
        $res = $ossClient->uploadFile($baseName, $tempFile);

        if ($res['status'] == Status::CODE_OK) {
            $returnData['imgUrl'] = $res['imgUrl'];

            return $this->writeJson(Status::CODE_OK, '', $returnData);

        } else {
            return $this->writeJson(Status::CODE_ERR, $res['msg']);

        }



    }


}