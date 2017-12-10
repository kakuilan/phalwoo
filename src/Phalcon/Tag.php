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
    }




}