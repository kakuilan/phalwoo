<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/10/9
 * Time: 21:53
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Mvc;

use Phalcon\Mvc\Dispatcher as PhDispatcher;
use Phalcon\Mvc\DispatcherInterface;
use Phalcon\Mvc\Dispatcher\Exception;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;
use Phalcon\Dispatcher as BaseDispatcher;

class Dispatcher extends PhDispatcher {


    /**
     * Dispatches a handle action taking into account the routing parameters
     *
     * @return object|bool
     */
    public function dispatch() {
        $handler = $e = null;
        try {
            yield $handler = $this->_dispatch();
        }catch (\Exception $e) {
            if($this->_handleException($e) === false) {
                return false;
            }
            throw $e;
        }

        return $handler;
    }


    /**
     * Dispatches a handle action taking into account the routing parameters
     *
     * @return object|bool
     */
    protected function _dispatch() {
        $value = $handler = $dependencyInjector = $namespaceName = $handlerName =
        $actionName = $params = $eventsManager =
        $actionSuffix = $handlerClass = $status = $actionMethod = $modelBinder =
        $e = $bindCacheKey = null;

        $dependencyInjector = $this->_dependencyInjector;
        if (!is_object($dependencyInjector)) {
            $this->_throwDispatchException('A dependency injection container is required to access related dispatching services', self::EXCEPTION_NO_DI);
            return false;
        }


        /*
         * Calling beforeDispatchLoop
         */
        $eventsManager = $this->_eventsManager;
        if (is_object($eventsManager)) {
            if ($this->_eventsManager->fire('dispatch:beforeDispatchLoop', $this) === false) {
                return false;
            }
        }

        $hasService = $wasFresh = false;
        $value = null;
        $handler = null;
        $numberDispatches = 0;
        $actionSuffix = $this->_actionSuffix;

        $this->_finished = false;

        while (!$this->_finished) {
            $numberDispatches++;

            /*
             * Throw an exception after 256 consecutive forwards
             */
            if ($numberDispatches == 256) {
                $this->_throwDispatchException('Dispatcher has detected a cyclic routing causing stability problems', self::EXCEPTION_CYCLIC_ROUTING);
                break;
            }

            $this->_finished = true;

            $this->_resolveEmptyProperties();

            $namespaceName = $this->_namespaceName;
            $handlerName = $this->_handlerName;
            $actionName = $this->_actionName;
            $handlerClass = $this->getHandlerClass();

            /*
             * Calling beforeDispatch
             */
            if (is_object($eventsManager)) {
                if ($this->_eventsManager->fire('dispatch:beforeDispatch', $this) === false) {
                    continue;
                }

                /*
                 * Check if the user made a forward in the listener
                 */
                if ($this->_finished === false) {
                    continue;
                }
            }


            /*
             * Handlers are retrieved as shared instances from the Service Container
             */
            $hasService = (bool) $dependencyInjector->has($handlerClass);
            if (!$hasService) {
                /*
                 * DI doesn't have a service with that name, try to load it using an autoloader
                 */
                $hasService = (bool) class_exists($handlerClass);
            }


            /*
             * If the service can be loaded we throw an exception
             */
            if (!$hasService) {
                $status = $this->_throwDispatchException($handlerClass . ' handler class cannot be loaded', self::EXCEPTION_HANDLER_NOT_FOUND);
                if ($status === false) {

                    /*
                     * Check if the user made a forward in the listener
                     */
                    if ($this->_finished === false) {
                        continue;
                    }
                }
                break;
            }


            /*
             * Handlers must be only objects
             */
            $handler = $dependencyInjector->getShared($handlerClass);

            /*
             * If the object was recently created in the DI we initialize it
             */
            if ($dependencyInjector->wasFreshInstance() === true) {
                $wasFresh = true;
            }


            if (!is_object($handler)) {
                $status = $this->_throwDispatchException('Invalid handler returned from the services container', self::EXCEPTION_INVALID_HANDLER);
                if ($status === false) {
                    if ($this->_finished === false) {
                        continue;
                    }
                }
                break;
            }

            $this->_activeHandler = $handler;

            /*
             * Check if the params is an array
             */
            $params = $this->_params;
            if (!is_array($params)) {

                /*
                 * An invalid parameter variable was passed throw an exception
                 */
                $status = $this->_throwDispatchException('Action parameters must be an Array', self::EXCEPTION_INVALID_PARAMS);
                if ($status === false) {
                    if ($this->_finished === false) {
                        continue;
                    }
                }
                break;
            }


            /*
             * Check if the method exists in the handler
             */
            $actionMethod = $actionName . $actionSuffix;

            if (!method_exists($handler, $actionMethod)) {

                /*
                 * Call beforeNotFoundAction
                 */
                if (is_object($eventsManager)) {

                    if ($this->_eventsManager->fire('dispatch:beforeNotFoundAction', $this) === false) {
                        continue;
                    }

                    if ($this->_finished === false) {
                        continue;
                    }
                }

                /*
                 * Try to throw an exception when an action isn't defined on the object
                 */
                $status = $this->_throwDispatchException("Action '" . $actionName . "' was not found on handler '" . $handlerName . "'", self::EXCEPTION_ACTION_NOT_FOUND);
                if ($status === false) {
                    if ($this->_finished === false) {
                        continue;
                    }
                }
                break;
            }


            /*
             * Calling beforeExecuteRoute
             */
            if (is_object($eventsManager)) {

                if ($this->_eventsManager->fire('dispatch:beforeExecuteRoute', $this) === false) {
                    continue;
                }

                /*
                 * Check if the user made a forward in the listener
                 */
                if ($this->_finished === false) {
                    continue;
                }
            }


            /*
             * Calling beforeExecuteRoute as callback and event
             */
            if (method_exists($handler, 'beforeExecuteRoute')) {

                if ($handler->beforeExecuteRoute($this) === false) {
                    continue;
                }

                /*
                 * Check if the user made a forward in the listener
                 */
                if ($this->_finished === false) {
                    continue;
                }
            }


            /**
             * Call the 'initialize' method just once per request
             */
            if ($wasFresh === true) {

                if (method_exists($handler, 'initialize')) {
                    $handler->initialize();
                }

                /**
                 * Calling afterInitialize
                 */
                if (is_object($eventsManager)) {
                    if ($eventsManager->fire('dispatch:afterInitialize', $this) === false) {
                        continue;
                    }

                    /*
                     * Check if the user made a forward in the listener
                     */
                    if ($this->_finished === false) {
                        continue;
                    }
                }
            }


            if($this->_modelBinding) {
                $modelBinder = $this->_modelBinder;
                $bindCacheKey = "_PHMB_" . $handlerClass . "_" . $actionMethod;
                $params = $modelBinder->bindToHandler($handler, $params, $bindCacheKey, $actionMethod);
            }

            $this->_lastHandler = $handler;

            // Calling afterBinding
            if(is_object($eventsManager)) {
                if($eventsManager->fire("dispatch:afterBinding", $this) === false) {
                    continue;
                }

                // Check if the user made a forward in the listener
                if ($this->_finished === false) {
                    continue;
                }
            }

            // Calling afterBinding as callback and event
            if(method_exists($handler, "afterBinding")) {
                if($handler->afterBinding($this) === false) {
                    continue;
                }

                // Check if the user made a forward in the listener
                if ($this->_finished === false) {
                    continue;
                }
            }


            try {
                // We update the latest value produced by the latest handler
                $this->_returnedValue = yield $this->callActionMethod($handler, $actionMethod, $params);
            } catch (\Exception $e) {
                if ($this->_handleException($e) === false) {
                    if ($this->_finished === false) {
                        continue;
                    }
                } else {
                    throw $e;
                }
            }

            // Calling afterExecuteRoute
            if(is_object($eventsManager)) {
                if($eventsManager->fire("dispatch:afterExecuteRoute", $this, $value) === false) {
                    continue;
                }

                if($this->_finished === false) {
                    continue;
                }

                // Call afterDispatch
                $eventsManager->fire("dispatch:afterDispatch", $this);
            }

            // Calling afterExecuteRoute as callback and event
            if (method_exists($handler, 'afterExecuteRoute')) {

                if ($handler->afterExecuteRoute($this, $value) === false) {
                    continue;
                }

                if ($this->_finished === false) {
                    continue;
                }
            }


        }//end while finished


        if(is_object($eventsManager)) {
            $eventsManager->fire("dispatch:afterDispatchLoop", $this);
        }


        return $handler;
    }




}