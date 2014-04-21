<?php

namespace App\Mvc\SaveGet;

use App\Http\Request;

class SaveGet
{
    private $request = null;
    private $uri = '';

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function url($replace = array(), $delete = array(), $link = null)
    {
        if (!$link) {
            $link = $this->getUri();
        }

        if ($replace) {
            foreach ($replace as $k => $v) {
                $k = strval($k);
                $v = urlencode(strval($v));
                if (preg_match('/([&\?])' . $k . "=[^&]*/i", $link)) {
                    $link = preg_replace('/([&\?])' . $k . "=[^&]*/i", "\\1" . $k . "=" . $v, $link);
                } else {
                    $link .= "&" . $k . "=" . $v;
                }
            }
        }

        if ($delete) {
            foreach ($delete as $k) {
                $k    = strval($k);
                $link = preg_replace('/([&\?])' . preg_quote($k) . "=[^&]*[&]?/i", "\\1", $link);
            }
            if (substr($link, -1, 1) == '&') {
                $link = substr($link, 0, -1);
            }
        }

        if (strpos($link, '?') === false && strpos($link, '&') !== false) {
            $ampPos = strpos($link, '&');
            $link   = substr_replace($link, '?', $ampPos, 1);
        }

        $link = rtrim($link, "?");

        if (strpos($link, '?') !== false && (strpos($link, '%2f') !== false || strpos($link, '%2F') !== false)) {
            list($_link, $_query) = explode('?', $link);
            $link = str_replace(array('%2f', '%2F'), '/', $_link) . '?' . $_query;
        } elseif (strpos($link, '%2f') !== false || strpos($link, '%2F') !== false) {
            $link = str_replace(array('%2f', '%2F'), '/', $link);
        }

        return $link;
    }

    /**
     * @return mixed
     */
    private function getUri()
    {
        if (!$this->uri) {
            $this->uri = $this->request->getRequestUri();
        }

        return $this->uri;
    }
}