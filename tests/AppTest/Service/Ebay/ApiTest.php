<?php

namespace AppTest\Service\Ebay;

define('EBAY_DEVID', 'd53ec883-5984-478d-b504-7d98e1f53efa');
define('EBAY_APPID', 'EugeneMy-91a9-4b0f-9b9b-5b1e87ea86aa');
define('EBAY_CERTID', '823810cd-12f6-4465-9882-ed4392c5c25e');
define('EBAY_TOKEN',  'AgAAAA**AQAAAA**aAAAAA**P8e1UQ**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6AFlYSlD5mCogWdj6x9nY+seQ**Ct0BAA**AAMAAA**z4EKyBqXJv6Q4YG4yS58WFLn/P94dJ5nChyn8P+WvUyQKFLG8KXJTqqIuDUyb/LtW9+Vm2M/+2SxjbbuvJeyxoAGNHzQF3s/jDRuygtSB0TwpKe07MxjTVT/ZlHg3J19GHZSJhA0I0qVLfXy1jiCgTn/b/8bSXgEh2epLRTWJ7YnsFJNAgydB8lyls6d1lYDbPo757MBiT/NzPharrZpED3uYVQgkC/0B7V+ITW/A0jrgu9hFzGo2X6aoAVViwFY0LKS0FKTCc9tEdSyisltOWaRPEADx/1nW+CN42Lv0kxXaeWB+xmCgE/NLABRTKzHBgGVv0DWR3ZN3GCS2YxN2ggwI43T+1tgbm1faFQ9mjzQ9s8fMd0XU55Ao8oC+C1INSBp8ojtF1htWHZO516STpNaCY3szxbAwNYSViWDQjVT8XN0p92djpL9f//SbwC62cZcm2oz6RcZGYBPo0bXsDTkr7jlrJYgb65fcttq7MFvpMsf3IFYDwm62NYWPylpYVaNAFLgqZfXU2q0wzbGc3FkQsYeQWvRLzdLY+cpJCBSkrJOdgGup1/840Ra70yHZ6vaT4mbY6KDGtzuSg5Bz02reVjcW7cnlk8jH4yX+tyQFmIbbIyvtb3Y8H2lDo9twUgigwFQtP46ttcKwNoAbiJL7MMFfWe8SvG8Y8d63ALy4VZc18o/ec3eVByipdlehi1VUIiketekfEw1vE9E6HHTaPO6i3EC2qID48yVgQU445yRlhzO+Hstnie4XaCm');

use App\Curl\Curl;
use App\Service\Ebay\Api;
use AppTest\TestCase as ParentTestCase;

class ApiTest extends ParentTestCase
{
    /**
     * @var Api
     */
    private $api;

    public function testCreate()
    {
        $this->assertInstanceOf('\App\Service\Ebay\Api', $this->getApi());
    }

    public function testCallSingleItem()
    {
        $result = $this->getApi()->callSingleItem('221333739498');
        print_r($result);
    }

    /**
     * @return Api
     */
    protected function getApi()
    {
        if (!$this->api) {
            Api::setRuName('Eugene_Myazin-EugeneMy-91a9-4-dbwlaua');
            Api::setAppId(EBAY_APPID);
            Api::setDevId(EBAY_DEVID);
            Api::setCertId(EBAY_CERTID);
            Api::setClient(new Curl());

            $this->api = new Api();
        }

        return $this->api;
    }
}

