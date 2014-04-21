<?php

namespace App\Mvc\Controller;

use App\Http\Header\Cookie;
use App\Http\Request;
use App\Http\Response;
use App\Mvc\Block\Block;

abstract class  AbstractAction
{
    const ERROR_404 = 404;
    const ERROR_403 = 403;
    const ERROR_500 = 500;

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

    private static $serviceManager;

    protected $stopProcessing = false;

    protected $withoutLayout = false;

    /**
     * Скрипт который нужно рендерить
     *
     * @var string
     */
    private $viewScript;

    /** @var \Model\Entity\UserEntity */
    private static $currentUser;

    /**
     * Остановить выполнение
     *
     * @var bool
     */
    private $breakRun = false;

    /**
     * Данные для редиректа controller => 'name', action => 'name', param => 'name'
     *
     * @var array|null
     */
    private $forward = null;

    /**
     * Текущий вид представления
     *
     * @var string
     */
    private $layout = 'default';

    /**
     * @param string $layout
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
    }

    /**
     * @return string
     */
    public function getLayout()
    {
        return $this->layout ? $this->layout : $this->getRequest()->getParam('layout');
    }

    /**
     * @param array|null $forward
     * @return $this
     */
    public function setForward($forward)
    {
        $this->forward = $forward;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getForward()
    {
        return $this->forward;
    }

    /**
     * @return array|null
     */
    public function removeForward()
    {
        $this->forward = null;
        return $this;
    }

    public function __construct(Request $request, Response $response)
    {
        $this->request      = $request;
        $this->response     = $response;
        //$this->currentBlock = $block;
    }

    /**
     * @return \App\Mvc\Block\Block
     */
    public function getCurrentBlock()
    {
        return $this->currentBlock;
    }

    /**
     * @return \Model\Entity\UserEntity
     */
    public function getCurrentUser()
    {
        if (!self::$currentUser) {
            self::$currentUser = $this->getServiceManager()->get('user');
        }

        return self::$currentUser;
    }

    public function indexAction()
    {}

    /**
     * @param $errorType
     * @return \App\Http\Response
     */
    public function errorAction($errorType)
    {
        /** @var $application \App\Mvc\Application */
        $application = $this->getServiceManager()->get('application');

        $request = $this->getRequest();
        $request->setParam('controller', 'error');
        $request->setParam('layout', 'default');

        switch ($errorType) {
            case self::ERROR_404:
                $request->setParam('action', 'error404');
                break;
            case self::ERROR_403:
                $request->setParam('action', 'error403');
                break;
            default:
                $request->setParam('action', 'error500');
        }

        $this->stopProcessing = true;
        return $application->dispatch($request, $this->getResponse());
    }

    public function postDispatch($actionResponse = array())
    {
        $request = $this->getRequest();
        $this->setLayout($request->getParam('layout', $this->getLayout()));

        $this->viewRenderer($actionResponse);
    }

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

    public function viewRenderer($actionResponse = array())
    {
        // Если обработка остановлена в контроллере
        if ($this->stopProcessing || $this->getBreakRun()) {
            return;
        }

        $viewScript = $this->getViewScript();

        // End of View Renderer
        $view = $this->getView();
        /*if ($actionResponse) {
            $view->set($actionResponse);
        }*/

        $actionResponse['content'] = $view->includeTpl($viewScript, $actionResponse);

        $this->getResponse()
             ->setBody(
                $this->getLayoutView()
                     ->includeTpl($this->getLayout() . '.phtml', $actionResponse));

        //$this->getResponse()->setBody($content);
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


    public static function getServiceManager()
    {
        return self::$serviceManager;
    }

    /**
     * @return \App\Mvc\UrlBuilder\UrlBuilder
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

    public static function setServiceManager($serviceManager)
    {
        self::$serviceManager = $serviceManager;
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
     * @return \App\Mvc\View
     */
    public function getLayoutView()
    {
        return self::getServiceManager()->get('layout_view');
    }

    /**
     * @return \Model\Entity\LanguageEntity
     */
    public function getLanguage()
    {
        $language = new \Model\Entity\LanguageEntity(array('id' => 1, 'code' => 'en'));
        return $language;
    }

    public function renderTitle($titleArray = array(), $delimiter = ' - ')
    {
        return implode($delimiter, array_map('trim', $titleArray));
    }

    public function renderMetaKeywords($keywordListArray = array())
    {
        if (is_string($keywordListArray)) {
            $keywordListArray = explode(',', $keywordListArray);
        }

        $keywordFilter = function ($str) {
            return trim(preg_replace('#\s+#', ' ', $str));
        };

        if (count($keywordListArray) < 2) {
            $keywordListArray = array_map(array($this, 'stripText'), explode(' ', reset($keywordListArray)));
        }

        $keywordListArray = array_map($keywordFilter, $keywordListArray);
        $keywordListArray = array_map(function ($a) { return mb_strtolower($a, 'UTF-8'); }, array_unique($keywordListArray));

        return implode(', ', $keywordListArray);
    }

    public function renderMetaDescription($metaDescription)
    {
        if (is_array($metaDescription)) {
            $metaDescription = implode('. ', $metaDescription);
        }

        return mb_substr($this->stripText($metaDescription), 0, 255, 'UTF-8');
    }

    public function stripText($str)
    {
        $str = \App\Filter\Filter::filterStatic($str, '\App\Filter\StripText');
        $str = str_replace('"', '&quot;', $str);
        return $str;
    }

    /**
     * @param string $name
     * @param string $value
     * @param App_DateTime|App_DateInterval|int $expires Date, interval or UNIX timestamp
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @return BaseController
     */
    public function setCookie($name, $value, $expires = null, $path = null, $domain = null, $secure = false)
    {
        if (!$domain) {
            $domain = '.' . PROJECT_HOST;
        }

        $cookie = new Cookie($name, $value, $domain, $expires, $path, $secure);
        $this->getResponse()->setCookie($cookie);
        return $this;
    }

    public function preDispatch()
    {

    }

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

    public function forward($action, $controller = null, array $params = array())
    {
        $this->getServiceManager()->get('application')->run($url);
        $params['action'] = (string)$action;
        $params['controller'] = $controller ? $controller : $this->getRequest()->getParam('controller');

        return $this->setForward($params);
    }

    /**
     * @param $url
     * @return $this
     */
    public function redirect($url)
    {
        $this->getResponse()->setRedirect($url);
        $this->setBreakRun(true);
        return $this;
    }

    /**
     * @return \App\Mvc\Block\Manager
     */
    public function getBlockManager()
    {
        if (!$this->blockManager) {
            $this->blockManager = $this->getServiceManager()->get('block_manager');
        }

        return $this->blockManager;
    }

    /**
     * @param $viewScript
     * @return $this
     */
    public function setViewScript($viewScript)
    {
        $this->viewScript = $viewScript;
        return $this;
    }

    /**
     * @return string
     */
    public function getViewScript()
    {
        if (!$this->viewScript) {
            $this->viewScript = $this->getRequest()->getParam('controller') . '/' . $this->getRequest()->getParam('action') . '.phtml';
        }
        return $this->viewScript;
    }

}