<?php

define('PROJECT_PATH', dirname(__FILE__) . '/../');

define('FIXTURES_PATH',   realpath(__DIR__ . '/_fixtures'));

require_once PROJECT_PATH . '/vendor/autoload.php';


/*set_include_path(realpath(PROJECT_PATH . '/library')
				 . PATH_SEPARATOR . get_include_path());

require_once 'App/Loader/Autoloader.php';

App\Loader\Autoloader::getInstance()
    ->addRule('App\\',  PROJECT_PATH . '/library', App\Loader\Autoloader::RULE_TYPE_PREFIX)
    ->addRule('AppTest\\',  __DIR__, App\Loader\Autoloader::RULE_TYPE_PREFIX)
    ->addRule('Zend\\', ZENDLIB_PATH, App\Loader\Autoloader::RULE_TYPE_PREFIX);*/