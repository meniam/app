<?php

namespace App\Mvc;

use App\Exception\InvalidArgumentException;
use App\Filter\Filter;
use Zend\Console\Adapter\AdapterInterface as ConsoleAdapter;
use App\Mvc\Controller\AbstractAction;
use App\ServiceManager\ServiceManager;
use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Getopt;
use Zend\Console\RouteMatcher\DefaultRouteMatcher;
use Zend\Filter\Word\DashToCamelCase;
use Zend\Log\Writer\Stream;
use Zend\Mvc\Service\ConsoleAdapterFactory;
use Zend\ServiceManager\Config;
use App\Http\Request;
use App\Http\Response;
use App\Loader\Autoloader;
use Zend\Text\Table;
use Zend\Stdlib\StringUtils;

class Application implements ApplicationInterface
{
    private $thread;

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

    private $log;

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
     *
     * @return $this
     */
    public function setControllerNamespace($controllerNamespace)
    {
        $this->controllerNamespace = $controllerNamespace;

        return $this;
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
     *
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

    private $circlesCount = 0;

    public function alive($isNewCircle = false)
    {
        if ($filename = $this->getAliveFile()) {
            if ($isNewCircle) {
                $this->circlesCount++;
            }

            $line = \sprintf("%s\t%s\t%s\t%s\t%s",
                    time(),
                    (time() - $GLOBALS['start_time']),
                    \memory_get_peak_usage(),
                    \memory_get_usage(),
                    $this->circlesCount
                );

            file_put_contents($filename, $line);
        }
        return $this;
    }

    protected function getRunDir()
    {
        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        return $runDir;
    }

    protected function getPidFile($thread = 0)
    {
        if (!$this->controllerName || !$this->actionName) {
            return false;
        }

        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        $pidFilename = $this->controllerName . '_' . $this->actionName . '_' . $thread . '.pid';
        $pidFile = $runDir . '/' . $pidFilename;

        return $pidFile;
    }

    protected function getAliveFile()
    {
        if (!$this->controllerName || !$this->actionName) {
            return false;
        }

        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        $this->thread = 0;

        $aliveFilename = $this->controllerName . '_' . $this->actionName . '_' . $this->thread . '.alive';
        $aliveFilename = $runDir . '/' . $aliveFilename;

        return $aliveFilename;
    }

    private $controllerName;
    private $actionName;

    /**
     * Run the application
     *
     * @param null $argv
     *
     * @internal param null $requestUri
     * @return Response
     */
    public function runConsole($argv = null)
    {
        $cliParams = $argv ? $argv : $_SERVER['argv'];
        $cliParams = array_slice($cliParams, 1);

        if (count($cliParams) < 1) {
            return $this->getConsoleUsageFull();
        }

        $log    = $this->config['dir']['log'] . '/console';
        if (!is_dir($log)) {
            @mkdir($log, 0750, true);
        }

        $this->controllerName = $controller = $cliParams[0];
        $this->actionName     = ($action = (isset($cliParams[1]) ? $cliParams[1] : null));
        $system               = isset($cliParams[2]) && in_array($cliParams[2], array('log', 'status')) ? $cliParams[2] : 0;

        if ($this->controllerName == 'status') {
            foreach(glob($this->getRunDir() .'/*.pid') as $file) {

                if (!$this->checkPid($file)) {
                    @unlink($file);
                    continue;
                }

                list($procController, $procAction, $procThread) = explode('_', preg_replace('#\.pid$#', '', basename($file)));

                $lastActionTime = file_get_contents($this->getRunDir() .'/' . $procController. '_' . $procAction . '_' . $procThread . '.alive');

                echo $procController . ' ' . $procAction . ' ' . $lastActionTime . "\n";
            }
            exit();
        }

        if (count($cliParams) < 2) {
            return $this->getConsoleUsageFull();
        }

        $pidFile   = $this->getPidFile();
        $aliveFile = $this->getAliveFile();

        $controller = (new DashToCamelCase())->filter($cliParams[0]);
        $action     = (new DashToCamelCase())->filter($cliParams[1]);

        $controllerClass = '\\Application\\Console\\Controller\\' . $controller . 'Controller';
        $actionName = mb_strtolower(substr($action . 'Action', 0, 1)) . substr($action . 'Action', 1);


        if (method_exists($controllerClass, $actionName)) {
            $usage = $controllerClass::getConsoleUsage();
            $moduleDescription = array_shift($usage);

            $matchParams = array();
            foreach ($usage as $k => $v) {
                if (is_numeric($k) && is_string($v)) {
                    continue;
                }

                $route = null;
                if (!is_numeric($k)) {
                    $route = '(' . $cliParams[0] . ') ' . $k . ' [--verbose] [--force] [--daemon] [--daemon-interval=] [--thread=] [--instances=] [--limit=] [--debug]';
                }

                if (!$route) {
                    continue;
                }

                $routeMatcher = new DefaultRouteMatcher($route);
                if ($matchParams = $routeMatcher->match($cliParams)) {
                    break;
                }
            }

            if (!$matchParams) {
                $result = $this->getConsoleUsageFull();
            } else {
                $this->getRequest()->setParams($matchParams);

                // Номер запускаемого процесса
                $thread = $this->getRequest()->getParam('thread', -1);
                $instances = $this->getRequest()->getParam('instances', 1);

                for ($i=1; $i<=$instances; $i++) {
                    $pidFile = $this->getPidFile($i);

                    if (!($pidCheck = $this->checkPid($pidFile))) {
                        @unlink($pidFile);
                    }

                    if ($thread == -1 && !$pidCheck) {
                        @unlink($pidFile);
                        $thread = $i;
                    } elseif ($thread == $i && $pidCheck) {
                        $this->getLog()
                            ->debug("Thread #{$thread} exists and work properly")
                            ->debug('EXIT');
                        exit();
                    }
                }

                if ($thread == -1) {
                    $this->getLog()
                        ->debug("All threads busy")
                        ->debug('EXIT');
                    exit();
                }

                $this->getRequest()->setParam('thread', $thread);
                $this->getRequest()->setParam('instances', $instances);

                $pidFile = $this->getPidFile($thread);
                @file_put_contents($pidFile, getmypid());
                $this->alive();

                if ($this->getRequest()->getParam('daemon') > 0) {
                    $sleep = max($this->getRequest()->getParam('daemon-interval'), 1000000);
                    while ($sleep) {
                        $result = $this->dispatchControllerAction($controller, $action);

                        $this->alive(true);
                        usleep($sleep);
                    }
                } else {
                    $result = $this->dispatchControllerAction($controller, $action);
                }
            }
        } else {
            $result = $this->getConsoleUsageFull();
        }

        @unlink($pidFile);
        @unlink($aliveFile);

        return $result;
    }


    /**
     * @return \Zend\Log\Logger
     */
    public function getLog()
    {
        if (!$this->log) {
            $request = $this->getRequest();
            $isVerbose = $request->getParam('verbose') == 1;

            $logger = new \Zend\Log\Logger;

            $format = '%timestamp% %priorityName%: %message%';

            $formatter = new \Zend\Log\Formatter\Simple($format, 'Y-m-d H:i:s');

            $logDir = $this->config['dir']['log'] . '/' . $this->controllerName . '/' . $this->actionName . '/' . date('/Y/m/d');
            if (!is_dir($logDir)) {
                mkdir($logDir, 0750, true);
            }

            $logFile = $logDir . date('/Y-m-d') . '.log';

            if ($isVerbose) {
                $writer = (new Stream('php://output'))->setFormatter($formatter);
                $logger->addWriter($writer);
            }

            $writer = (new Stream($logFile))->setFormatter($formatter);
            $logger->addWriter($writer);

            $this->log = $logger;
        }

        return $this->log;
    }

    protected function checkPid($filename)
    {
        if (!is_numeric($filename)) {
            if (is_file($filename)) {
                $pidNum = intval(file_get_contents($filename));
            } else {
                return false;
            }
        } else {
            $pidNum = (int)$filename;
        }

        $cmd = "ps $pidNum";

        // run the system command and assign output to a variable ($output)
        exec($cmd, $output, $result);

        // check the number of lines that were returned
        if(count($output) >= 2){
            // the process is still alive
            return true;
        }

        // the process is dead
        return false;

    }

    protected function getConsoleUsageFull()
    {
        if (!$this->getRequest()->getParam('controller')) {
            $console = Console::getInstance();
            $modules = array('screenshot');

            $usageInfo = array(
                array( '--thread='       , 'current thread'    , 'Current thread number' ),
                array( '--instances=' , 'thread count'      , 'Threads count' ),
                array( '--verbose'       , 'verbose mode'      , 'Display additional information during processing' ),
                array( '--force'         , 'run anyway'        , 'Do not check lock file on start' ),
            );

            $moduleName = sprintf("%s\n%s\n%s\n",
                str_repeat('-', $console->getWidth()),
                ' + Shared options',
                str_repeat('-', $console->getWidth())
            );

            $body = '';
            $body .= $console->colorize($moduleName, ColorInterface::LIGHT_BLUE);
            $body .= $this->getConsoleUsage($console, array($usageInfo), 'console');

            foreach ($modules as $module) {
                $class = '\\Application\\Console\\Controller\\' . Filter::filterStatic($module, 'Zend\Filter\Word\DashToCamelCase') . 'Controller';
                $usage = $class::getConsoleUsage();

                $moduleName = sprintf("%s\n%s\n%s\n",
                    str_repeat('-', $console->getWidth()),
                    '  ' . $module,
                    str_repeat('-', $console->getWidth())
                );

                $body .= $console->colorize($moduleName, ColorInterface::RED);
                $body .= $this->getConsoleUsage($console, array($module => $usage), 'console');
            }

            $this->getResponse()->setBody($body);
            return $this->getResponse();
        }

        return $this->getResponse();
    }

    /**
     * Build Console usage information by querying currently loaded modules.
     *
     * @param ConsoleAdapter $console
     * @param array          $usageInfo
     * @param string         $scriptName
     *
     * @throws \Exception
     * @return string
     */
    protected function getConsoleUsage(
        ConsoleAdapter $console,
        $usageInfo = array(),
        $scriptName
    ) {
        /*
         * Loop through all loaded modules and collect usage info
         */
        //$usageInfo = array();

        /*
         * Handle an application with no usage information
         */
        if (!count($usageInfo)) {
            return '';
        }

        /*
         * Transform arrays in usage info into columns, otherwise join everything together
         */
        $result    = '';
        $table     = false;
        $tableCols = 0;
        $tableType = 0;
        foreach ($usageInfo as $moduleName => $usage) {
            if (!is_string($usage) && !is_array($usage)) {
                throw new \Exception(sprintf(
                    'Cannot understand usage info for module "%s"',
                    $moduleName
                ));
            }

            if (is_string($usage)) {
                // It's a plain string - output as is
                $result .= $usage . "\n";
                continue;
            }

            // It's an array, analyze it
            foreach ($usage as $a => $b) {
                /*
                 * 'invocation method' => 'explanation'
                 */
                if (is_string($a) && is_string($b)) {
                    if (($tableCols !== 2 || $tableType != 1) && $table !== false) {
                        // render last table
                        $result .= $this->renderTable($table, $tableCols, $console->getWidth());
                        $table   = false;

                        // add extra newline for clarity
                        $result .= "\n";
                    }

                    // Colorize the command
                    $a = $console->colorize($scriptName . ' ' . $moduleName . ' ' . $a, ColorInterface::GREEN);

                    $tableCols = 2;
                    $tableType = 1;
                    $table[]   = array($a, $b);
                    continue;
                }

                /*
                 * array('--param', '--explanation')
                 */
                if (is_array($b)) {
                    if ((count($b) != $tableCols || $tableType != 2) && $table !== false) {
                        // render last table
                        $result .= $this->renderTable($table, $tableCols, $console->getWidth());
                        $table   = false;

                        // add extra newline for clarity
                        $result .= "\n";
                    }

                    $tableCols = count($b);
                    $tableType = 2;
                    $table[]   = $b;
                    continue;
                }

                /*
                 * 'A single line of text'
                 */
                if ($table !== false) {
                    // render last table
                    $result .= $this->renderTable($table, $tableCols, $console->getWidth());
                    $table   = false;

                    // add extra newline for clarity
                    $result .= "\n";
                }

                $tableType = 0;
                $result   .= $b . "\n";
            }
        }

        // Finish last table
        if ($table !== false) {
            $result .= $this->renderTable($table, $tableCols, $console->getWidth());
        }

        return $result;
    }


    /**
     * Render a text table containing the data provided, that will fit inside console window's width.
     *
     * @param  $data
     * @param  $cols
     * @param  $consoleWidth
     * @return string
     */
    protected function renderTable($data, $cols, $consoleWidth)
    {
        $result  = '';
        $padding = 2;


        // If there is only 1 column, just concatenate it
        if ($cols == 1) {
            foreach ($data as $row) {
                $result .= $row[0] . "\n";
            }
            return $result;
        }

        // Get the string wrapper supporting UTF-8 character encoding
        $strWrapper = StringUtils::getWrapper('UTF-8');

        // Determine max width for each column
        $maxW = array();
        for ($x = 1; $x <= $cols; $x += 1) {
            $maxW[$x] = 0;
            foreach ($data as $row) {
                $maxW[$x] = max($maxW[$x], $strWrapper->strlen($row[$x-1]) + $padding * 2);
            }
        }

        /*
         * Check if the sum of x-1 columns fit inside console window width - 10
         * chars. If columns do not fit inside console window, then we'll just
         * concatenate them and output as is.
         */
        $width = 0;
        for ($x = 1; $x < $cols; $x += 1) {
            $width += $maxW[$x];
        }

        if ($width >= $consoleWidth - 10) {
            foreach ($data as $row) {
                $result .= implode("    ", $row) . "\n";
            }
            return $result;
        }

        /*
         * Use Zend\Text\Table to render the table.
         * The last column will use the remaining space in console window
         * (minus 1 character to prevent double wrapping at the edge of the
         * screen).
         */
        $maxW[$cols] = $consoleWidth - $width -1;
        $table       = new Table\Table();
        $table->setColumnWidths($maxW);
        $table->setDecorator(new Table\Decorator\Blank());
        $table->setPadding(2);

        foreach ($data as $row) {
            $table->appendRow($row);
        }

        return $table->render();
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
        $subdomain = $this->getRequest()->getSubdomain('_root');

        if (isset($routes[$subdomain])) {
            $routes = $routes[$subdomain];
        } else {
            return false;
        }

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

    private $controllerRegistry;

    public function controller($controller)
    {
        if (!isset($this->controllerRegistry[$controller])) {
            $controllerClass = $this->controllerNamespace . implode('', array_map('ucfirst', explode('-', $controller))) . 'Controller';
            $this->controllerRegistry[$controller] = new $controllerClass($this->getRequest(), $this->getResponse());
        }

        return $this->controllerRegistry[$controller];
    }

    /**
     * Диспетчеризация для Controller Action блока
     *
     * @param $controllerParam
     * @param $actionParam
     * @internal param \App\Mvc\Block $block
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
            $controller = $this->controller($controllerParam);
            // = new $controllerClass($request, $response)

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