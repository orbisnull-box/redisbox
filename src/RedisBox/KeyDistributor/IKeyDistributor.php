<?php

namespace RedisBox\KeyDistributor;

interface IKeyDistributor
{
    const DEFAULT_WEIGHT = 1;

    /**
     * Add connection
     *
     * @param string $connectionString Connection string: '127.0.0.1:6379'
     * @param integer $weight Connection weight
     * @return $this
     */
    public function addConnection($connectionString, $weight = IKeyDistributor::DEFAULT_WEIGHT);

    /**
     * Remove connection
     * @param string $connectionString Connection string: '127.0.0.1:6379'
     * @return $this
     */
    public function removeConnection($connectionString);

    /**
     * Get connection by key name
     * @param string $name Key name
     * @return string Connection string
     */
    public function getConnectionByKeyName($name);
}