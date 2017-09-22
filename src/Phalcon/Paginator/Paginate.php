<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/19
 * Time: 20:56
 * Desc: -分页Paginate类
 */


namespace Lkk\Phalwoo\Phalcon\Paginator;

use ArrayObject;
use Lkk\Phalwoo\Phalcon\Mvc\Model\Resultset\Async;

class Paginate extends \stdClass {

    public $items;
    public $first = 0;
    public $before = 0;
    public $current = 0;
    public $last = 0;
    public $next = 0;
    public $total_pages = 0;
    public $total_items = 0;
    public $limit = 0;


    public function __construct(int $page = 1, int $limit = 10, int $total = 0, array $items = []) {
        if($page<=0) $page = 1;
        if($limit<=0) $limit = 10;
        $pages = $total ? ceil($total / $limit) : 0;
        if($pages && $page>$pages) $page = $pages;

        $this->first = $total ? 1 : 0;
        $this->before = $page >1 ? ($page-1) : 1;
        $this->current = $page;
        $this->last = $pages ? $pages : 0;
        $this->next = ($page+1<$pages) ? ($page+1) : $pages;
        $this->total_items = $total;
        $this->total_pages = $pages;
        $this->limit = $limit;

        $this->setItems($items);

    }


    public function setItems(array $items=[]) {
        $this->items = empty($items) ? [] : new Async($items, ArrayObject::STD_PROP_LIST);
    }


    public function getItems() {
        return $this->items;
    }


    /**
     * Returns the current page.
     *
     * @return integer
     */
    public function getCurrentPage() {
        return $this->current;
    }

    /**
     * Returns the first page.
     *
     * @return integer
     */
    public function getFirstPage() {
        return $this->first;
    }

    /**
     * Returns the previous page.
     *
     * @return integer
     */
    public function getPreviousPage() {
        return $this->before;
    }

    /**
     * Returns the next page.
     *
     * @return integer
     */
    public function getNextPage() {
        return $this->next;
    }

    /**
     * Returns the last page.
     *
     * @return integer
     */
    public function getLastPage() {
        return $this->last;
    }


    /**
     * Returns the total pages.
     *
     * @return integer
     */
    public function getTotalPage() {
        return $this->total_pages;
    }


    /**
     * Returns the total items
     *
     * @return integer
     */
    public function getCount() {
        return $this->total_items;
    }


}

