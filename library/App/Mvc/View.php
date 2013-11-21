<?php

namespace App\Mvc;

use App\Mvc\Block\Block;
use App\Mvc\Block\Manager;
use App\Mvc\UrlBuilder\UrlBuilder;
use App\ServiceManager\ServiceManager;

class View extends \Blitz
{
    /**
     * @var array
     */
    private static $viewPathList = array();

    /**
     * @var Block
     */
    private $currentBlock;

    private static $blockManager;

    /**
     * @var ServiceManager
     */
    private static $serviceManager;

    /**
     * @var UrlBuilder
     */
    private $urlBuilder;

    public function __construct($template = null, $viewPath = null)
    {
        if ($viewPath) {
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

    public function img($img)
    {
        return $img;
    }

    /**
     * @param Block $currentBlock
     * @return $this
     */
    public function setCurrentBlock($currentBlock)
    {
        $this->currentBlock = $currentBlock;
        return $this;
    }

    /**
     * @return Block
     */
    public function getCurrentBlock()
    {
        return $this->currentBlock;
    }

    /**
     * @param Manager $blockManager
     * @return void
     */
    public static function setBlockManager(Manager $blockManager)
    {
        self::$blockManager = $blockManager;
    }

    /**
     * @return Manager
     */
    public static function getBlockManager()
    {
        return self::$blockManager;
    }

    /**
     * @param string $name
     * @return string
     */
    public function block($name)
    {
        $oldName = $name;
        try {
            $currentBlock = $this->getCurrentBlock();

            $name         = $currentBlock->getNamespace() . '/' . $name;
            $block        = $currentBlock->getBlock($name);
            return (string)$this->getBlockManager()->renderBlock($block, false);
        } catch (\Exception $e) {
            return '';
        }
    }

    public function _($string)
    {
        return $string;
    }

    public static function globalParam($name)
    {
        return self::global_param($name);
    }

    public static function global_param($name)
    {
        return "%%" . self::getSessionId() . '_' . $name . '%%';
    }

    private static $uniqSessionId;

    public static function getSessionId()
    {
        if (!self::$uniqSessionId) {
            self::$uniqSessionId = uniqid();
        }

        return self::$uniqSessionId;
    }

    public function setViewPath($path)
    {
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
                    $contents = file_get_contents($fullFilename);
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
        self::$viewPathList[] = realpath($viewPath);
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