<?php

namespace App\Console;

use App\Console\Controller\AbstractAction;
use App\Filter\Filter;
use Zend\ServiceManager\Config;
use App\ServiceManager\ServiceManager;
use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Filter\Word\DashToCamelCase;
use Zend\Log\Formatter\Simple;
use Zend\Log\Logger;
use Zend\Stdlib\StringUtils;
use Zend\Text\Table;
use Zend\Console\RouteMatcher\DefaultRouteMatcher;
use Zend\Console\Adapter\AdapterInterface as ConsoleAdapter;
use Zend\Log\Writer\Stream;
use App\Http\Response;

class Application extends \App\Mvc\Application
{
    /**
     * Thread number
     *
     * @var integer|null
     */
    private $thread;

    /**
     * Run count in daemon mode
     *
     * @var integer
     */
    private $circlesCount = 0;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @param null $argv
     *
     * @return array|null
     */
    private function prepareCliParams($argv = null)
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

        $controller = $cliParams[0];
        $action = (isset($cliParams[1]) ? $cliParams[1] : null);

        $this->setControllerName($controller);
        $this->setActionName($action);

        return $cliParams;
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

            //$controllerClass = $this->getControllerNamespace() . implode('', array_map('ucfirst', explode('-', $controllerParam))) . 'Controller';
            $actionMethod    = implode('', array_map('ucfirst', explode('-', $actionParam)))     . 'Action';
            $actionMethod    = strtolower(substr($actionMethod, 0, 1)) . substr($actionMethod, 1);

            $response = $this->getResponse();
            /** @var $controller AbstractAction */
            $controller = $this->controller($controllerParam);

            $classMethods = get_class_methods($controller);

            if (in_array('preDispatch', $classMethods)) {
                $controller->preDispatch();
            }

//            if (!$controller->getBreakRun()) {
                $actionResponse = $controller->$actionMethod();

