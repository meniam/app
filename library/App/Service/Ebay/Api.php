<?php

namespace App\Service\Ebay;

use App\Service\Ebay\Exception\RuntimeException;
use \Zend\Cache\Storage\StorageInterface as Cache;
use SimpleXMLElement;

class Api
{
    const EBAY_API_URL                  = 'https://api.ebay.com/ws/api.dll';
    const EBAY_SANDBOX_API_URL          = 'https://api.sandbox.ebay.com/ws/api.dll';

    const EBAY_SINGIN_URL               = 'https://signin.ebay.com/ws/eBayISAPI.dll';
    const EBAY_SANDBOX_SINGIN_URL       = 'https://signin.sandbox.ebay.com/ws/eBayISAPI.dll';

    const EBAY_SEND_MESSAGE_URL         = 'http://contact.ebay.com/ws/eBayISAPI.dll?ContactUserNextGen';
    const EBAY_SANDBOX_SEND_MESSAGE_URL = 'http://contact.sandbox.ebay.com/ws/eBayISAPI.dll?ContactUserNextGen';

    const EBAY_FINDING_URL              = 'http://svcs.ebay.com/services/search/FindingService/v1?';

    protected static $instance = null;

    /**
     * @var \App\Curl\Curl
     */
    private static $client = null;

    /**
     * @var string
     */
    private static $devId;

    /**
     * @var string
     */
    private static $appId;

    /**
     * @var string
     */
    private static $certId;

    /**
     * @var string
     */
    private static $ruName;

    /**
     * Включен режим песочницы?
     *
     * @var bool
     */
    private $isSandboxMode = false;

    /**
     * Адаптер кеширования
     *
     * @var
     */
    private static $cacheAdapter;

    /**
     * @param Cache $cacheAdapter
     */
    public static function setCacheAdapter($cacheAdapter)
    {
        self::$cacheAdapter = $cacheAdapter;
    }

    /**
     * @return Cache
     */
    public static function getCacheAdapter()
    {
        return self::$cacheAdapter;
    }

    public function __construct()
    {
        if (!self::$devId) {
            throw new RuntimeException('DevId wasn\'t set');
        }
        if (!self::$appId) {
            throw new RuntimeException('AppId wasn\'t set');
        }
        if (!self::$certId) {
            throw new RuntimeException('CertId wasn\'t set');
        }
        if (!self::$client) {
            throw new RuntimeException('Api client wasn\'t set');
        }
    }

    /*
    private function buildItemFilters($itemFilters)
    {
        $k = 0;
        if (!$itemFilters) {
            return;
        }
        $result = array();
        foreach ($itemFilters as $name=>$values) {
            $prefix = 'itemFilter(' . $k . ').';
            $result[$prefix . 'name'] = urlencode($name);
            $j = 0;
            if (is_array($values)) {
                foreach ($values as $value) {
                    $result[$prefix . 'value(' . $j . ')'] = urlencode($value);
                    $j++;
                }
            } else {
                $result[$prefix . 'value'] = urlencode($values);
            }
            $k++;
        }

        return $result;
    }*/

    /**
     * @param $filterarray
     *
     * @return string
     */
    private function buildURLArray ($filterarray)
    {
        $urlfilter = '';
        $i = 0;
        // Iterate through each filter in the array
        foreach($filterarray as $itemfilter) {
            // Iterate through each key in the filter
            foreach ($itemfilter as $key =>$value) {
                if(is_array($value)) {
                    foreach($value as $j => $content) { // Index the key for each value
                        $urlfilter .= "&itemFilter($i).$key($j)=$content";
                    }
                }
                else {
                    if($value != "") {
                        $urlfilter .= "&itemFilter($i).$key=$value";
                    }
                }
            }
            $i++;
        }

        return $urlfilter;
    }

