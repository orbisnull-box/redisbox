<?php

namespace RedisBox\Serializer;

class Factory
{
    const DEFAULT_SERIALIZER = 'Php';

    /**
     * @param string $serializer
     * @return ISerializer
     * @throws Exception
     */
    public static function getSerializer($serializer = static::DEFAULT_SERIALIZER)
    {
        if (!class_exists($serializer)) {
            throw new Exception;
        }
        return new $serializer;
    }
}