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

    public function url($route, $params = array())
    {
        $route = (string)$route;

        if (!isset($this->routeList[$route])) {
            throw new InvalidArgumentException('Route ' . $route . ' not found');
        }

        $defaults = isset($this->routeList[$route]['defaults']) ? $this->routeList[$route]['defaults'] : array();

        $url                   = $this->routeList[$route]['spec'];
        $mergedParams          = array_merge($defaults, $params);

        foreach ($mergedParams as $key => $value) {
            $spec = '%' . $key . '%';

            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, rawurlencode($value), $url);
            }
        }

        $urlPrefix = '';
        if (isset($this->schema) && isset($this->host)) {
            $urlPrefix = $this->schema . '://';
            if (isset($this->subdomain)) {
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