<?php

namespace App\Stdlib;



/**
 * Utility class for testing and manipulation of PHP arrays.
 *
 * Declared abstract, as we have no need for instantiation.
 */
abstract class ArrayUtils extends \Zend\Stdlib\ArrayUtils
{
    public static function filterEmpty($input)
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = self::filterEmpty($value);
            }
        }

        return array_filter($input);
    }

}
