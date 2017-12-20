<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/20
 * Time: 11:32
 * Desc: 控制器基类
 */

namespace Lkk\Phalwoo\Phalcon\Mvc;

use Phalcon\Mvc\Controller as PhController;
use Lkk\Phalwoo\Phalcon\Http\Response as PwResponse;
use Lkk\Phalwoo\Phalcon\Mvc\View;
use Lkk\Helpers\ArrayHelper;


class Controller extends PhController {

    protected $isJson = false;
    protected $jsonRes = [
        'status' => false, //状态
        'code' => 200, //状态码
        'data' => [], //数据
        'msg' => '', //提示信息
    ];


    /**
     * 设置Action是否json接口
     * @param bool $status
     */
    public function setJsonStatus($status=false) {
        $this->isJson = boolval($status);

        if($status) {
            //取消视图模板
            if(isset($this->view)) {
                $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
                $this->view->disable();
            }

            if(isset($this->response)) {
                $this->response->setJsonStatus(true);
            }
        }
    }


    /**
     * 检查Action是否json接口
     * @return bool
     */
    public function isJson() {
        return $this->isJson;
    }


    /**
     * 设置json结果
     * @param array $res
     * @param string $callback
     */
    public function setJsonRes($res=[], $callback='') {
        $this->jsonRes = array_merge($this->jsonRes, $res);
    }


    /**
     * 获取json结果
     * @return array
     */
    public function getJsonRes() {
        return $this->jsonRes;
    }






}