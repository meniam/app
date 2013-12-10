<?php

namespace App\Mvc;

use App\Exception\InvalidArgumentException;
use App\Mvc\Controller\AbstractAction;
use App\ServiceManager\ServiceManager;
use Zend\ServiceManager\Config;
use App\Http\Request;
use App\Http\Response;
use App\Loader\Autoloader;

class Application implements ApplicationInterface
{
    /**
     * @var \App\Http\Request
     */
    private $request;

    /**
     * @var \App\Http\Response
     */
    private $response;

    private $controllerNamespace;


    private $config;

    /**
     * @var \App\ServiceManager\ServiceManager
     */
    private $serviceManager;

    private $requestUri;

    public function __construct($config, ServiceManager $serviceManager)
    {
        $this->config         = $config;
        $this->serviceManager = $serviceManager;
        $this->request        = $serviceManager->get('Request');
        $this->response       = $serviceManager->get('Response');
        $this->requestUri     = $this->getRequest()->getRequestUri();
    }

    /**
     * @param mixed $controllerNamespace
     */
    public function setControllerNamespace($controllerNamespace)
    {
        $this->controllerNamespace = $controllerNamespace;
    }

    /**
     * @return mixed
     */
    public function getControllerNamespace()
    {
        return $this->controllerNamespace;
    }

    /**
     * @return \App\ServiceManager\ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Get the request object
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the response object
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Run the application
     *
     * @param null $requestUri
     * @return Response
     */
    public function run($requestUri = null)
    {
        if ($requestUri) {
            $this->requestUri = $requestUri;
        }

        // Роутим
        $params = $this->route();

        if ($params === false) {
            $params = array(
                'controller' => 'error',
                'action'     => 'error404'
            );
        }

        $this->getRequest()->setParams($params);

        // Диспетчим данные
        return $this->dispatch($this->getRequest(), $this->getResponse());
    }

    /**
     * @return array|bool
     */
    protected function route()
    {
        if (!isset($this->config['routes'])) {
            return false;
        }

        $routes = $this->config['routes'];

        list($path) = explode('?', $this->requestUri, 2);

        $path = trim(urldecode($path), '/ ');

        $return = false;

        foreach ($routes as $routeName => $routeParams) {
            $regex = '#^' . trim($routeParams['route'], ' \\\/') . '$#i';
            $res   = preg_match($regex, $path, $values);

            if ($res === 0) {
                continue;
            }

            $map      = isset($routeParams['map']) ? $routeParams['map'] : array();
            $defaults = isset($routeParams['defaults']) ? $routeParams['defaults'] : array();

            $values   = $this->_getMappedValues($map, $values);
            $defaults = $this->_getMappedValues($map, $defaults, false, true);
            $return   = $values + $defaults;

            $return['_route_name'] = $routeName;

            break;
        }

        return $return;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request)
    {
        $controller = $request->getParam('controller');
        $action     = $request->getParam('action');
        return $this->dispatchControllerAction($controller, $action);
    }

    /**
     * Диспетчеризация для Controller Action блока
     *
     * @param Block $block
     * @return Response|bool
     */
    public function dispatchControllerAction($controllerParam, $actionParam)
    {
        try {
            $request = $this->getServiceManager()->get('request');

            $controllerClass = $this->controllerNamespace . implode('', array_map('ucfirst', explode('-', $controllerParam))) . 'Controller';
            $actionMethod    = implode('', array_map('ucfirst', explode('-', $actionParam)))     . 'Action';
            $actionMethod    = strtolower(substr($actionMethod, 0, 1)) . substr($actionMethod, 1);

            $response = $this->getResponse();
            /** @var $controller AbstractAction */
            $controller = new $controllerClass($request, $response);

            $classMethods = get_class_methods($controller);

            if (in_array('preDispatch', $classMethods)) {
                $controller->preDispatch();
            }

            $forward = $controller->getForward();
            if (!$controller->getBreakRun() && empty($forward)) {
                $actionResponse = $controller->$actionMethod();

                if (in_array('postDispatch', $classMethods)) {
                    $controller->postDispatch($actionResponse);
                }
            }
            /*
            if (!empty($forward)) {
                $request->setParams($forward);
                $controller->removeForward();
                return $this->dispatch($request, $response);
            }*/
            return $response;
        } catch (Exception $e) {
            $response = $this->getResponse();
            $response->setException($e);
        }


        return $response;
        //return false;
    }

    /**
     * Static method for quick and easy initialization of the Application.
     *
     * If you use this init() method, you cannot specify a service with the
     * name of 'ApplicationConfig' in your service manager config. This name is
     * reserved to hold the array from application.config.php.
     *
     * @param array $configuration
     * @throws \App\Exception\InvalidArgumentException
     * @return Application
     */
    public static function init($configuration = array())
    {
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $serviceManager = new ServiceManager(new Config($smConfig));

        return $serviceManager->get('application');
    }

    /**
     * Maps numerically indexed array values to it's associative mapped counterpart.
     * Or vice versa. Uses user provided map array which consists of index => name
     * parameter mapping. If map is not found, it returns original array.
     *
     * Method strips destination type of keys form source array. Ie. if source array is
     * indexed numerically then every associative key will be stripped. Vice versa if reversed
     * is set to true.
     *
     * @param array    $map
     * @param  array   $values   Indexed or associative array of values to map
     * @param  boolean $reversed False means translation of index to association. True means reverse.
     * @param  boolean $preserve Should wrong type of keys be preserved or stripped.
     * @return array   An array of mapped values
     */
    protected function _getMappedValues(array $map, $values, $reversed = false, $preserve = false)
    {
        if (count($map) == 0) {
            return $values;
        }

        $return = array();

        foreach ($values as $key => $value) {
            if (is_int($key) && !$reversed) {
                if (array_key_exists($key, $map)) {
                    $index = $map[$key];
                } elseif (false === ($index = array_search($key, $map))) {
                    $index = $key;
                }
                $return[$index] = $values[$key];
            } elseif ($reversed) {
                $index = $key;
                if (!is_int($key)) {
                    if (array_key_exists($key, $map)) {
                        $index = $map[$key];
                    } else {
                        $index = array_search($key, $map, true);
                    }
                }
                if (false !== $index) {
                    $return[$index] = $values[$key];
                }
            } elseif ($preserve) {
                $return[$key] = $value;
            }
        }

        return $return;
    }
}