    public function getList(ListCond $listCond)
    {
        $filter = $listCond->getParams();
        $params = array(
            'outputSelector' => array(//'ConditionHistogram',
                                      //'CategoryHistogram',
                                      'AspectHistogram',
                                      //'PictureURLSuperSize',
                                      //'SellerInfo',
                                      //'UnitPriceInfo'
            ),
            'keywords' =>   $listCond->getKeyword(),
            'paginationInput.pageNumber' =>     1,
            'paginationInput.entriesPerPage' => 25
        );

        return $this->callFinding('findItemsAdvanced', $params, $filter);
    }


    public function callShippingCosts($id)
    {
        $url = "http://open.api.ebay.com/shopping?callname=GetShippingCosts&responseencoding=JSON";
        $url .= "&appid=" .  self::$appId;
        $url .= "&siteid=0";
        $url .= "&version=683";
        $url .= "&ItemID=" . $id;
        $url .= "&DestinationCountryCode=US";
        $url .= "&DestinationPostalCode=02035-1072";
        $url .= "&IncludeDetails=true";
        $url .= "&QuantitySold=1";

        $cacheId = 'ebay_api_url_' . sha1($url);

        if (!$response = $this->loadFromCache($cacheId)) {
            $response = self::getClient()->get($url)->getBody();

            $this->saveToCache($cacheId, $response, 3600);
        }

        $result = json_decode($response, true);
        return (array)$result;
    }


    public function callSingleItem($id)
    {
        $url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=JSON";
        $url .= "&appid=" .  self::$appId;
        $url .= "&siteid=0";
        $url .= "&version=683";
        $url .= "&ItemID=" . $id;
        $url .= "&IncludeSelector=Description,Details,SearchDetails,ItemSpecifics,CategoryHistogram,ShippingCosts,Variations";

        $cacheId = 'ebay_api_url_' . sha1($url);

        if (!$response = $this->loadFromCache($cacheId)) {
            $response = self::getClient()->get($url)->getBody();

            $this->saveToCache($cacheId, $response);
        }

        $result = json_decode($response, true);
        return (array)$result;
    }

