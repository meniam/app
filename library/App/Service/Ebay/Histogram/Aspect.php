<?php

namespace App\Service\Ebay\Histogram;

use Model\EbayApiModel;
use Model\EbayAspectModel;
use Model\EbayAspectValueModel;

class Aspect extends Histogram
{
    private static $cache = array();

    private static $aspectValueCache = array();

    public function __construct($input = null)
    {
        parent::__construct($input, \ArrayObject::ARRAY_AS_PROPS);

        foreach ($this as &$item) {
            if (!isset($item['name'])) {
                continue;
            }

            $aspect = EbayAspectModel::getInstance()->getByName($item['name']);

            foreach ($aspect->toArray() as $k => $v) {
                $item[$k] = $v;
            }

            foreach ($item['values'] as $k => $value) {
                if (isset(self::$cache[$k])) {
                    $item['values'][$k] = self::$cache[$k];
                    continue;
                }


                if (!isset(self::$aspectValueCache[$item['name']][$aspect->getId()])) {
                    $result = EbayAspectValueModel::getInstance()->getByEbayAspectAndValue($item['name'], $aspect->getId());
                    self::$aspectValueCache[$item['name']][$aspect->getId()] = $result;
                } else {
                    $result = self::$aspectValueCache[$item['name']][$aspect->getId()];
                }


                if (!$result->getId()) {
                    $valueItem = EbayAspectValueModel::getInstance()
                                    ->import(array('value' => $item['name'],
                                                   'ebay_aspect_id' => $aspect->getId()));

                    if ($valueItem->getResult()) {
                        $valueItem = EbayAspectValueModel::getInstance()->getById($valueItem->getResult());

                        foreach ($valueItem->toArray() as $k2 => $v2) {
                            $item['values'][$k][$k2] = $v2;
                        }

                        self::$cache[$k] = $item['values'][$k];
                    }
                }
            }
        }
    }

    public function getAsAspectImportArray()
    {
        $result = array();
        foreach ($this as $item) {
            if (!isset($item['name'])) {
                continue;
            }

            $ebayAspect = array(
                'name' => $item['name']
            );

            if (isset($item['values'])) {
                $aspectValues = array();
                foreach ($item['values'] as $value) {
                    $aspectValues[] = array('value' => $value['name']);
                }
                $ebayAspect['_ebay_aspect_value_collection'] = $aspectValues;
            }

            $result[] = $ebayAspect;
        }

        return $result;
    }
}

