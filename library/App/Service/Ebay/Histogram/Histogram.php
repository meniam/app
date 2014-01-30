<?php

namespace App\Service\Ebay\Histogram;


class Histogram extends \ArrayObject
{

    static public function factory(array $data = array())
    {
        $key = key($data);
        if ($key == 'conditionHistogram') {
            return self::createConditionHistogram($data['conditionHistogram']);
        } elseif (isset($data['aspect'])) {
            return self::createAspectHistogram($data['aspect']);
        }
    }

    static public function createConditionHistogram(array $data = array())
    {
        $result = array();
        foreach ($data as $item) {
            if (isset($item['count'][0]) && isset($item['condition'][0]['conditionId'][0]) && isset($item['condition'][0]['conditionDisplayName'][0])) {
                $resultItem = array(
                    'count' => isset($item['count'][0]) ? (int)$item['count'][0] : 0,
                    'id' => $item['condition'][0]['conditionId'][0],
                    'name' => $item['condition'][0]['conditionDisplayName'][0],
                );

                $result[$resultItem['id']] = $resultItem;
            }
        }

        return new Condition($result);
    }

    static public function createAspectHistogram(array $data = array())
    {
        $result = array();
        foreach ($data as $item) {
            if (isset($item['@name']) && isset($item['valueHistogram'])) {
                $resultItem = array(
                    'name' => $item['@name'],
                );

                $resultValues = array();
                foreach ($item['valueHistogram'] as $value) {
                    $resultValues[$value['@valueName']] = array(
                        'name' => $value['@valueName'],
                        'count' => $value['count'][0],
                    );
                }

                $resultItem['values'] = $resultValues;
                $result[$resultItem['name']] = $resultItem;
            }
        }

        return new Aspect($result);
    }

    public function __construct($input = null, $flags = 0, $iterator_class = "ArrayIterator")
    {
        if (!$flags) {
            $flags = \ArrayObject::ARRAY_AS_PROPS;
        }

        parent::__construct($input, $flags, $iterator_class);
    }


}