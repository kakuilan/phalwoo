<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/19
 * Time: 17:52
 * Desc: -
 */
 

namespace Lkk\Phalwoo\Phalcon\Paginator\Adapter;

use Phalcon\Paginator\Adapter;
use Phalcon\Mvc\Model\Query\Builder;
use Lkk\Phalwoo\Server\SwooleServer;


class AsyncMysql extends Adapter {

    /**
     * Configuration of paginator by model
     */
    protected $_config;

    /**
     * Paginator's data
     */
    protected $_builder;


    protected $_count;

    //SQL查询相关
    protected $_columns;
    protected $_table;
    protected $_where;
    protected $_order;
    protected $_offset;


    /**
     * Phalcon's paginate result.
     *
     * @var \stdClass
     */
    protected $paginateResult = null;

    /**
     * Phalcon\Paginator\Adapter\QueryBuilder
     *
     * @param array $config
     */
    public function __construct(array $config) {
        $this->_config = $config;
        $this->_builder = $config['builder'] ?? null; //注意builder中的conditions条件必须为字符串
        $this->_limitRows = $config['limit'] ?? 10;
        $this->_page = $config['page'] ?? 1;

        if($this->_page<=0) $this->_page = 1;
        if($this->_page<=0) $this->_page = 10;

        $this->_offset = ($this->_page-1) * $this->_limitRows;

    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function getCurrentPage() {
        return $this->_page;
    }

    /**
     * Set query builder object
     *
     * @param \Phalcon\Mvc\Model\Query\Builder $builder
     * @return Builder
     */
    public function setQueryBuilder(\Phalcon\Mvc\Model\Query\Builder $builder) {
        $this->_builder = $builder;
        return $this->_builder;
    }

    /**
     * Get query builder object
     *
     * @return \Phalcon\Mvc\Model\Query\Builder
     */
    public function getQueryBuilder() {
        return $this->_builder;
    }

    /**
     * Returns a slice of the resultset to show in the pagination
     *
     * @return \stdClass
     */
    public function getPaginate() {
        if(is_null($this->paginateResult)) {
            $this->runQuery();
        }

        return $this->paginateResult;
    }


    //执行查询
    private function runQuery() {
        //$sql = "SELECT * FROM lkk_action WHERE ac_id>0 ORDER BY ac_id DESC LIMIT 5,4 ";
        $this->_columns = $this->_builder->getColumns();
        $model = $this->_builder->getFrom();
        $this->_table = $model::getTableName();
        $this->_where = $this->_builder->getWhere();
        $this->_order = $this->_builder->getOrderBy();

        $countSql   = "SELECT COUNT(1) AS num FROM {$this->_table} WHERE {$this->_where} ";
        $listSql    = "SELECT {$this->_columns} FROM {$this->_table} WHERE {$this->_where} ";
        if($this->_order) $listSql .= " {$this->_order} ";
        $listSql .= " LIMIT {$this->_offset},{$this->_limitRows} ";

        $sqlArr = $this->_builder->getQuery()->getSql();
        $baseSql = preg_replace_callback('/SELECT (.*) FROM .* WHERE .*/i', function ($matches) {
            if(isset($matches[1])) {
                $matches[0] = str_replace($matches[1], " COUNT(1) AS num ", $matches[0]);
            }
            return $matches[0];
        }, $sqlArr['sql']);

        $asyncMysql = SwooleServer::getPoolManager()->get('mysql_master')->pop();

        //统计
        $couuntRes = yield $asyncMysql->execute($baseSql, true);
        if($couuntRes['code']==0) {
            $this->_count = $couuntRes['data']['num'];

            //查询
        }else{

        }





    }



    /**
     * Get current rows limit (if provided)
     *
     * @return integer|null
     */
    public function getLimit() {
        return $this->_limitRows;
    }


    /**
     * Return true if it's necessary to paginate or false if not.
     *
     * @return boolean
     */
    public function haveToPaginate() {
        $this->getPaginate();
        return !is_null($this->paginateResult) && $this->paginateResult->total_pages > 1;
    }


    /**
     * Returns the first page.
     *
     * @return integer
     */
    public function getFirstPage() {
        return $this->paginateResult->first;
    }


    /**
     * Returns the previous page.
     *
     * @return integer
     */
    public function getPreviousPage() {
        return $this->paginateResult->before;
    }


    /**
     * Returns the next page.
     *
     * @return integer
     */
    public function getNextPage() {
        return $this->paginateResult->next;
    }


    /**
     * Returns the last page.
     *
     * @return integer
     */
    public function getLastPage() {
        return $this->paginateResult->last;
    }


    /**
     * Returns the total pages.
     *
     * @return integer
     */
    public function getTotalPage() {
        return $this->paginateResult->total_pages;
    }

    /**
     * {@inheritdoc}
     *
     * @return integer
     */
    public function getCount() {
        return intval($this->_count);
    }


}

 