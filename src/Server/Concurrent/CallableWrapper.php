<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/3
 * Time: 15:51
 * Desc: -
 */


namespace Lkk\Phalwoo\Server\Concurrent;

class CallableWrapper extends Wrapper {

    public function __invoke() {
        $obj = $this->obj;
        return Promise::all(func_get_args())->then(function($args) use ($obj) {
            return call_user_func_array($obj, $args);
        });
    }

}