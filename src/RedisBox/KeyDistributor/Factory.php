<?php

namespace RedisBox\KeyDistributor;

class Factory
{
    const DEFAULT_DISTRIBUTOR = 'Crc32';

    /**
     * @param string $distributor
     * @return IKeyDistributor
     * @throws Exception
     */
    public static function getDistributor($distributor = static::DEFAULT_DISTRIBUTOR)
    {
        if (!class_exists($distributor)) {
            throw new Exception;
        }
        return new $distributor;
    }
}