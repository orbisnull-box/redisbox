<?php

namespace RedisBox\KeyDistributor;

class Factory
{
    const DEFAULT_DISTRIBUTOR = '\RedisBox\KeyDistributor\Crc32';

    /**
     * @param string $distributor
     * @return IKeyDistributor
     * @throws Exception
     */
    public static function getDistributor($distributor = self::DEFAULT_DISTRIBUTOR)
    {
        if (!class_exists($distributor, true)) {
            throw new Exception('Distributor not avialible');
        }
        return new $distributor;
    }
}