<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/20
 * Time: 23:37
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon;

use Phalcon\Http\Cookie as PhalCookie;
use Phalcon\Http\Cookie\Exception;


class Cookie extends PhalCookie {

    public function send() {
        return $this;
    }

    public function getValue($filters = null, $defaultValue = null) {
        $dependencyInjector = $this->_dependencyInjector;

        if (empty($dependencyInjector)) {
            throw new Exception("A dependency injection object is required to access the 'request,filter,crypt' service");
        }

        if (!$this->_restored) {
            // $this->restore();
        }

        if (!$this->_readed) {
            $request = $dependencyInjector->get('request');

            if ($request->getCookie($this->_name)) {
                $value = $request->getCookie($this->_name);

                if ($this->_useEncryption) {
                    $crypt = $dependencyInjector->get('crypt');
                    $decryptedValue = $crypt->decryptBase64($value);
                } else {
                    $decryptedValue = $value;
                }

                $this->_value = $decryptedValue;

                if ($filters) {
                    $filter = $this->_filter;
                    if (empty($filter)) {
                        $filter = $dependencyInjector->get('filter');
                        $this->_filter = $filter;
                    }

                    return $filter->sanitize($decryptedValue, $filters);
                }

                return $decryptedValue;
            }

            return $defaultValue;
        }

        return $this->_value;
    }


    public function getEncryptValue() {
        $value = $this->_value;

        if ($this->_useEncryption) {

            if (!empty($value)) {
                $crypt = $this->_dependencyInjector->getShared("crypt");

                /**
                 * Encrypt the value also coding it with base64
                 */
                $encryptValue = $crypt->encryptBase64((string)$value);

            } else {
                $encryptValue = $value;
            }

        } else {
            $encryptValue = $value;
        }

        return $encryptValue;
    }

}