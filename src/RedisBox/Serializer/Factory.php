<?php

namespace RedisBox\Serializer;

class Factory
{
    const DEFAULT_SERIALIZER = '\RedisBox\Serializer\Php';

    /**
     * @param string $serializer
     * @return ISerializer
     * @throws Exception
     */
    public static function getSerializer($serializer = self::DEFAULT_SERIALIZER)
    {
        if (!class_exists($serializer, true)) {
            throw new Exception('Serializer not avialible');
        }
        return new $serializer;
    }
}