<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/10
 * Time: 17:00
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon;

use Phalcon\Tag as PhTag;
use Phalcon\Tag\Select;
use Phalcon\Tag\Exception;
use Phalcon\Mvc\UrlInterface;
use Phalcon\EscaperInterface;
use Phalcon\Mvc\Url;


class Tag extends PhTag {

    /**
     * Pre-assigned values for components
     */
    protected static $_displayValues;

    /**
     * HTML document title
     */
    protected static $_documentTitle = null;

    protected static $_documentAppendTitle = null;

    protected static $_documentPrependTitle = null;

    protected static $_documentTitleSeparator = null;

    protected static $_documentType = 11;

    /**
     * Framework Dispatcher
     */
    protected static $_dependencyInjector;

    protected static $_urlService = null;

    protected static $_dispatcherService = null;

    protected static $_escaperService = null;

    protected static $_autoEscape = true;


    const HTML32 = 1;

    const HTML401_STRICT = 2;

    const HTML401_TRANSITIONAL = 3;

    const HTML401_FRAMESET = 4;

    const HTML5 = 5;

    const XHTML10_STRICT = 6;

    const XHTML10_TRANSITIONAL = 7;

    const XHTML10_FRAMESET = 8;

    const XHTML11 = 9;

    const XHTML20 = 10;

    const XHTML5 = 11;


    public static function setEscaperService(EscaperInterface $escaper) {
        self::$_escaperService = $escaper;
    }


    public static function setUrlService(UrlInterface $url) {
        self::$_urlService = $url;
        parent::$_urlService = $url;
    }


    public static function getUrlService() {
        $url = self::$_urlService;
        if(!$url || !is_object($url)) {
            $url = self::$_urlService = new Url();
            $url->setBaseUri('/');
            $url->setBasePath('/');
        }

        return $url;
    }


    public static function linkTo($parameters, $text = null, $local = true) {
        $params = [];
        $action = $query = $url = $code = '';

        if (!is_array($parameters)) {
            $params = [$parameters, $text, $local];
        } else {
            $params = $parameters;
        }

        if(isset($params[0])) {
            $action = $params[0];
        }elseif (isset($params["action"])) {
            $action = $params["action"];
            unset($params["action"]);
        }

        if(isset($params[1])) {
            $text = $params[1];
        }elseif (isset($params["text"])) {
            $text = $params["text"];
            unset($params["text"]);
        }

        if(isset($params[2])) {
            $local = $params[2];
        }elseif (isset($params["local"])) {
            $local = $params["local"];
            unset($params["local"]);
        }


        if(isset($params["query"])) {
            $query = $params["query"];
            unset($params["query"]);
        }else{
            $query = null;
        }


        $url = self::getUrlService();
        $params["href"] = $url->get($action, $query, $local);
        $code = self::renderAttributes("<a", $params);
        $code .= ">" . $text . "</a>";

        return $code;
    }


    public static function form($parameters) {
        $params = [];
        $paramsAction = $action = $code = '';

        if(!is_array($parameters)) {
            $params = [$parameters];
        }else{
            $params = $parameters;
        }


        if(isset($params[0])) {
            $paramsAction = $params[0];
        }elseif (isset($params["action"])) {
            $paramsAction = $params["action"];
        }


        if(isset($params["method"])) {
            $params["method"] = "post";
        }

        $action = null;

        if(!empty($paramsAction)) {
            $action = self::getUrlService()->get($paramsAction);
        }

        if(isset($params["parameters"])) {
            $parameters = $params["parameters"];
            $action .= "?" . $parameters;
        }

        if(!empty($action)) {
            $params["action"] = $action;
        }

        $code = self::renderAttributes("<form", $params);
        $code .= ">";

        return $code;
    }



    public static function javascriptInclude($parameters = null, $local = true) {
        $params = [];
        $code = '';

        if(!is_array($parameters)) {
            $params = [$parameters, $local];
        }else{
            $params = $parameters;
        }

        if(isset($params[1])) {
            $local = (bool) $params[1];
        }elseif(isset($params["local"])){
            $local = (bool) $params["local"];
            unset($params["local"]);
        }

        if(!isset($params["type"])) {
            $params["type"] = "text/javascript";
        }

        if(!isset($params["src"])) {
            $params["src"] = $params[0] ?? '';
        }

        if($local === true) {
            $params["src"] = self::getUrlService()->getStatic($params["src"]);
        }

        $code = self::renderAttributes("<script", $params);
        $code .= "></script>" . PHP_EOL;

        return $code;
    }



    public static function image($parameters = null, $local = true) {
        $params = [];
        $code = $src = '';

        if(!is_array($parameters)) {
            $params = [$parameters];
        }else{
            $params = $parameters;
            if(isset($params[1])) {
                $local = (bool) $params[1];
            }
        }

        if(!isset($params["src"])) {
            $params["src"] = $params[0] ?? '';
        }

        if($local) {
            $params["src"] = self::getUrlService()->getStatic($params["src"]);
        }

        $code = self::renderAttributes("<img", $params);

        if(self::$_documentType > self::HTML5) {
            $code .= " />";
        }else{
            $code .= ">";
        }

        return $code;
    }


    public static function stylesheetLink($parameters = null, $local = true) {
        $params = [];
        $code = '';

        if(!is_array($parameters)) {
            $params = [$parameters, $local];
        }else{
            $params = $parameters;
        }

        if(isset($params[1])) {
            $local = (boolean) $params[1];
        }elseif (isset($params["local"])) {
            $local = (boolean) $params["local"];
            unset($params["local"]);
        }

        if(!isset($params["type"])) {
            $params["type"] = "text/css";
        }

        if(!isset($params["href"])) {
            $params["href"] = $params[0] ?? '';
        }

        if($local) {
            $params["href"] = self::getUrlService()->getStatic($params["href"]);
        }

        if(!isset($params["rel"])) {
            $params["rel"] = 'stylesheet';
        }

        $code = self::renderAttributes("<link", $params);

        if(self::$_documentType > self::HTML5) {
            $code .= " />" . PHP_EOL;
        }else{
            $code .= ">" . PHP_EOL;
        }

        return $code;
    }



}