<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/10/9
 * Time: 21:53
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Mvc;

use Exception;
use Throwable;
use Lkk\Phalwoo\Phalcon\Dispatcher as BaseDispatcher;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;
use Phalcon\Mvc\DispatcherInterface;

/**
 * Phalcon\Mvc\Dispatcher
 *
 * Dispatching is the process of taking the request object, extracting the module name,
 * controller name, action name, and optional parameters contained in it, and then
 * instantiating a controller and calling an action of that controller.
 *
 *<code>
 * $di = new \Phalcon\Di();
 *
 * $dispatcher = new \Phalcon\Mvc\Dispatcher();
 *
 * $dispatcher->setDI($di);
 *
 * $dispatcher->setControllerName("posts");
 * $dispatcher->setActionName("index");
 * $dispatcher->setParams([]);
 *
 * $controller = $dispatcher->dispatch();
 *</code>
 */
class Dispatcher extends BaseDispatcher implements DispatcherInterface {

    protected $_handlerSuffix = "Controller";

    protected $_defaultHandler = "index";

    protected $_defaultAction = "index";


    /**
     * Sets the default controller suffix
     * @param string $controllerSuffix
     */
    public function setControllerSuffix($controllerSuffix) {
        $this->_handlerSuffix = $controllerSuffix;
    }


    /**
     * Sets the default controller name
     * @param string $controllerName
     */
    public function setDefaultController($controllerName) {
        $this->_defaultHandler = $controllerName;
    }


    /**
     * Sets the controller name to be dispatched
     * @param string $controllerName
     */
    public function setControllerName($controllerName) {
        $this->_handlerName = $controllerName;
    }


    /**
     * Gets last dispatched controller name
     * @return null|string
     */
    public function getControllerName() {
        return $this->_handlerName;
    }


    /**
     * Gets previous dispatched namespace name
     * @return null|string
     */
    public function getPreviousNamespaceName() {
        return $this->_previousNamespaceName;
    }


    /**
     * Gets previous dispatched controller name
     * @return null|string
     */
    public function getPreviousControllerName() {
        return $this->_previousHandlerName;
    }


    /**
     * Gets previous dispatched action name
     * @return null|string
     */
    public function getPreviousActionName() {
        return $this->_previousActionName;
    }


    /**
     * Throws an internal exception
     * @param string $message
     * @param int $exceptionCode
     * @return bool
     * @throws Exception
     */
    protected function _throwDispatchException($message, $exceptionCode = 0) {
        $dependencyInjector = $response = $exception = null;

        $dependencyInjector = $this->_dependencyInjector;
        if(!is_object($dependencyInjector)) {
            $exception = new Exception(
                "A dependency injection container is required to access the 'response' service",
                BaseDispatcher::EXCEPTION_NO_DI
            );

            return $this->displayException($exception);
        }

        $response = $dependencyInjector->getShared("response");

        /**
         * Dispatcher exceptions automatically sends a 404 status
         */
        $response->setStatusCode(404, "Not Found");

        /**
         * Create the real exception
         */
        $exception = new Exception($message, $exceptionCode);

        if($this->_handleException($exception) === false) {
            //return false;
        }

        /**
         * Throw the exception if it wasn't handled
         */
        //throw $exception;
        return $this->displayException($exception);
    }


    /**
     * Handles a user exception
     * @param Throwable $exception
     * @return bool
     */
    protected function _handleException(Throwable $exception) {
        $eventsManager = $this->_eventsManager;
        if(is_object($eventsManager)) {
            if($eventsManager->fire("dispatch:beforeException", $this, $exception) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Forwards the execution flow to another controller/action.
     *
     * <code>
     * use Phalcon\Events\Event;
     * use Phalcon\Mvc\Dispatcher;
     * use App\Backend\Bootstrap as Backend;
     * use App\Frontend\Bootstrap as Frontend;
     *
     * // Registering modules
     * $modules = [
     *     "frontend" => [
     *         "className" => Frontend::class,
     *         "path"      => __DIR__ . "/app/Modules/Frontend/Bootstrap.php",
     *         "metadata"  => [
     *             "controllersNamespace" => "App\Frontend\Controllers",
     *         ],
     *     ],
     *     "backend" => [
     *         "className" => Backend::class,
     *         "path"      => __DIR__ . "/app/Modules/Backend/Bootstrap.php",
     *         "metadata"  => [
     *             "controllersNamespace" => "App\Backend\Controllers",
     *         ],
     *     ],
     * ];
     *
     * $application->registerModules($modules);
     *
     * // Setting beforeForward listener
     * $eventsManager  = $di->getShared("eventsManager");
     *
     * $eventsManager->attach(
     *     "dispatch:beforeForward",
     *     function(Event $event, Dispatcher $dispatcher, array $forward) use ($modules) {
     *         $metadata = $modules[$forward["module"]]["metadata"];
     *
     *         $dispatcher->setModuleName($forward["module"]);
     *         $dispatcher->setNamespaceName($metadata["controllersNamespace"]);
     *     }
     * );
     *
     * // Forward
     * $this->dispatcher->forward(
     *     [
     *         "module"     => "backend",
     *         "controller" => "posts",
     *         "action"     => "index",
     *     ]
     * );
     * </code>
     *
     * @param array $forward
     */
    public function forward($forward) {
        $eventsManager = $this->_eventsManager;
        if(is_object($eventsManager)) {
            $eventsManager->fire("dispatch:beforeForward", $this, $forward);
        }

        parent::forward($forward);
    }


    /**
     * Possible controller class name that will be located to dispatch the request
     * @return null|string
     */
    public function getControllerClass() {
        return $this->getHandlerClass();
    }


    /**
     * Returns the latest dispatched controller
     * @return mixed
     */
    public function getLastController() {
        return $this->_lastHandler;
    }


    /**
     * Returns the active controller in the dispatcher
     * @return mixed
     */
    public function getActiveController() {
        return $this->_activeHandler;
    }


}