            if (in_array('postDispatch', $classMethods)) {
                $controller->postDispatch($actionResponse);
            }
//            }
            /*
            if (!empty($forward)) {
                $request->setParams($forward);
                $controller->removeForward();
                return $this->dispatch($request, $response);
            }*/
            return $response;
        } catch (\Exception $e) {
            $response = $this->getResponse();
            $response->setException($e);
        }


        return $response;
        //return false;
    }


    /**
     * Run the application
     *
     * @param null $argv
     *
     * @internal param null $requestUri
     * @return Response
     */
    public function run($argv = null)
    {
        $cliParams = $this->prepareCliParams($argv);

        if ($this->getControllerName() == 'system') {
            return $this->systemController($this->getActionName());
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

        $result = null;
        if (method_exists($controllerClass, $actionName)) {
            if (method_exists($controllerClass, 'getConsoleUsage')) {
                $usage = $controllerClass::getConsoleUsage();
            } else {
                $usage = array(null);
            }
            array_shift($usage);

            $matchParams = array();
            foreach ($usage as $k => $v) {
                // skip comment lines in array
                if (is_numeric($k)) {
                    continue;
                }

                $route = '(' . $cliParams[0] . ') ' . $k . ' [--limit=] [--verbose] [--force] [--debug] '
                       . ' [--daemon] [--daemon-interval=] '
                       . ' [--thread=] [--instances=]';

                if ($matchParams = (new DefaultRouteMatcher($route))->match($cliParams)) {
                    break;
                }
            }

            if (!$matchParams) {
                $result = $this->getConsoleUsageFull();
            } else {
                $this->getRequest()->setParams($matchParams);

                // Номер запускаемого процесса
                $thread    = $this->getRequest()->getParam('thread', -1);
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
     * @param null $action
     *
     * @return null
     */
    protected function systemController($action = null)
    {
        switch ($action) {
            case 'status';
                return $this->statusAction();
                break;
        }

        return null;
    }

    protected function statusAction()
    {
        $console = Console::getInstance();

        $outputExists = false;
        foreach(glob($this->getRunDir() .'/*.pid') as $file) {
            if (!$this->checkPid($file)) {
                @unlink($file);
                continue;
            }

            list($procController, $procAction, $procThread) = explode('_', preg_replace('#\.pid$#', '', basename($file)));

            $aliveFile = $this->getRunDir() .'/' . $procController. '_' . $procAction . '_' . $procThread . '.alive';
            $lastActionTime = file_get_contents($aliveFile);

            echo $procController . ' ' . $procAction . ' ' . $lastActionTime . "\n";
            $outputExists = true;
        }

        if (!$outputExists) {
            $console->writeLine('Status information is empty', ColorInterface::RED);
        }

        return null;
    }

    /**
     * @return \Zend\Log\Logger
     */
    public function getLog()
    {
        if (!$this->log) {
            $request = $this->getRequest();
            $isVerbose = $request->getParam('verbose') == 1;

            $logger = new Logger();
            $format = '%timestamp% %priorityName%: %message%';
            $formatter = new Simple($format, 'Y-m-d H:i:s');

            $logDir = $this->config['dir']['log'] . '/' . $this->getControllerName() . '/' . $this->getActionName() . '/' . date('/Y/m/d');
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

    /**
     * @return Response
     * @throws \Exception
     */
    public function getConsoleUsageFull()
    {
        if (!$this->getRequest()->getParam('controller')) {
            $console = Console::getInstance();
            $modules = array('screenshot');

            $usageInfo = array(
                array( '--thread=<int>'          , 'current thread'    , 'Current thread number' ),
                array( '--instances=<int>'       , 'thread count'      , "Maximumm threads count\n" ),

                array( '--daemon'                , 'run as daemon'     , 'Run script in daemon mode' ),
                array( '--daemon-interval=<int>' , 'interval beetwen cycles'     , "Interval in microseconds between run\nDefault: 1000000 (1 second)\n" ),

                array( '--verbose'               , 'verbose mode'      , 'Display additional information during processing' ),
                array( '--debug'                 , 'debug mode'        , "Show debug information in logs\n" ),

                array( '--force'                 , 'run anyway'        , 'Do not check lock file on start' ),
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
                if (method_exists($class, 'getConsoleUsage')) {
                    $usage = $class::getConsoleUsage();
                } else {
                    $usage = array(null);
                }

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
     * @return string
     */
    protected function getRunDir()
    {
        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        return $runDir;
    }


    /**
     * @param bool $isNewCircle
     *
     * @return $this
     */
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

    /**
     * @param int $thread
     *
     * @return string
     */
    protected function getPidFile($thread = 0)
    {
        if (!$this->getControllerName() || !$this->getActionName()) {
            return false;
        }

        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        $pidFilename = $this->getControllerName() . '_' . $this->getActionName() . '_' . $thread . '.pid';
        $pidFile = $runDir . '/' . $pidFilename;

        return $pidFile;
    }

    protected function stopByThread($thread = 0)
    {
        if (!$this->getControllerName() || !$this->getActionName()) {
            return false;
        }

        if ($this->checkPid($this->getPidFile($thread))) {
            $stopFilename = $this->getControllerName() . '_' . $this->getActionName() . '_' . $thread . '.stop';
            $stopFilename = $this->getRunDir() . DIRECTORY_SEPARATOR . $stopFilename;
            touch($stopFilename);
        }

        return true;
    }

    /**
     * @param $filename
     *
     * @return bool
     */
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


    /**
     * @return string
     */
    protected function getAliveFile()
    {
        if (!$this->getControllerName() || !$this->getActionName()) {
            return false;
        }

        $runDir = $this->config['dir']['run'] . '/console';
        if (!is_dir($runDir)) {
            @mkdir($runDir, 0750, true);
        }

        $this->thread = 0;

        $aliveFilename = $this->getControllerName() . '_' . $this->getActionName() . '_' . $this->thread . '.alive';
        $aliveFilename = $runDir . '/' . $aliveFilename;

        return $aliveFilename;
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
     * Build Console usage information by querying currently loaded modules.
     *
     * @param ConsoleAdapter $console
     * @param array          $usageInfo
     * @param string         $scriptName
     *
     * @throws \Exception
     * @return string
     */
    protected function getConsoleUsage(ConsoleAdapter $console, $usageInfo = array(), $scriptName)
    {
        if (!count($usageInfo)) {
            return '';
        }

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
                $result .= $usage . PHP_EOL;
                continue;
            }

            foreach ($usage as $a => $b) {
                if (is_string($a) && is_string($b)) {
                    if (($tableCols !== 2 || $tableType != 1) && $table !== false) {
                        // render last table
                        $result .= $this->renderTable($table, $tableCols, $console->getWidth()) . PHP_EOL;
                        $table   = false;
                    }

                    // Colorize the command
                    $a = $console->colorize($scriptName . ' ' . $moduleName . ' ' . $a, ColorInterface::GREEN);

                    $tableCols = 2;
                    $tableType = 1;
                    $table[]   = array($a, $b);
                    continue;
                }

                if (is_array($b)) {
                    if ((count($b) != $tableCols || $tableType != 2) && $table !== false) {
                        $result .= $this->renderTable($table, $tableCols, $console->getWidth()) . PHP_EOL;
                        $table   = false;
                    }

                    $tableCols = count($b);
                    $tableType = 2;
                    $table[]   = $b;
                    continue;
                }

                if ($table !== false) {
                    $result .= $this->renderTable($table, $tableCols, $console->getWidth()) . PHP_EOL;
                    $table   = false;
                }

                $tableType = 0;
                $result   .= $b . PHP_EOL;
            }
        }

        if ($table !== false) {
            $result .= $this->renderTable($table, $tableCols, $console->getWidth());
        }

        return $result;
    }
}