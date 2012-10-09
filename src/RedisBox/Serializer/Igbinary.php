<?php

namespace RedisBox\Serializer;

class Igbinary implements ISerializer
{
    public function __construct()
    {
        if (!function_exists('igbinary_serialize')) {
            throw new Exception('Please setup igbinary extension in you php');
        }
    }

    public function serialize($value)
    {
        return igbinary_serialize($value);
    }

    public function unserialize($value)
    {
        return igbinary_unserialize($value);
    }

}