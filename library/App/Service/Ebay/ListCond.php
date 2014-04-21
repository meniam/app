<?php

namespace App\Service\Ebay;


class ListCond
{
    const LISTING_TYPE_ALL = 'All';
    const LISTING_TYPE_AUCTION = 'Auction';
    const LISTING_TYPE_FIXED_PRICE = 'FixedPrice';

    const CONDITION_ALL = 'All';
    const CONDITION_NEW = 'New';
    const CONDITION_USED = 'Used';

    const SORT_ORDER_BEST_MATCH = 'BestMatch';

    private $page = 1;
    private $itemsPerPage = 50;
    private $category = array();
    private $keyword  = '';

    private $params = array(
        'ListingType' => array(
            'name'       => 'ListingType',
            'value'      => self::LISTING_TYPE_ALL,
            'paramName'  => '',
            'paramValue' => '',
        ),
        /*'Condition' => array(
            'name'       => 'Condition',
            'value'      => self::CONDITION_ALL,
            'paramName'  => '',
            'paramValue' => '',
        ),*/
        'HideDuplicateItems' => array(
            'name'       => 'HideDuplicateItems',
            'value'      => 'true',
            'paramName'  => '',
            'paramValue' => '',
        ),
        'SortOrder' => array(
            'name'       => 'SortOrder',
            'value'      => self::SORT_ORDER_BEST_MATCH,
            'paramName'  => '',
            'paramValue' => '',
        ),
    );

    static public function init()
    {
        return new self();
    }

    /**
     * @param string $listingType
     *
     * @return $this
     */
    public function setListingType($listingType = self::LISTING_TYPE_ALL)
    {
        $this->setParam('ListingType', $listingType);
        return $this;
    }

    /**
     * @param boolean $freeShippingOnly
     * @return $this
     */
    public function setFreeShippingOnly($freeShippingOnly)
    {
        $this->setParam('FreeShippingOnly', (bool) $freeShippingOnly ? 'true' : 'false');
        return $this;
    }

    /**
     * @param mixed $condition
     * @return $this
     */
    public function setCondition($condition)
    {
        $this->setParam('Condition', $condition);
        return $this;
    }

    /**
     * @param        $name
     * @param        $value
     * @param string $paramName
     * @param string $paramValue
     * @return $this
     */
    protected function setParam($name, $value, $paramName = '', $paramValue = '')
    {
        $this->params[(string)$name] = array (
            'name' => (string) $name,
            'value' => $value,
            'paramName' => (string) $paramName,
            'paramValue' => $paramValue
        );

        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return array_values($this->params);
    }

    /**
     * @param int $page
     *
     * @return $this
     */
    public function setPage($page, $itemsPerPage = 50)
    {
        $page = (int)$page;

        if ($page < 1) {
            $page = 1;
        } elseif ($page > 100) {
            $page = 100;
        }

        $this->page = $page;
        $this->itemsPerPage = (int)$itemsPerPage;
        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage ? $this->itemsPerPage : 50;
    }

    /**
     * @param $category
     *
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = array((int)$category);
        return $this;
    }

    /**
     * @param $category
     *
     * @return $this
     */
    public function addCategory($category)
    {
        array_unshift($this->category, (int)$category);
        array_slice($this->category, 0, 3);
        return $this;
    }


    public function setKeyword($keyword)
    {
        $keyword = (string) $keyword;

        $keywordLength = mb_strlen($keyword, 'UTF-8');

        if ($keywordLength < 2 || $keywordLength > 350) {

        }

        $this->keyword = $keyword;
        return $this;
    }

    public function getKeyword()
    {
        return $this->keyword;
    }

    public function getCategory()
    {
        return $this->category;
    }

}