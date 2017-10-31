<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/11/1
 * Time: 0:06
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Mvc;

use Phalcon\DiInterface;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\View\Exception;
use Phalcon\Mvc\ViewInterface;
use Phalcon\Cache\BackendInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\View as PhView;
use Lkk\Phalwoo\Server\SwooleServer;

class View extends PhView {


    /**
     * Executes render process from dispatching data
     *
     *<code>
     * // Shows recent posts view (app/views/posts/recent.phtml)
     * $view->start()->render("posts", "recent")->finish();
     *</code>
     *
     * @param string $controllerName
     * @param string $actionName
     * @param array $params
     */
    public function render(string $controllerName, string $actionName, $params = null) {
        if (!is_string($controllerName) || !is_string($actionName)) {
            throw new Exception('Invalid parameter type.');
        }

        $silence = $mustClean = null;
        $renderLevel = 0;
        $layoutsDir = $layout = $pickView = $layoutName =
        $engines = $renderView = $pickViewAction = $eventsManager =
        $disabledLevels = $templatesBefore = $templatesAfter =
        $templateBefore = $templateAfter = $cache = null;

        $this->_currentRenderLevel = 0;

        /**
         * If the view is disabled we simply update the buffer from any output produced in the controller
         */
        if ($this->_disabled !== false) {
            $this->_content = ob_get_contents();
            return false;
        }

        $this->_controllerName = $controllerName;
        $this->_actionName = $actionName;
        $this->_params = $params;

        /**
         * Check if there is a layouts directory set
         */
        $layoutsDir = $this->_layoutsDir;
        if (!$layoutsDir) {
            $layoutsDir = "layouts/";
        }


        /**
         * Check if the user has defined a custom layout
         */
        $layout = $this->_layout;
        if ($layout) {
            $layoutName = $layout;
        } else {
            $layoutName = $controllerName;
        }

        /**
         * Load the template engines
         */
        $engines = $this->_loadTemplateEngines();

        /**
         * Check if the user has picked a view different than the automatic
         */
        $pickView = $this->_pickView;


        if ($pickView === null) {
            $renderView = $controllerName . "/" . $actionName;
        } else {

            /**
             * The 'picked' view is an array, where the first element is controller and the second the action
             */
            $renderView = $pickView[0];
            if ($layoutName === null) {
                if(isset($pickView[1])) {
                    $pickViewAction = $pickView[1];
                    $layoutName = $pickViewAction;
                }
            }
        }


        /**
         * Start the cache if there is a cache level enabled
         */
        if ($this->_cacheLevel) {
            $cache = $this->getCache();
        } else {
            $cache = null;
        }

        $eventsManager = $this->_eventsManager;

        /**
         * Create a virtual symbol table.
         * Variables are shared across symbol tables in PHP5
         */
        if (PHP_MAJOR_VERSION == 5) {
            create_symbol_table();
        }


        /**
         * Call beforeRender if there is an events manager
         */
        if (is_object($eventsManager)) {
            if ($this->_eventsManager->fire('view:beforeRender', $this) === false) {
                return false;
            }
        }


        /**
         * Get the current content in the buffer maybe some output from the controller?
         */
        $this->_content = ob_get_contents();
        $mustClean = true;
        $silence = true;

        /**
         * Disabled levels allow to avoid an specific level of rendering
         */
        $disableLevel = $this->_disabledLevels;


        /**
         * Render level will tell use when to stop
         */
        $renderLevel = (int) $this->_renderLevel;
        if ($renderLevel) {

            /**
             * Inserts view related to action
             */
            if ($renderLevel >= self::LEVEL_ACTION_VIEW) {
                if (!isset($disabledLevels[self::LEVEL_ACTION_VIEW])) {
                    $this->_currentRenderLevel = self::LEVEL_ACTION_VIEW;
                    $this->_engineRender($engines, $renderView, $silence, $mustClean, $cache);
                }
            }

            /**
             * Inserts templates before layout
             */
            if ($renderLevel >= self::LEVEL_BEFORE_TEMPLATE)  {
                if (!isset($disabledLevels[self::LEVEL_BEFORE_TEMPLATE])) {
                    $this->_currentRenderLevel = self::LEVEL_BEFORE_TEMPLATE;

                    $templatesBefore = $this->_templatesBefore;

                    $silence = false;
                    if (is_array($templatesBefore)) {
                        foreach ($templatesBefore as $templateBefore) {
                            $this->_engineRender($engines, $layoutsDir . $templateBefore, $silence, $mustClean, $cache);
                        }
                    }
                    $silence = true;
                }
            }

            /**
             * Inserts controller layout
             */
            if ($renderLevel >= self::LEVEL_LAYOUT) {
                if (!isset ($disabledLevels[self::LEVEL_LAYOUT])) {
                    $this->_currentRenderLevel = self::LEVEL_LAYOUT;
                    $this->_engineRender($engines, $layoutsDir . $layoutName, $silence, $mustClean, $cache);
                }
            }

            /**
             * Inserts templates after layout
             */
            if ($renderLevel >= self::LEVEL_AFTER_TEMPLATE) {
                if (!isset ($disabledLevels[self::LEVEL_AFTER_TEMPLATE])) {
                    $this->_currentRenderLevel = self::LEVEL_AFTER_TEMPLATE;

                    $templatesAfter = $this->_templatesAfter;

                    $silence = false;
                    if (is_array($templatesAfter)) {
                        foreach ($templatesAfter as $templateAfter) {
                            $this->_engineRender($engines, $layoutsDir . $templateAfter, $silence, $mustClean, $cache);
                        }
                    }
                    $silence = true;
                }
            }

            /**
             * Inserts main view
             */
            if ($renderLevel >= self::LEVEL_MAIN_LAYOUT) {
                if (!isset ($disabledLevels[self::LEVEL_MAIN_LAYOUT])) {
                    $this->_currentRenderLevel = self::LEVEL_MAIN_LAYOUT;
                    $this->_engineRender($engines, $this->_mainView, $silence, $mustClean, $cache);
                }
            }

            $this->_currentRenderLevel = 0;

            /**
             * Store the data in the cache
             */
            if (is_object($cache)) {
                if ($cache->isStarted() && $cache->isFresh()) {
                    $cache->save();
                } else {
                    $cache->stop();
                }
            }
        }


        /**
         * Call afterRender event
         */
        if (is_object($eventsManager)) {
            $eventsManager->fire('view:afterRender', $this);
        }

        return $this;
    }





}