<?php

namespace App\Mvc\Block;

use App\Exception\InvalidArgumentException;
use App\Mvc\Controller\AbstractAction;
use App\ServiceManager\ServiceManager;
use App\Http\Response;
use App\Http\Request;
//use Blitz\View;

/**
 * Управление блоками в системе
 *
 * @package App\Mvc\Block
 */
class Manager extends \ArrayIterator
{
    protected $blockPath = array();

    /**
     * @var bool
     */
    protected $allowCache = false;

    /**
     * Путь с кешем блоков
     *
     * @var string
     */
    protected $cachePath;

    /**
     * @var \App\ServiceManager\ServiceManager
     */
    protected $serviceManager;

    /**
     * @var string
     */
    protected $responseClass;

    private $breakRender = false;

    private $emptyResponse;

    private $controllerNamespace = 'Application\\Controller\\Block\\';

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function setControllerNamescpace($namespace)
    {
        $this->controllerNamespace = (string)$namespace;
        return $this;
    }

    /**
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Проверить наличие блока
     *
     * @param $block
     * @return bool
     */
    public function hasBlock($block)
    {
        return isset($this[$block]);
    }

    /**
     * @param $block
     *
     * @throws \Exception
     * @return Block
     */
    public function getBlock($block)
    {
        $blockName = $block;
        $blockParams = explode('/', $block);
        if (count($blockParams) != 2) {
            throw new \Exception('Wrong block param format 2');
        }

        if (isset($this[$blockName])) {
            return $this[$blockName];
        }

        $blockArray = array();
        if ($this->getAllowCache()) {
            $file = $this->cachePath . DIRECTORY_SEPARATOR . $block . '.array';

            if (is_file($file)) {
                $blockArray = require($file);
                $block = new Block($blockArray);
                $this[$block->getName()] = $block;
                return $block;
            }
        }

        if (empty($blockArray)) {
            $blockArray = $this->loadBlockArray($block);
            $blockObject = $this->createBlock($blockArray);
            $this[$blockParams[0] . '/' . $blockParams[1] . '/' . $blockObject->getName()] = $blockObject;

            if ($this->allowCache && isset($file)) {
                $code = "<?php\n\n return " . var_export($blockObject->toArray(), true) . ";\n";

                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0755, true);
                }

                file_put_contents($file, $code);
            }
            return $this[$blockParams[0] . '/' . $blockParams[1] . '/' . $blockObject->getName()];
        }

        return $this->createBlock($blockArray);
    }


    protected function fixNames(&$blockArray, $prefix)
    {
        if (isset($blockArray['name']) && strpos($blockArray['name'], '/') === false) {
            $blockArray['name'] = $prefix . '/' . $blockArray['name'];
        }

        if (isset($blockArray['block'])) {
            foreach ($blockArray['block'] as $i => $innerBlock) {
                $blockArray['block'][$i] = $this->fixNames($innerBlock, $prefix);
            }
        }

        return $blockArray;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        if (!$this->responseClass) {
            $this->responseClass = get_class($this->getServiceManager()->get('response'));
        }

        return new $this->responseClass;
    }

    /**
     * @return Response
     */
    public function getEmptyResponse()
    {
        if (!$this->emptyResponse) {
            $this->emptyResponse = $this->getResponse();
        }

        return $this->emptyResponse;
    }

    public function getUserRole()
    {
        return $this->getServiceManager()->get('user')->getRole();
    }

    /**
     * @param string|Block $block
     * @param bool         $autorenderOn
     *
     * @return \App\Http\Response
     */
    public function renderBlock($block, $autorenderOn = true)
    {
        if (!$block instanceof Block) {
            $block = $this->getBlock($block);
        }


        if ($block->getRoleAllow() && !in_array($this->getUserRole(), $block->getRoleAllow())) {
            return $this->getEmptyResponse();
        }

        if ($block->getRoleDeny() && in_array($this->getUserRole(), $block->getRoleDeny())) {
            return $this->getEmptyResponse();
        }

        // Если блок не нужно показывать, то возвращаем пустой Response
        if (!$block->getShow() || ($autorenderOn && !$block->getAutorender())) {
            return $this->getEmptyResponse();
        }

        //$responseBody = '';
        if (($controller = $block->getController()) && ($action = $block->getAction())) {
            $status = $this->dispatchControllerAction($block);
            if ($status === false) {
                return $this->getEmptyResponse();
            } elseif ($status instanceof Response) {
                if ($status->isRedirect() || $status->isException()) {
                    return $status;
                } else {
                    $response = $status;
                }
            }
        } elseif ($partial = $block->getPartial()) {
            $response = $this->getResponse();
            $response->setBody($this->renderPartial($partial, $block));
        } elseif ($blockContent = $block->getContent()) {
            if (strpos($blockContent, '{{') !== false) {
                $blockContent = $this->renderString($blockContent, $block);
            }
            $response = $this->getResponse();
            $response->setBody($blockContent);
        } else {
            $response = $this->getResponse();
        }

        $blockBody = $response->getBody();

        if (($childArray = $block->getBlocks()) && !empty($childArray)) {
            $childListBody = '';
            foreach ($childArray as $childBlock) {
                // Если можно рендерить автоматически
                if ($childBlock->getAutorender()) {
                    $childResponse = $this->renderBlock($childBlock, false);
                    $childListBody .= (string) $childResponse;
                }
            }

            if ($childListBody) {
                if ($innerTagParam = $block->getInnerTag()) {
                    $childListBody = $this->wrapContentInHtmlTag($childListBody, $innerTagParam);
                }
            }

            if (strpos($block->getContent(), '{{') !== false && !$block->getWrapperContent()) {
                $childListBody = $this->renderString($block->getContent(), $block, array('content' => $childListBody));
                $blockBody = $childListBody;
            } else {
                $blockBody .= $childListBody;
            }
        }

        if ($wrapperTagParam = $block->getWrapperTag()) {
            $blockBody = $this->wrapContentInHtmlTag($blockBody, $wrapperTagParam);
        }

        if (!empty($this->globalParams)) {
            $blockBody = str_replace(array_keys($this->globalParams), array_values($this->globalParams), $blockBody);
        }

        //Оборачиваем блок другим блоком, если пришел враппер
        if ($wrapperBlock = $block->getWrapperBlock()) {
            $wrapperBlock = $this->createBlock($wrapperBlock);

            if ($wrapperBlock->getShow(true)) {
                $wrapperBlock->setWrapperContent($blockBody);
                $wrapperResponse = $this->renderBlock($wrapperBlock);
                return $wrapperResponse;
            }
        } else {
            return $response->setBody($blockBody);
        }
    }

    /**
     * @param $response
     * @return Response|bool
     */
    public function processResponse($response)
    {
        if ($response === false) {
            return $response;
        } elseif ($response instanceof Response) {
            if ($response->isRedirect() || $response->isException()) {
                $this->breakRender = true;
                return $response;
            }
        }

        return $response;
    }

    /**
     * Отрендерить шаблон
     *
     * @param       $partial
     * @param Block $block текущий блок для шаблона
     * @return string
     */
    public function renderPartial($partial, Block $block)
    {
        return $this->getView($block)->includeTpl($partial, $this->prepareBlockParamsArrayForView($block));
    }

    public function renderString($string, Block $block, array $params = array())
    {
        $blockParams = $this->prepareBlockParamsArrayForView($block);
        if (!empty($params)) {
            $params = array_merge($blockParams, $params);
        } else {
            $params = $blockParams;

        }

        return $this->getView($block)->renderString($string, $params);
    }

    /**
     * @param Block $block
     * @return array
     */
    protected function prepareBlockParamsArrayForView(Block $block)
    {
        $params = $block->getParams();
        $templateParams = array();
        if ($params && is_array($params)) {
            foreach ($params as $k => &$v) {
                if (isset($v['value'])) {
                    $templateParams[$k] = $v['value'];
                }
            }
        }

        if ($wrapperContent = $block->getWrapperContent()) {
           $templateParams['content'] = $wrapperContent;
        } elseif ($content = $block->getContent()) {
            $templateParams['content'] = $content;
        }

        return $templateParams;
    }

    /**
     * @param Block $block
     * @return \App\Mvc\View
     */
    private function getView(Block $block = null)
    {
        /**
         * @var View
         */
        $view = $this->getServiceManager()->get('view');

        if ($block) {
            $view->setCurrentBlock($block);
        }

        return $view;
    }

    /**
     * Диспетчеризация для Controller Action блока
     *
     * @param Block $block
     * @return Response|bool
     */
    public function dispatchControllerAction($block)
    {
        try {
            $controllerParam = $block->getController();
            $actionParam     = $block->getAction();

            $request = $this->getServiceManager()->get('request');

            $controllerClass = $this->controllerNamespace . implode('', array_map('ucfirst', explode('-', $controllerParam))) . 'Controller';
            $actionMethod    = implode('', array_map('ucfirst', explode('-', $actionParam)))     . 'Action';
            $actionMethod    = strtolower(substr($actionMethod, 0, 1)) . substr($actionMethod, 1);

            $response = $this->getResponse();
            /** @var $controller AbstractAction */
            $controller = new $controllerClass($request, $response, $block);

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

        return false;
    }

    public function convertXmlArray($blockArray)
    {
        $spec = array();
        if (isset($blockArray['@attributes'])) {
            $spec['name']       = isset($blockArray['@attributes']['name'])  ? $blockArray['@attributes']['name'] : null;
            $spec['type']       = isset($blockArray['@attributes']['type'])  ? $blockArray['@attributes']['type'] : null;
            $spec['autorender'] = isset($blockArray['@attributes']['autorender']) ? self::parseBool($blockArray['@attributes']['autorender']) : true;
            $spec['label']      = isset($blockArray['@attributes']['label']) ? $blockArray['@attributes']['label'] : null;
            $spec['show']       = isset($blockArray['@attributes']['show'])  ? self::parseBool($blockArray['@attributes']['show']) : null;
            $spec['pos']        = isset($blockArray['@attributes']['pos'])   ? (int)$blockArray['@attributes']['pos'] : null;
            $spec['role_allow'] = isset($blockArray['@attributes']['role_allow'])   ? self::parseRole($blockArray['@attributes']['role_allow']) : array();
            $spec['role_deny']  = isset($blockArray['@attributes']['role_deny'])   ? self::parseRole($blockArray['@attributes']['role_deny']) : array();
        }

        if (isset($blockArray['controller'])) {
            if (isset($blockArray['controller']['@attributes']['value'])) {
                $spec['controller'] = $blockArray['controller']['@attributes']['value'];
            } else {
                $spec['controller'] = 'index';
            }
        }

        if (isset($blockArray['partial']) && isset($blockArray['partial']['@attributes']['value'])) {
            $spec['partial'] = (string)$blockArray['partial']['@attributes']['value'];
        }

        if (isset($blockArray['action'])) {
            if (isset($blockArray['action']['@attributes']['value'])) {
                $spec['action'] = $blockArray['action']['@attributes']['value'];
            } else {
                $spec['action'] = 'index';
            }
        }

        if (isset($blockArray['content'])) {
            $values = array();
            if (isset($blockArray['content']['@attributes']['value'])) {
                $values[] = $blockArray['content']['@attributes']['value'];
            }

            if (isset($blockArray['content']['value'])) {
                if (!is_string($blockArray['content']['value'])) {
                    $values[] = implode('', $blockArray['content']['value']);
                } else {
                    $values[] = $blockArray['content']['value'];
                }
            }

            $spec['content'] = implode('', $values);
        }

        if (isset($blockArray['wrapper_tag'])) {
            $spec['wrapper_tag'] = array(
                'html_tag' => (isset($blockArray['wrapper_tag']['@attributes']['html_tag']) ? $blockArray['wrapper_tag']['@attributes']['html_tag'] : 'div'),
                'css_class' => (isset($blockArray['wrapper_tag']['@attributes']['css_class']) ? $blockArray['wrapper_tag']['@attributes']['css_class'] : null),
                'modifier_css_class' => (isset($blockArray['wrapper_tag']['@attributes']['modifier_css_class']) ? $blockArray['wrapper_tag']['@attributes']['modifier_css_class'] : null),
                'show' => (isset($blockArray['wrapper_tag']['@attributes']['show']) ? self::parseBool($blockArray['wrapper_tag']['@attributes']['show']) : true)
            );
        }

        if (isset($blockArray['wrapper_block'])) {
            $wrapperBlockArray = $this->convertXmlArray($blockArray['wrapper_block']);
            $spec['wrapper_block'] = $wrapperBlockArray;
        }

        if (isset($blockArray['inner_tag'])) {

            $spec['inner_tag'] = array(
                'html_tag' => (isset($blockArray['inner_tag']['@attributes']['html_tag']) ? $blockArray['inner_tag']['@attributes']['html_tag'] : 'div'),
                'css_class' => (isset($blockArray['inner_tag']['@attributes']['css_class']) ? $blockArray['inner_tag']['@attributes']['css_class'] : null),
                'modifier_css_class' => (isset($blockArray['inner_tag']['@attributes']['modifier_css_class']) ? $blockArray['inner_tag']['@attributes']['modifier_css_class'] : null),
                'show' => (isset($blockArray['inner_tag']['@attributes']['show']) ? self::parseBool($blockArray['inner_tag']['@attributes']['show']) : true)
            );
        }

        if (isset($blockArray['param'])) {
            $spec['param'] = self::parseParam($blockArray['param']);
        }

        if (isset($blockArray['block'])) {
            if (!is_int(key($blockArray['block']))) {
                $blockArray['block'] = array($blockArray['block']);
            }

            foreach ($blockArray['block'] as $_innerBlockArray) {
                $innerBlockArray = $this->convertXmlArray($_innerBlockArray);

                if (empty($innerBlockArray)) {
                    continue;
                }

                if (!isset($innerBlockArray['name'])) {
                    throw new InvalidArgumentException('Block must have a name');
                }

                try {
                    $globalBlock = $this->loadBlockArray($innerBlockArray['name']);
                    $innerBlockArray = self::arrayMergeRecursive($globalBlock, $innerBlockArray);
                } catch (\Exception $e) {

                }
                $spec['block'][$innerBlockArray['name']] = $innerBlockArray;
            }
        }

        if (isset($blockArray['parent']['@attributes']['name'])) {
            $spec['parent'] = $blockArray['parent']['@attributes']['name'];
        }

        if (isset($blockArray['extends']['@attributes']['name'])) {
            $spec['extends'] = $blockArray['extends']['@attributes']['name'];
        }

        if (isset($blockArray['css']['include'])) {
            $nodes = $blockArray['css']['include'];

            if (!is_int(key(reset($nodes)))) {
                $nodes = array($nodes);
            }

            foreach ($nodes as $node) {
                if (!isset($node['@attributes']['value'])) {
                    continue;
                }

                if (!isset($node['@attributes']['name'])) {
                    throw new InvalidArgumentException('include/exclude node must have a name');
                }

                $name  = $node['@attributes']['name'];
                $media = (isset($node['@attributes']['media'])) ? $node['@attributes']['media'] : 'all';
                $value = $node['@attributes']['value'];

                $spec['css'][$media][$name] = $value;
            }
        }

        if (isset($blockArray['css']['exclude'])) {
            $nodes = $blockArray['css']['exclude'];

            if (!is_int(key(reset($nodes)))) {
                $nodes = array($nodes);
            }

            foreach ($nodes as $node) {
                if (!isset($node['@attributes']['name'])) {
                    throw new InvalidArgumentException('include/exclude node must have a name');
                }

                $name  = $node['@attributes']['name'];
                $media = (isset($node['@attributes']['media'])) ? $node['@attributes']['media'] : 'all';

                if (isset($spec['css'][$media][$name])) {
                    unset($spec['css'][$media][$name]);
                }
            }
        }

        return $spec;
    }

    /**
     * guest, test => array('guest', 'test')
     *
     * @param $role
     * @return array
     */
    public static function parseRole($role)
    {
        $role = trim($role);
        if (!$role) {
            return array();
        }
        $role = strtolower($role);
        return array_map('trim', preg_split('#[\,\|\s]+#si', $role));
    }

    public function createBlock(array $blockArray)
    {
        return new Block($blockArray, $this);
    }

    /**
     * Обрабатывает массив блока, нормализует и склеивает с родителем
     *
     * @param $blockArray
     * @return array|mixed
    protected function prepareBlockArray($blockArray)
    {

        $parentBlock = null;
        if (isset($blockArray['parent'])) {
            $parentBlock = self::loadBlockArray($blockArray['parent'], array($blockArray['name'] => $blockArray));
        }
        return $parentBlock ? $parentBlock : $blockArray;
    }
     */

    /**
     * Загружает блок в массив, с учетом родителей
     *
     * @param       $block
     * @param array $childBlock
     * @param array $extendedBlockArray
     * @return array|mixed
     */
    protected function loadBlockArray($block, array $childBlock = array(), array $extendedBlockArray = array())
    {
        $blockParams = explode('/', $block);
        if (count($blockParams) != 2) {
            throw new \Exception('Wrong block param format');
        }

        if (!$xml = $this->loadBlockXmlFile($block)) {
            return array();
        }
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        $blockArray = $this->convertXmlArray($this->normalizeArray(json_decode(json_encode((array)$xml), 1)));
        $this->fixNames($blockArray, $blockParams[0] . '/' . $blockParams[1]);

        if (!empty($extendedBlockArray)) {
            $blockArray = self::arrayMergeRecursive($blockArray, $extendedBlockArray);
            unset($blockArray['extends']);
        }

        if ($childBlock && !empty($childBlock)) {
            foreach ($childBlock as $n => $v) {
                $blockArray['block'][$n] = $v;
            }
        }
        if (isset($blockArray['extends'])) {
            $blockArray = self::loadBlockArray($blockArray['extends'], array(), $blockArray);
        }

        return $blockArray;
    }

    public static function  arrayMapRecursive($callback, $value)
    {
        if (is_array($value)) {
            return array_map(function($value) use ($callback) { return self::arrayMapRecursive($callback, $value); }, $value);
        }
        return $callback($value);
    }

    /**
     *
     */
    protected function fixBlock($blockArray)
    {
        if (isset($blockArray['block'])) {
            foreach ($blockArray['block'] as $n => $blockItem) {
                $realBlock = $this->getBlock($blockItem['name'])->toArray();
                //var_dump ($realBlock);
                //print_r($blockItem);
                //$blockArray['block'][$n] =$blockItem;
                $blockArray['block'][$n] = self::arrayMergeRecursive($realBlock, $blockItem);
            }

            reset($blockArray['block']);
            foreach ($blockArray['block'] as &$innerBlock) {
                if ($newBlocks = $this->fixBlock($innerBlock)) {
                    $innerBlock = $newBlocks;
                }

            }
        }

        return $blockArray;
    }

    /**
     * XML Может разобрать элемент как один элемент массива, а может как набор вложенных
     *
     * @param array $data
     * @return array
     */
    protected function normalizeArray(array $data)
    {
        foreach ($data as $k => &$v) {
            if (is_array($v) && !is_int($k) && in_array($k, array('param', 'block')) && !is_int(key($v))) {
                unset($data[$k]);
                $data[$k][] = $v;
            }

            if (is_array($v)) {
                $v = self::normalizeArray($v);
            }
        }

        return $data;
    }

    /**
     * Соединяет два и более массивов
     *
     * @return mixed
     */
    public static function arrayMergeRecursive()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        foreach ($arrays as $array) {
            reset($base); //important
            while (list($key, $value) = @each($array)) {
                if (is_array($value) && @is_array($base[$key])) {
                    $base[$key] = self::arrayMergeRecursive($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }


    /**
     * Разбираем параметры из XML поддержка вложенных
     *
     * @param array $params
     * @throws \App\Exception\InvalidArgumentException
     * @return array
     */
    protected static function parseParam(array $params)
    {
        if (!is_int(key($params))) {
            $params = array($params);
        }

        $resultParams = array();
        foreach ($params as $param) {
            $resultParam = array();
            if (isset($param['@attributes'])) {
                $resultParam['name'] = isset($param['@attributes']['name']) ? $param['@attributes']['name'] : null;
                $resultParam['type'] = isset($param['@attributes']['type']) ? $param['@attributes']['type'] : null;
                $resultParam['value'] = isset($param['@attributes']['value']) ? $param['@attributes']['value'] : null;

                if ($resultParam['type']) {
                    switch ($resultParam['type']) {
                        case 'bool':
                        case 'boolean':
                            $resultParam['value'] = self::parseBool($resultParam['value']);
                            break;
                    }
                }

                $resultParam['default'] = isset($param['@attributes']['default']) ? $param['@attributes']['default'] : null;
                $resultParam['pos'] = isset($param['@attributes']['pos']) ? $param['@attributes']['pos'] : null;
            }

            if (isset($param['param'])) {
                $childParams = self::parseParam($param['param']);

                foreach ($childParams as $childParam) {
                    $resultParam[$childParam['name']] = $childParam;
                }
            }

            if (!isset($resultParam['name'])) {
                throw new InvalidArgumentException('Params must have a name');
            }

            if (in_array($resultParam['name'], array('name', 'default', 'value', 'type'))) {
                throw new InvalidArgumentException("Name cant use this ['name', 'default', 'value', 'type']");

            }

            $resultParams[$resultParam['name']] = $resultParam;
        }

        return $resultParams;
    }

    /**
     * @param mixed $str
     * @return bool
     */
    protected static function parseBool($str)
    {
        $str = strtolower(trim((string)$str));

        switch ($str) {
            case 'yes':
            case 'y':
            case 'true':
            case 'on':
            case '1':
                return true;
                break;
            case 'not':
            case 'no':
            case 'n':
            case 'false':
            case 'off':
            case '0':
            case '':
                return false;
                break;
            default:
                return (bool)$str;
        }
    }

    /**
     * @param $block
     * @return string
     */
    protected function loadBlockXmlFile($block)
    {
        $blockName = implode(DIRECTORY_SEPARATOR, array_slice(explode('/', $block), 0, 3));

        foreach ($this->blockPath as $blockPath) {
            $file = $blockPath . DIRECTORY_SEPARATOR . strtolower($blockName) . '.xml';

            if (is_file($file)) {
                return file_get_contents($file);
            }
        }

        return false;
    }

    /**
     * Установить путь где искать блоки
     *
     * @param $path
     * @return $this
     */
    public function setBlockPath($path)
    {
        if (!is_array($path)) {
            $path = array(realpath($path));
        }

        $this->blockPath = array();
        foreach ($path as $_path) {
            $this->addBlockPath($_path);
        }

        return $this;
    }

    /**
     * Добавить путь где искать блоки
     *
     * @param $path
     * @return $this
     */
    public function addBlockPath($path)
    {
        $this->blockPath[] = realpath($path);
        return $this;
    }

    /**
     * @param boolean $allowCache
     * @return $this
     */
    public function setAllowCache($allowCache)
    {
        $this->allowCache = $allowCache;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAllowCache()
    {
        return $this->allowCache;
    }

    /**
     * @param $path
     * @return $this
     * @throws \App\Exception\InvalidArgumentException
     */
    public function setCachePath($path)
    {
        $this->cachePath = realpath($path);

        if (!is_dir($this->cachePath)) {
            throw new InvalidArgumentException('Cache folder is not a folder');
        }

        return $this;
    }

    public function clearCache()
    {
        foreach (glob($this->cachePath . DIRECTORY_SEPARATOR . '*.array') as $file) {
            @unlink($file);
        }

        return $this;
    }

    /**
     * Обернуть контент в html-тег
     *
     * @param string $content
     * @param array $wrapperParamArray - массив параметров
     *
     * @return string
     */
    protected function wrapContentInHtmlTag($content, array $wrapperParamArray)
    {
        $showWrapper = isset($wrapperParamArray['show']) ? $wrapperParamArray['show'] : false;
        $htmlTag     = isset($wrapperParamArray['html_tag']) ? $wrapperParamArray['html_tag'] : 'div';

        if ($showWrapper && $htmlTag) {
            $cssClass         = isset($wrapperParamArray['css_class']) ? $wrapperParamArray['css_class'] : '';
            $modifierCssClass = isset($wrapperParamArray['modifier_css_class']) ? $wrapperParamArray['modifier_css_class'] : '';
            $modifierCssClass = $modifierCssClass ? ' ' . $modifierCssClass : '';
            $cssClassHtml     = $cssClass ? " class=\"{$cssClass}{$modifierCssClass}\"" : '';

            return "<{$htmlTag}{$cssClassHtml}>{$content}</{$htmlTag}>";
        }
        return $content;
    }

    protected  $globalParams = array();
    public function setGlobalParam($name, $value)
    {
        $this->globalParams[\App\Mvc\View::globalParam($name)] = $value;
    }

}