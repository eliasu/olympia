<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class Abs extends Modifier
{
    /**
     * Return the absolute value of a number.
     *
     * @param mixed $value
     * @param array $params
     * @param array $context
     * @return float|int
     */
    public function index($value, $params, $context)
    {
        return abs((float) $value);
    }
}
