<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 11:13
 * Desc: -重写Phalcon的Request\File类
 */


namespace Lkk\Phalwoo\Phalcon\Request;

use Phalcon\Http\Request\FileInterface;
use Lkk\Helpers\FileHelper;
use Lkk\Helpers\DirectoryHelper;

class File implements FileInterface {

    /**
     * Name
     *
     * @var null|string
     * @access protected
     */
    protected $_name;

    /**
     * Temp
     *
     * @var null|string
     * @access protected
     */
    protected $_tmp;

    /**
     * Size
     *
     * @var null|int
     * @access protected
     */
    protected $_size;

    /**
     * Type
     *
     * @var null|string
     * @access protected
     */
    protected $_type;

    /**
     * RealType
     *
     * @var null|string
     * @access protected
     */
    protected $_realType;

    /**
     * Error
     *
     * @var null|array
     * @access protected
     */
    protected $_error;

    /**
     * Key form的input名称
     *
     * @var null|string
     * @access protected
     */
    protected $_key;

    /**
     * Extension
     *
     * @var null|string
     * @access protected
     */
    protected $_extension;


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


    public function __construct(array $file, $key = null) {
        $this->_name = $file['name'] ?? null;
        $this->_tmp = $file['tmp_name'] ?? null;
        $this->_size = $file['size'] ?? 0;
        $this->_type = $file['type'] ?? null;
        $this->_error = $file['error'] ?? self::$errorCode;
        $this->_extension = $this->_name ? pathinfo($this->_name, PATHINFO_EXTENSION) : '';

        if ($key) {
            $this->_key = $key;
        }
    }

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
        return $this->_error;
    }


    /**
     * 获取错误信息
     * @return mixed|string
     */
    public function getErrorMsg() {
        return self::$errorInfo[$this->_error] ?? '未知错误';
    }


    /**
     * Returns the file size of the uploaded file
     *
     * @return int
     */
    public function getSize() {
        return $this->_size;
    }


    /**
     * Returns the real name of the uploaded file
     *
     * @return string
     */
    public function getName() {
        return $this->_name;
    }


    /**
     * Returns the temporal name of the uploaded file
     *
     * @return string
     */
    public function getTempName() {
        return $this->_tmp;
    }


    /**
     * Returns the mime type reported by the browser
     * This mime type is not completely secure, use getRealType() instead
     *
     * @return string
     */
    public function getType() {
        return $this->_type;
    }


    /**
     * Gets the real mime type of the upload file using finfo
     *
     * @return string
     */
    public function getRealType() {
        return FileHelper::getFileMime($this->_tmp, true);
    }


    /**
     * Move the temporary file to a destination
     *
     * @param string $destination
     * @return bool
     */
    public function moveTo($destination, $overWrite=false) {
        $res = false;
        if(empty($this->_tmp) || $this->_error!='0') return $res;

        $dir = dirname($destination);
        if(!file_exists($dir)) mkdir($dir, 0766, true);

        if(file_exists($destination)) {
            if(is_dir($destination)) {
                $dir = DirectoryHelper::formatDir($destination);
                $ext = $this->_extension ? ('.'.$this->_extension) : '';
                $file = date('ymdH').uniqid('', true);
                $destination = $dir . $file .$ext;
            }elseif(!$overWrite){
                return $res;
            }
        }

        $res = copy($this->_tmp, $destination);
        return $res;
    }

}