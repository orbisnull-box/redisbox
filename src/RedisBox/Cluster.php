<?php

namespace RedisBox;

class Cluster
{
    protected $options = [];

    protected $hosts = [];
    /**
     * @var Client[]
     */
    protected $clients = [];

    /**
     * @var KeyDistributor\IKeyDistributor
     */
    protected $keyDistributor;

    public function __construct($hosts = [['$host' => 'localhost', 'port' => '6379']], array $options = [])
    {
        $this->options = $options;
        $keyDistributor = $this->getKeyDistributor();

        foreach ($hosts as $host) {
            $hash = hash('md5', implode('|', $host));
            if (!isset($host['weight'])) {
                $host['weight'] = KeyDistributor\IKeyDistributor::DEFAULT_WEIGHT;
            }
            $this->hosts[$hash] = $host;
            $keyDistributor->addConnection($hash, $host['weight']);
        }
    }

    /**
     * @return KeyDistributor\IKeyDistributor
     */
    public function getKeyDistributor()
    {
        if (is_null($this->keyDistributor)){
            if (!isset($this->options['distributor'])) {
                $this->options['distributor'] = KeyDistributor\Factory::DEFAULT_DISTRIBUTOR;
            }
            $this->keyDistributor = KeyDistributor\Factory::getDistributor($this->options['distributor']);
        }
        return $this->keyDistributor;
    }

    public function getHostByKeyName($key)
    {
        $hash = $this->getKeyDistributor()->getConnectionByKeyName($key);
        return $hash;
    }

    /**
     * @param string $key
     * @return Client
     */
    public function getClient($key)
    {
        $hostHash = $this->getHostByKeyName($key);
        if (!isset($this->clients[$hostHash])) {
            $this->clients[$hostHash] = new Client($this->hosts[$hostHash]);
        }
        return $this->clients[$hostHash];
    }


}