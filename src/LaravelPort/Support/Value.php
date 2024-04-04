<?php

namespace Autoframe\Components\SocketCache\LaravelPort\Support;

class Value
{
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function value($value, ...$args)
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}