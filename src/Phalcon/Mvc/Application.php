<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/10/6
 * Time: 13:59
 * Desc: -重写Application以支持协程
 */


namespace Lkk\Phalwoo\Phalcon\Mvc;

use Phalcon\Mvc\Application as PhApp;
use Phalcon\DiInterface;
use Phalcon\Mvc\ViewInterface;
use Phalcon\Mvc\RouterInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\DispatcherInterface;
use Phalcon\Mvc\Application\Exception;
use Phalcon\Mvc\Router\RouteInterface;
use Phalcon\Mvc\ModuleDefinitionInterface;
use Lkk\Phalwoo\Server\SwooleServer;
use Phalcon\Mvc\View as PhView;
use Throwable;


class Application extends PhApp {


    public function handle($uri = null) {
        if (!is_string($uri) && !is_null($uri)) {
            throw new Exception('Invalid parameter type.');
        }

        //声明变量
        $dependencyInjector = $eventsManager = $router = $dispatcher = $response = $view =
        $module = $moduleObject = $moduleName = $className = $path =
        $implicitView = $returnedResponse = $controller = $possibleResponse =
        $renderStatus = $matchedRoute = $match = null;

        $dependencyInjector = $this->_dependencyInjector;
        if(!is_object($dependencyInjector)) {
            throw new Exception("A dependency injection object is required to access internal services");
        }

        $eventsManager = $this->_eventsManager;
        if(is_object($eventsManager)) {
            if ($eventsManager->fire("application:boot", $this) === false) {
                return false;
            }
        }

        $router = $dependencyInjector->getShared("router");
        /**
         * Handle the URI pattern (if any)
         */
        $router->handle($uri);

        /**
         * If a 'match' callback was defined in the matched route
         * The whole dispatcher+view behavior can be overridden by the developer
         */
        $matchedRoute = $router->getMatchedRoute();

        // \Phalcon\Mvc\Router\Route
        if(is_object($matchedRoute)) {
            $match = $matchedRoute->getMatch();
            if(!is_null($match)) {
                if($match instanceof \Closure) {
                    $match = \Closure::bind($match, $dependencyInjector);
                }

                /**
                 * Directly call the match callback
                 */
                $possibleResponse = call_user_func_array($match, $router->getParams());

                /**
                 * If the returned value is a string return it as body
                 */
                if(is_string($possibleResponse)) {
                    $response = $dependencyInjector->getShared("response");
                    $response->setContent($possibleResponse);
                    return $response;
                }

                /**
                 * If the returned string is a ResponseInterface use it as response
                 */
                if(is_object($possibleResponse)) {
                    if($possibleResponse instanceof ResponseInterface) {
                        $possibleResponse->sendHeaders();
                        $possibleResponse->sendCookies();
                        return $possibleResponse;
                    }
                }
            }
        }

        /**
         * If the router doesn't return a valid module we use the default module
         */
        $moduleName = $router->getModuleName();
        if (!$moduleName) {
            $moduleName = $this->_defaultModule;
        }

        $moduleObject = null;

        /**
         * Process the module definition
         */
        if($moduleName) {
            if(is_object($eventsManager)) {
                if ($eventsManager->fire("application:beforeStartModule", $this, $moduleName) === false) {
                    return false;
                }
            }

            /**
             * Gets the module definition
             */
            $module = $this->getModule($moduleName);

            /**
             * A module definition must ne an array or an object
             */
            if(!is_array($module) && !is_object($module)) {
                throw new Exception("Invalid module definition");
            }

            /**
             * An array module definition contains a path to a module definition class
             */
            if(is_array($module)) {
                /**
                 * Class name used to load the module definition
                 */
                if(!$className= $module["className"]) {
                    $className = "Module";
                }

                if($path = $module["path"]) {
                    if (!class_exists($className, false)) {
                        if (!file_exists($path)) {
                            throw new Exception("Module definition path '" . $path . "' doesn't exist");
                        }

                        require $path;
                    }
                }

                $moduleObject = $dependencyInjector->get($className);
                /**
                 * 'registerAutoloaders' and 'registerServices' are automatically called
                 */
                $moduleObject->registerAutoloaders($dependencyInjector);
                $moduleObject->registerServices($dependencyInjector);

            }else{
                /**
                 * A module definition object, can be a Closure instance
                 */
                if (!($module instanceof \Closure)) {
                    throw new Exception("Invalid module definition");
                }

                $moduleObject = call_user_func_array($module, [$dependencyInjector]);
            }

            /**
             * Calling afterStartModule event
             */
            if(is_object($eventsManager)) {
                $eventsManager->fire("application:afterStartModule", $this, $moduleObject);
            }

        }

        /**
         * Check whether use implicit views or not
         */
        $implicitView = $this->_implicitView;
        if ($implicitView === true) {
            $view = $dependencyInjector->getShared("view");
        }

        /**
         * We get the parameters from the router and assign them to the dispatcher
         * Assign the values passed from the router
         */
        $dispatcher = $dependencyInjector->getShared("dispatcher");
        $dispatcher->setModuleName($router->getModuleName());
        $dispatcher->setNamespaceName($router->getNamespaceName());
        $dispatcher->setControllerName($router->getControllerName());
        $dispatcher->setActionName($router->getActionName());
        $dispatcher->setParams($router->getParams());

        /**
         * Start the view component (start output buffering)
         */
        if ($implicitView === true) {
            $view->start();
        }

        /**
         * Calling beforeHandleRequest
         */
        if(is_object($eventsManager)) {
            if ($eventsManager->fire("application:beforeHandleRequest", $this, $dispatcher) === false) {
                return false;
            }
        }


        /**
         * The dispatcher must return an object
         */
        $controller = yield $dispatcher->dispatch();
        if(is_object($controller) && $controller instanceof Throwable) {
            return $controller;
        }

        //包含var_dump等debug调试信息
        $debug = SwooleServer::isOpenDebug() ? ob_get_contents() : '';

        /**
         * Get the latest value returned by an action
         */
        $possibleResponse = yield $dispatcher->getReturnedValue();

        $response =$dependencyInjector->getShared("response");
        $isJson = $response->isJson();

        /**
         * Returning false from an action cancels the view
         */
        if((is_bool($possibleResponse) && $possibleResponse===false) || $isJson) {
            if($isJson) {
                $response->setContent($debug . $response->getContent());
            }
        }else{
            /**
             * Returning a string makes use it as the body of the response
             */
            if(is_string($possibleResponse)) {
                $response->setContent($debug . $possibleResponse);
            }else{
                /**
                 * Check if the returned object is already a response
                 */
                $returnedResponse = is_object($possibleResponse) && ($possibleResponse instanceof ResponseInterface);

                /**
                 * Calling afterHandleRequest
                 */
                if(is_object($eventsManager)) {
                    $eventsManager->fire("application:afterHandleRequest", $this, $controller);
                }

                /**
                 * If the dispatcher returns an object we try to render the view in auto-rendering mode
                 */
                if ($returnedResponse === false && $implicitView === true) {
                    if(is_object($controller)) {
                        $renderStatus = true;

                        /**
                         * This allows to make a custom view render
                         */
                        if(is_object($eventsManager)) {
                            $renderStatus = $eventsManager->fire("application:viewRender", $this, $view);
                        }

                        /**
                         * Check if the view process has been treated by the developer
                         */
                        if ($renderStatus !== false) {

                            /**
                             * Automatic render based on the latest controller executed
                             */
                            $view->render(
                                $dispatcher->getControllerName(),
                                $dispatcher->getActionName(),
                                $dispatcher->getParams()
                            );

                        }
                    }
                }


                /**
                 * Finish the view component (stop output buffering)
                 */
                if ($implicitView === true) {
                    $view->finish();
                }


                if ($returnedResponse === true) {

                    /**
                     * We don't need to create a response because there is one already created
                     */
                    $response = $possibleResponse;
                } else {
                    $response = $dependencyInjector->getShared("response");
                    if ($implicitView === true) {

                        /**
                         * The content returned by the view is passed to the response service
                         */
                        $response->setContent($debug . $view->getContent());
                    }
                }

            }

        }

        /**
         * Calling beforeSendResponse
         */
        if (is_object($eventsManager)) {
            $eventsManager->fire("application:beforeSendResponse", $this, $response);
        }

        /**
         * Headers and Cookies are automatically sent
         */
        $response->sendHeaders();
        $response->sendCookies();

        /**
         * Return the response
         */
        return $response;
    }





}