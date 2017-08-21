<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/20
 * Time: 17:26
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Request;

use Phalcon\Http\Request\FileInterface;
use Lkk\Helpers\FileHelper;
use Lkk\Helpers\DirectoryHelper;

class File implements FileInterface {

    public static $errorCode    = -1; //错误代码
    public static $errorInfo    = [ //错误消息
        //系统错误消息
        '0' => '没有错误发生',
        '1' => '上传文件大小超出系统限制', //php.ini中upload_max_filesize
        '2' => '上传文件大小超出网页表单限制', //HTML表单中规定的MAX_FILE_SIZE
        '3' => '文件只有部分被上传',
        '4' => '没有文件被上传',
        '5' => '上传文件大小为0',
        '6' => '找不到临时文件夹',
        '7' => '文件写入失败',

        //自定义错误消息
        '-1' => '未知错误',
        '-2' => '未找到相应的文件域',
        '-3' => '文件大小超出允许范围:',
        '-4' => '文件类型不在允许范围:',
        '-5' => '未指定上传目录',
        '-6' => '创建目录失败',
        '-7' => '目录不可写',
        '-8' => '临时文件不存在',
        '-9' => '存在同名文件,取消上传',
        '-10' => '文件移动失败',
        '-11' => '文件内容可能不安全',
        '99'  => '上传成功',
    ];


    private $file;


    /**
     * 设置上传的文件信息
     * @param array $file
     */
    public function setFile(array $file) {
        $this->file = $file;
    }


    /**
     * 获取错误码
     * @return int
     */
    public function getErrorCode() {
        return $this->file['error'] ?? self::$errorCode;
    }


    /**
     * 获取错误信息
     * @return mixed|string
     */
    public function getErrorMsg() {
        $code = $this->file['error'] ?? self::$errorCode;
        return self::$errorInfo[$code] ?? '未知错误';
    }


    /**
     * Returns the file size of the uploaded file
     *
     * @return int
     */
    public function getSize() {
        return $this->file['size'] ?? 0;
    }


    /**
     * Returns the real name of the uploaded file
     *
     * @return string
     */
    public function getName() {
        return $this->file['name'] ?? 0;
    }


    /**
     * Returns the temporal name of the uploaded file
     *
     * @return string
     */
    public function getTempName() {
        return $this->file['tmp_name'] ?? 0;
    }


    /**
     * Returns the mime type reported by the browser
     * This mime type is not completely secure, use getRealType() instead
     *
     * @return string
     */
    public function getType() {
        return $this->file['type'] ?? 0;
    }


    /**
     * Gets the real mime type of the upload file using finfo
     *
     * @return string
     */
    public function getRealType() {
        return FileHelper::getFileMime($this->file['tmp_name'], true);
    }


    /**
     * Move the temporary file to a destination
     *
     * @param string $destination
     * @return bool
     */
    public function moveTo($destination, $overWrite=false) {
        $res = false;
        if(empty($this->file)) return $res;

        $dir = dirname($destination);
        if(!file_exists($dir)) mkdir($dir, 0766, true);

        if(file_exists($destination)) {
            if(is_dir($destination)) {
                $dir = DirectoryHelper::formatDir($destination);
                $ext = FileHelper::getFileExt($this->file['name']);
                $file = uniqid('', true);
                $destination = $dir . $file .'.'.$ext;
            }elseif(!$overWrite){
                return $res;
            }
        }

        $res = copy($this->file['tmp_name'], $destination);
        return $res;
    }



}