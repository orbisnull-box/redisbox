<?php

namespace RedisBox\KeyDistributor;

class Crc32 implements IKeyDistributor
{
    protected $_connections = array();

    protected $_connectionCount = 0;

    protected $_connectionPositions = array();

    protected $_connectionPositionCount = 0;

    public function addConnection($connectionString, $weight = IKeyDistributor::DEFAULT_WEIGHT)
    {
        if (in_array($connectionString, $this->_connections)) {
            throw new Exception("Connection '$connectionString' already exists.");
        }

        $this->_connections[] = $connectionString;
        $this->_connectionCount++;

        // add connection positions
        for ($index = 0; $index < $weight; $index++) {
            $this->_connectionPositions[] = $connectionString;
            $this->_connectionPositionCount++;
        }

        return $this;
    }

    public function removeConnection($connectionString)
    {
        if (!in_array($connectionString, $this->_connections)) {
            throw new Exception("Connection '$connectionString' does not exist.");
        }

        $index = array_search($connectionString, $this->_connections);
        unset($this->_connections[$index]);
        $this->_connectionCount--;

        // remove connection positions
        $connectionPositions = $this->_connectionPositions;
        $this->_connectionPositions = array();
        $this->_connectionPositionCount = 0;
        foreach($connectionPositions as $connection) {
            if ($connection != $connectionString) {
                $this->_connectionPositions[] = $connection;
                $this->_connectionPositionCount++;
            }
        }

        return $this;
    }

    public function getConnectionByKeyName($name)
    {
        if (empty($this->_connections)) {
            throw new Exception("No connection exists.");
        }

        if ($this->_connectionCount == 1) {
            return $this->_connections[0];
        }

        $index = abs(crc32($name) % $this->_connectionPositionCount);

        return $this->_connectionPositions[$index];
    }
}