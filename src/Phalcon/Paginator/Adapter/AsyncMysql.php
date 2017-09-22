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
use Lkk\Phalwoo\Phalcon\Paginator\Paginate;

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

    protected $_errorMessages;

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
        $this->_columns = $this->_builder->getColumns();
        $model = $this->_builder->getFrom();
        $this->_table = $model::getTableName();
        $this->_where = $this->_builder->getWhere();
        $this->_order = $this->_builder->getOrderBy();

        $sqlArr = $this->_builder->getQuery()->getSql();
        $baseSql = preg_replace_callback('/SELECT\s+(.*)\s+FROM\s+(.*)\s+WHERE\s+((?!order\s+by).)*((order\s+by)?.*)/i', function ($matches) {
            if(isset($matches[1])) {
                $matches[0] = str_replace($matches[1], " COUNT(1) AS num ", $matches[0]);
                if(isset($matches[4])) {
                    $matches[0] = str_replace($matches[4], '', $matches[0]);
                }
                return $matches[0];
            }else{
                return false;
            }
        }, $sqlArr['sql']);

        if(empty($baseSql)) {
            $this->setError('parse sql error.');
            return false;
        }

        //统计
        $asyncMysql = SwooleServer::getPoolManager()->get('mysql_master')->pop();
        $couuntRes = yield $asyncMysql->execute($baseSql, true);
        if($couuntRes['code']==0) {
            $this->_count = $couuntRes['data']['num'];
            if($this->_offset >= $this->_count) {
                $items = [];
            }else{
                //查询
                $listSql = $baseSql . " LIMIT {$this->_offset},{$this->_limitRows} ";
                $listRes = yield $asyncMysql->execute($listSql, false);
                if($listRes['code']==0) {
                    $items = $listRes['data'];
                }else{
                    $this->setError('query list sql error.');
                    return false;
                }
            }

            $this->paginateResult = new Paginate($this->_page, $this->_limitRows, $this->_count, $items);
        }else{
            $this->setError('query count sql error.');
            return false;
        }

        return true;
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


    public function setError($msg) {
        $this->_errorMessages = $msg;
    }


    public function getError() {
        return $this->_errorMessages;
    }


}

 