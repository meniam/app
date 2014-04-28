<?php

namespace App\Console\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mvc\Console\Application;
use App\Mvc\UrlBuilder\UrlBuilder;
use App\ServiceManager\ServiceManager;

abstract class AbstractAction
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var \App\Mvc\UrlBuilder\UrlBuilder
     */
    private static $urlBuilder;

    /**
     * @var ServiceManager
     */
    private static $serviceManager;

    /**
     * @var bool
     */
    protected $stopProcessing = false;

    /**
     * @var bool
     */
    protected $withoutLayout = false;

    /**
     * @var Application
     */
    private $application;

    /**
     * Остановить выполнение
     *
     * @var bool
     */
    private $breakRun = false;

    /**
     * @param Request  $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request      = $request;
        $this->response     = $response;
    }

    /**
     * @return Application|null
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param Application $application
     *
     * @return $this
     */
    public function setApplication(Application $application)
    {
        $this->application = $application;
        return $this;
    }

    /**
     *
     */
    public function indexAction()
    {
    }

    /**
     * @return \App\Http\Response
     */
    public function errorAction()
    {
        return $this->getApplication()->getConsoleUsageFull();
    }

    /**
     * @param array $actionResponse
     */
    public function postDispatch($actionResponse = array())
    { }

    /**
     * Невыполнять рендеринг стандартным рендерером
     *
     * @return $this
     */
    public function withoutViewRenderer()
    {
        $this->stopProcessing = true;
        return $this;
    }

    /**
     * @return \App\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return \App\Http\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param $serviceManager
     */
    public static function setServiceManager($serviceManager)
    {
        self::$serviceManager = $serviceManager;
    }

    /**
     * @return ServiceManager
     */
    public static function getServiceManager()
    {
        return self::$serviceManager;
    }

    /**
     * @return UrlBuilder
     */
    public function getUrlBuilder()
    {
        if (!self::$urlBuilder) {
            self::$urlBuilder = $this->getServiceManager()->get('url_builder');
        }

        return self::$urlBuilder;
    }

    /**
     * @param       $route
     * @param array $params
     * @return string
     */
    public function url($route, $params = array())
    {
        return $this->getUrlBuilder()->url($route, $params);
    }

    /**
     * @return \App\Mvc\View
     */
    public function getView()
    {
        /**
         * @var \App\Mvc\View
         */
        $view = self::getServiceManager()->get('view');

        return $view;
    }

    /**
     * @return void
     */
    public function preDispatch()
    { }

    /**
     * @param boolean $breakRun
     */
    public function setBreakRun($breakRun)
    {
        $this->breakRun = $breakRun;
    }

    /**
     * @return boolean
     */
    public function getBreakRun()
    {
        return $this->breakRun;
    }
}