<?php

namespace App\Mvc\UrlBuilder;

use App\Exception\InvalidArgumentException;

class UrlBuilder implements UrlBuilderInterface
{
    private $routeList = array();
    private $host = null;
    private $subdomain = null;
    private $schema = null;

    public function __construct(array $routes, $host = null, $subdomain = null, $schema = 'http')
    {
        $this->routeList = $routes;
        $this->subdomain = (string)$subdomain;
        $this->host = preg_replace('#^' . $subdomain .'\.#si', '', (string)$host);
        $this->schema = (string)$schema;
    }

    public function url($route, $params = array(), $domain = '_root')
    {
        $route = (string)$route;

        if (!isset($this->routeList[$domain][$route])) {
            throw new InvalidArgumentException('Route ' . $route . ' not found');
        }


        $defaults = isset($this->routeList[$domain][$route]['defaults']) ? $this->routeList[$domain][$route]['defaults'] : array();

        $url                   = $this->routeList[$domain][$route]['spec'];
        $mergedParams          = array_merge($defaults, $params);

        foreach ($mergedParams as $key => $value) {
            if ($key[0] == '!') {
                $key = substr($key, 1);
                $noEscape = true;
            } else {
                $noEscape = false;
            }

            $spec = '%' . $key . '%';

            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, ($noEscape ? $value : rawurlencode($value)), $url);
            }
        }

        $urlPrefix = '';
        if (isset($this->schema) && isset($this->host)) {
            $urlPrefix = $this->schema . '://';
            if (!empty($this->subdomain)) {
                $urlPrefix .= $this->subdomain . '.';
            }
            $urlPrefix .= $this->host;
        }

        if ($urlPrefix && $url == '/') {
            return $urlPrefix;
        }

        return $urlPrefix . $url;
    }
}