    public function callFinding($method, array $params = array(), $filter = array())
    {
        $url = self::EBAY_FINDING_URL . 'OPERATION-NAME=' . $method;
        $url .= '&SERVICE-VERSION=1.0.0';
        $url .= '&SECURITY-APPNAME=' . self::$appId;
        $url .= '&RESPONSE-DATA-FORMAT=JSON&REST-PAYLOAD';
        $url .= '&X-EBAY-SOA-GLOBAL-ID=EBAY-US';

        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $i = 0;
                foreach ($v as $val) {
                    $url .= '&' . $k . '(' . $i++ .')=' . urlencode($val);
                }
            } else {
                $url .= '&' . $k . '=' . urlencode($v);
            }
        }


        $url .= $this->buildURLArray($filter);
        $cacheId = 'ebay_api_url_' . sha1($url);
        if (!$response = $this->loadFromCache($cacheId)) {
            $response = self::getClient()->get($url)->getBody();
            $this->saveToCache($cacheId, $response, 3600);
        }

        $result = json_decode($response, true);
        return (array)$result;
    }

    public function call($method, array $params = array())
    {
        $headers = array (
            'X-EBAY-API-COMPATIBILITY-LEVEL: ' . 1000,
            'X-EBAY-API-DEV-NAME: ' . self::$devId,
            'X-EBAY-API-APP-NAME: ' . self::$appId,
            'X-EBAY-API-CERT-NAME: ' . self::$certId,
            'X-EBAY-API-CALL-NAME: ' . $method,
            'X-EBAY-API-SITEID: ' . 0,
            'X-EBAY-API-SITE-ID: ' . 0
        );

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>' . "<{$method}Request/>");
        $xml->addAttribute('xmlns', 'urn:ebay:apis:eBLBaseComponents');
        self::arrayToXml($params, $xml);

        self::getClient()->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        self::getClient()->setOpt(CURLOPT_RETURNTRANSFER, 1);
        self::getClient()->setOpt(CURLOPT_HTTPHEADER, $headers);

        $response = self::getClient()->post($this->getApiUrl(), $xml->asXML());
        return self::xmlToArray($response->getBody());
    }


    public function getItem2($itemId)
    {
        return $this->call('GetItem', array('ItemID' => $itemId));
    }

    /**
     * Получить sessionID
     *
     * @return array
     */
    public function getSessionId()
    {
        return $this->call('GetSessionID', array('RuName' => self::$ruName));
    }

    /**
     * Получить url для получения token
     *
     * @param string $sessionId
     * @param array  $params
     *
     * @return string
     */
    public function getTokenUrl($sessionId, $params = array())
    {
        $url = $this->getSinginUrl() . '?SignIn&RuName=' . urlencode(self::$ruName) . '&SessID=' . urlencode($sessionId);
        if (!empty($params)) {
            $url .= '&ruparams=';
            foreach ($params as $key => $value) {
                $url .= urlencode($key . '=' . $value . '&');
            }
        }
        return $url;
    }

    /**
     * Получить token пользователя
     *
     * @param null|string $sessionId
     *
     * @return array
     */
    public function fetchToken($sessionId)
    {
        return $this->call('FetchToken', array('SessionID' => $sessionId));
    }

    /**
     * Получить список товаров продавца (без детализации)
     *
     * @param string - $token token продавца
     * @param array $params - параметры для request
     *
     * @return array
     */
    public function getSellerList($token, array $params = array())
    {
        $fromDate = date('Y-m-d\TH:i:s.', strtotime('-1 Month')) . '005Z';
        $toDate   = date('Y-m-d\TH:i:s.', strtotime('+1 Month')) . '005Z';

        $defaultParams = array(
            'StartTimeFrom'        => $fromDate,
            'StartTimeTo'          => $toDate,
            'DetailLevel'          => 'ReturnAll',
            'Pagination'           => array(
                'EntriesPerPage' => 10,
                'PageNumber'     => 1
            )
        );

        $request = array_merge($defaultParams, $params);
        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);

        return $this->call('GetSellerList', $request);
    }

    /**
     * Получить список продаваемых товаров продавца (с максимальной детализацией)
     *
     * @param string - $token token продавца
     * @param array $params - параметры для request
     *
     * @return array
     */
    public function getMyeBaySelling($token, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
        );

        $request = array_merge($defaultParams, $params);

        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);

        return $this->call('GetMyeBaySelling', $request);
    }

    /**
     * Получить инфу о пользователе
     *
     * @param string - $token token продавца
     * @param array $params - параметры для request
     * @see http://developer.ebay.com/DevZone/xml/docs/Reference/ebay/GetUser.html
     * @return array
     */
    public function getUser($token, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
        );

        $request = array_merge($defaultParams, $params);

        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);

        return $this->call('GetUser', $request);
    }

    /**
     * Получить список транзакций проданного товара продавца (по-умолчанию, с максимальной детализацией)
     *
     * @param $token - токен
     * @param $itemId - id товара
     * @param array $params - параметры для request
     *
     * @return array
     */
    public function getItemTransactions($token, $itemId, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
        );

        $request = array_merge($defaultParams, $params);

        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);
        $request['ItemID']               = (string) $itemId;

        return $this->call('GetItemTransactions', $request);
    }

    /**
     *  Получить информацию по товару продавца (по-умолчанию, с максимальной детализацией)
     *
     * @param $token - токен
     * @param $itemId - id товара
     * @param array $params - параметры для request
     *
     * @return array
     */
    public function getItem($token, $itemId, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
        );

        $request = array_merge($defaultParams, $params);

        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);
        $request['ItemID']               = (string) $itemId;

        return $this->call('GetItem', $request);
    }

    public function getCategories($token, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
        );

        $request = array_merge($defaultParams, $params);
        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);

        return $this->call('GetCategories', $request);
    }


    /*public function getCategoryListByParent($parentId, array $params = array())
    {
        $defaultParams = array(
            'DetailLevel' => 'ReturnAll',
            'CategoryParent' => $parentId
        );

        $request = array_merge($defaultParams, $params);
        $request['RequesterCredentials'] = array('eBayAuthToken' => EBAY_TOKEN);

        return $this->call('GetCategories', $request);
    }*/

    /**
     * Отправить сообщение от продавца покупателю
     *
     * @param       $token - токен
     * @param       $itemId - id товара
     * @param       $message - текст сообщения
     * @param       $buyer - получатель
     * @param array $params - параметры для request
     * @return array
    public function addMessageToBuyer($token, $itemId, $message, $buyer, array $params = array())
    {
        $defaultParams = array();

        $request = array_merge($defaultParams, $params);

        $request['RequesterCredentials'] = array('eBayAuthToken' => $token);
        $request['ItemID']               = (string) $itemId;

        $request['MemberMessage'] = array_merge($request['MemberMessage'], array(
            //'Subject'           => 'This member has a question for you',
            'Body'              => $message,
            'QuestionType'      => 'General', //CustomizedSubject
            'RecipientID'       => $buyer
        ));

        return $this->call('AddMemberMessageAAQToPartner', $request);
    }
     */

    /**
     * @param \App\Curl\Curl $client
     */
    public static function setClient($client)
    {
        self::$client = $client;
    }

    /**
     * @return \App\Curl\Curl
     */
    public static function getClient()
    {
        return self::$client;
    }

    /**
     * Включить режим песочницы
     *
     * @param bool $isSandboxMode
     */
    public function setSandboxMode($isSandboxMode)
    {
        $this->isSandboxMode = (bool) $isSandboxMode;
    }

    /**
     * Влючен ли режим песочницы?
     *
     * @return bool
     */
    public function isSandboxMode()
    {
        return (bool) $this->isSandboxMode;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->isSandboxMode() ? self::EBAY_SANDBOX_API_URL : self::EBAY_API_URL;
    }

    /**
     * @return string
     */
    public function getSinginUrl()
    {
        return $this->isSandboxMode() ? self::EBAY_SANDBOX_SINGIN_URL : self::EBAY_SINGIN_URL;
    }

    /**
     * @return string
     */
    public function getSendMessageUrl()
    {
        return $this->isSandboxMode() ? self::EBAY_SANDBOX_SEND_MESSAGE_URL : self::EBAY_SEND_MESSAGE_URL;
    }

    /**
     * Получить xml из масива
     *
     * @param array $array
     * @param \SimpleXMLElement $xml
     */
    public static function arrayToXml($array, &$xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild("$key");
                    self::arrayToXml($value, $subnode);
                } else {
                    self::arrayToXml($value, $xml);
                }
            } else {
                $xml->addChild("$key", "$value");
            }
        }
    }

    /**
     * Получить
     *
     * @param string|SimpleXMLElement $xml
     *
     * @return array
     */
    public static function xmlToArray($xml)
    {
        if ($xml instanceof SimpleXMLElement) {
            $xml = $xml->asXML();
        }

        return json_decode(json_encode((array) simplexml_load_string($xml)), true);
    }

    /**
     * @param string $appId
     */
    public static function setAppId($appId)
    {
        self::$appId = $appId;
    }

    /**
     * @param string $certId
     */
    public static function setCertId($certId)
    {
        self::$certId = $certId;
    }

    /**
     * @param string $devId
     */
    public static function setDevId($devId)
    {
        self::$devId = $devId;
    }

    /**
     * @param string $ruName
     */
    public static function setRuName($ruName)
    {
        self::$ruName = $ruName;
    }

    /**
     * Получить класс для доступа к Ebay API
     *
     * @return $this
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            $className = get_called_class();
            self::$instance = new $className();
        }

        return self::$instance;
    }

    /**
     * @param $cacheId
     *
     * @return mixed|null
     */
    protected function loadFromCache($cacheId)
    {
        if (!$this->getCacheAdapter()) {
            return null;
        }

        return $this->getCacheAdapter()->getItem($cacheId);
    }

    protected function saveToCache($cacheId, $data)
    {
        if (!$this->getCacheAdapter()) {
            return null;
        }

        return $this->getCacheAdapter()->addItem($cacheId, $data);
    }
}