<?php

namespace RedisBox;

use \Bind\FastStorage\IStorage;

class Redis implements IStorage
{
    const LOCK_KEY_PREFIX = '.lock_key.';
    const TAG_PREFIX      = '.tag.';

    protected $options = [];

    /**
     * @var Serializer\ISerializer;
     */
    protected $serializer;

    protected $prefix = 'O';
    protected $tagPrefix;
    protected $lockKeyPrefix;

    /**
     * @var Cluster
     */
    protected $cluster;

    public function __construct($hosts = [['$host' => 'localhost', 'port' => '6379']], array $options = [])
    {
        $this->options = $options;
        if (!isset($options['cluster'])) {
            $options['cluster'] = [];
        }
        $this->cluster = new Cluster($hosts, $options['cluster']);
    }

    /**
     * @return Cluster
     */
    public function getCluster()
    {
        return $this->cluster;
    }

    /**
     * @return Serializer\ISerializer
     */
    public function getSerializer()
    {
        if (is_null($this->serializer)) {
            if (!isset($this->options['serializer'])) {
                $this->options['serializer'] = Serializer\Factory::DEFAULT_SERIALIZER;
            }
            $this->serializer = Serializer\Factory::getSerializer($this->options['serializer']);
        }
        return $this->serializer;
    }

    /**
     * @param $key
     * @return Client
     */
    protected function getClient($key)
    {
        return $this->getCluster()->getClient($key);
    }

    public function setNamespace($namespace)
    {
        $this->prefix = $namespace;
    }

    public function getNamespace()
    {
        return $this->prefix;
    }

    /**
     * Add value to memory storage, only if this key does not exists (or false will be returned).
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array|string $tags
     * @return boolean
     */
    public function add($key, $value, $ttl = null, $tags = NULL)
    {
        $key = $this->prefix.'.'.$key;
        $client = $this->getClient($key);

        $set = $client->setNX($key, $this->getSerializer()->serialize($value));

        if (!$set) {
            return false;
        }
        if (!empty($ttl)) {
            $client->expire($key, $ttl);
        }
        if (!empty($tags)) {
            $this->setTags($key, $tags);
        }
        return true;
    }

    /**
     * Set tags, associated with the key
     *
     * @param string $key
     * @param string|array $tags
     * @return bool
     */
    public function setTags($key, $tags)
    {
        $client = $this->getClient($key);
        if (!is_array($tags)) $tags = array($tags);
        foreach ($tags as $tag) {
            if (!$client->sAdd($this->tagPrefix.'.'.$tag, $key)) {
                return false;
            }
        }
        return true;
    }




}

