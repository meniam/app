<?php

namespace App\Filter;

use Zend\Filter\AbstractFilter;

class Text extends AbstractFilter
{
    public function filter($value)
    {
        return $value;
    }
}