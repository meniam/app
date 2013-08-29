<?php

namespace App\Mvc\UrlBuilder;

use App\Exception\InvalidArgumentException;

class UrlBuilder implements UrlBuilderInterface
{
    private $routeList = array();

    public function __construct(array $routes)
    {
        $this->routeList = $routes;
    }

    public function url($route, $params = array())
    {
        $route = (string)$route;

        if (!isset($this->routeList[$route])) {
            throw new InvalidArgumentException('Route ' . $route . ' not found');
        }
        $defaults = $this->routeList[$route]['defaults'];

        $url                   = $this->routeList[$route]['spec'];
        $mergedParams          = array_merge($defaults, $params);

        foreach ($mergedParams as $key => $value) {
            $spec = '%' . $key . '%';

            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, rawurlencode($value), $url);
            }
        }

        return $url;

    }
}