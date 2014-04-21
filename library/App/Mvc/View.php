<?php

namespace App\Mvc;

use App\Mvc\Block\Block;
use App\Mvc\Block\Manager;
use App\Mvc\SaveGet\SaveGet;
use App\Mvc\UrlBuilder\UrlBuilder;
use App\ServiceManager\ServiceManager;

class View extends \Blitz
{
    /**
     * @var array
     */
    private static $viewPathList = array();

    /**
     * @var ServiceManager
     */
    private static $serviceManager;

    /**
     * @var UrlBuilder
     */
    private $urlBuilder;

    /**
     * @var SaveGet
     */
    private $saveGet;

    public function __construct($template = null, $viewPath = null)
    {
        if ($viewPath) {
            $this->addViewPath($viewPath);
            $this->setViewPath($viewPath);
        }

        ini_set('blitz.charset', 'UTF-8');
        parent::__construct($template);
    }

    public function url($route, $params = array())
    {
        if (!$this->urlBuilder) {
            $this->urlBuilder = self::getServiceManager()->get('url_builder');
        }

        return $this->urlBuilder->url($route, $params);
    }

    /**
     * @param array $replace
     * @param array $delete
     * @param null  $link
     *
     * @return mixed
     */
    public function saveGet($replace = array(), $delete = array(), $link = null)
    {
        if (!$this->saveGet) {
            $this->saveGet = self::getServiceManager()->get('save_get');
        }

        return $this->saveGet->url($replace, $delete, $link);
    }

    public function img($img)
    {
        return $img;
    }

    public function _($string)
    {
        return $string;
    }


    public function setViewPath($path)
    {
        if (is_array($path)) {
            $path = reset($path);
        }
        ini_set('blitz.path', realpath($path) . '/');
    }

    /**
     * @param       $filename
     * @param array $vars
     * @return string
     */
    public function includeTpl($filename, $vars = array())
    {
        if (!is_array($vars)) {
            $vars = (array)$vars;
        }

        if ($filename[0] != '/') {
            foreach ($this->getViewPath() as $viewPath) {
                $fullFilename = $viewPath . DIRECTORY_SEPARATOR . $filename;

                if (is_file($fullFilename)) {
                    //$contents = file_get_contents($fullFilename);
                    return $this->include($fullFilename, $vars);
                }
            }
        } else {
            return $this->include($filename, $vars);
        }

        return '';
    }

    /**
     * @param       $string
     * @param array $vars
     * @return string
     */
    public function renderString($string, $vars = array())
    {
        $this->load($string);
        return $this->parse($vars);
    }

    /**
     * @param $viewPath
     */
    public function addViewPath($viewPath)
    {
        if (is_array($viewPath)) {
            foreach ($viewPath as $vp) {
                self::$viewPathList[] = realpath($vp);
            }
        } else {
            self::$viewPathList[] = realpath($viewPath);
        }
    }

    /**
     * @return array
     */
    public function getViewPath()
    {
        return self::$viewPathList;
    }

    /**
     * @param      $var
     * @param null $value
     * @return View
     */
    public function assign($var, $value = null)
    {
        if (!is_array($var)) {
            $var = array($var => $value);
        }

        $this->set($var);
        return $this;
    }

    /**
     * @param ServiceManager $serviceManager
     */
    public static function setServiceManager(ServiceManager $serviceManager)
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

}