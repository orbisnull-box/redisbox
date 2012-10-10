<?php

namespace RedisBox;

use \Bind\FastStorage\IStorage;
use \Bind\FastStorage\IKeyLocker;

class Redis implements IStorage
{
    const DEFAULT_PREFIX = 'O';
    const LOCK_KEY_PREFIX = '.lock_key.';
    const TAG_PREFIX      = '.tag.';
    const LOCK_KEY_TIME = '3000';
    const LOCK_USLEEP_TIME =  1000;

    protected $options = [];

    /**
     * @var Serializer\ISerializer;
     */
    protected $serializer;

    protected $prefix = self::DEFAULT_PREFIX;
    protected $tagPrefix = self::TAG_PREFIX;
    protected $lockKeyPrefix = self::LOCK_KEY_PREFIX;
    protected $keyLockTime = self::LOCK_KEY_TIME;

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

    public function serialize($value)
    {
        return $this->getSerializer()->serialize($value);
    }

    public function unserialize($value)
    {
        return $this->getSerializer()->unserialize($value);
    }

    public function prepareKey($key)
    {
        $key = $this->prefix.'.'.$key;
        return $key;
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
        $key = $this->prepareKey($key);
        $client = $this->getClient($key);

        $set = $client->setNX($key, $this->serialize($value));

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
            if (!$client->sAdd($this->prefix.$this->tagPrefix.$tag, $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Save variable in memory storage
     *
     * @param string $key key
     * @param mixed $value value
     * @param int $ttl time to live (store) in seconds
     * @param array|string $tags array of tags for this key
     * @return bool
     */
    public function save($key, $value, $ttl = null, $tags = NULL)
    {
        $key = $this->prepareKey($key);

        $set = $this->getClient($key)->set($key, $this->serialize($value), $ttl);

        if (!$set) {
            return false;
        }
        if (!empty($tags)) {
            $this->setTags($key, $tags);
        }
        return true;
    }

    /**
     * Read data from memory storage
     *
     * @param string $key (string or array of string keys)
     * @param mixed $ttl_left = (ttl - time()) of key. Use to exclude dog-pile effect, with lock/unlock_key methods.
     * @return mixed
     */
    public function read($key, &$ttlLeft = -1)
    {
        $key = $this->prepareKey($key);
        $r = $this->unserialize($this->getClient($key)->get($key));

        if ($ttlLeft!==-1)
        {
            $ttlLeft = $this->getClient($key)->ttl($key);
            if ($ttlLeft < 1) {
                $ttlLeft = -1;
            }
        }
        return $r;
    }

    /**
     * Delete key or array of keys from storage
     * @param string $key - key
     * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
     */
    public function delete($key)
    {
        $key = $this->prepareKey($key);
        $client = $this->getClient($key);
        $tags = $this->getClient($key)->keys($this->tagPrefix . '*');
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $client->sRem($tag, $key);
            }
        }
        return $client->del($key);
    }

    /**
     * Delete keys by tags
     *
     * @param array|string $tag - tag or array of tags
     * @return boolean
     */
    public function deleteByTags($tag)
    {
        // TODO: Implement deleteByTags() method.
        throw new Exception('Not Implemented');
    }

    /**
     * Get exclusive mutex for key. Key will be still accessible to read and write, but
     * another process can exclude dog-pile effect, if before updating the key he will try to get this mutex.
     * @param mixed $key
     * @param mixed $autoUnLocker - pass empty, just declared variable
     */
    public function lock($key, &$autoUnLocker)
    {
        $lockKey = $this->prefix . $this->lockKeyPrefix . $key;
        $client = $this->getClient($lockKey);
        $r = $client->setNX($lockKey, 1);
        if (!$r) {
            return false;
        }
        $client->Expire($lockKey, $this->keyLockTime);
        $autoUnLocker = new KeyLocker([$this, 'unlock']);
        $autoUnLocker->setKey($key);
        return true;
    }

    /**
     * Unlock key, locked by method 'lock_key'
     * @param KeyAutoUnlocker $autoUnlocker
     * @return bool
     */
    public function unlock(IKeyLocker $autoUnlocker)
    {
        $key = $autoUnlocker->getKey();
        if (empty($key)) {
            $this->ReportError('Empty key in the AutoUnlocker', __LINE__);
            return false;
        }
        $key = $this->prefix . $this->lockKeyPrefix . $key;
        $autoUnlocker->revoke();
        return $this->getClient($key)->del($key);
    }

    /**
     * Try to lock key, and if key is already locked - wait, until key will be unlocked.
     * Time of waiting is defined in max_wait_unlock constant of MemoryObject class.
     * @param string $key
     * @param $autoUnlocker
     * @return boolean
     */
    public function acquire($key, &$autoUnLocker, $maxWait = IStorage::LOCK_WAIT_TIME)
    {
        $t = microtime(true);
        while (!$this->lock($key, $autoUnLocker)) {
            if ((microtime(true)-$t) > $maxWait) {
                return false;
            }
            usleep(self::LOCK_USLEEP_TIME);
        }
        return true;
    }

    /**
     * Increment value of the key
     * @param string $key
     * @param mixed $byValue
     *                              if stored value is an array:
     *                              if $by_value is a value in array, new element will be pushed to the end of array,
     *                              if $by_value is a key=>value array, new key=>value pair will be added (or updated)
     * @param int $limitKeysCount - maximum count of elements (used only if stored value is array)
     * @param int $ttl              - set time to live for key
     * @return int|string|array new value of key
     */
    public function increment($key, $byValue = 1, $limitKeysCount = 0, $ttl = null)
    {
        $autoUnLocker = null;
        if (!$this->acquire($key, $autoUnLocker)) {
            return false;
        }

        $value = $this->read($key);

        if ($value === null || $value === false) {
            return $this->save($key, $byValue, $ttl);
        }

        if (is_array($value)) {
            $value = $this->incrementArray($limitKeysCount, $value, $byValue);
        } elseif (is_numeric($value) && is_numeric($byValue)) {
            $value += $byValue;
        } else {
            $value .= $byValue;
        }

        if ($this->save($key, $value, $ttl)) {
            return $value;
        } else {
            return false;
        }
    }

    protected function incrementArray($limit_keys_count, $value, $by_value)
    {
        if ($limit_keys_count > 0 && (count($value) > $limit_keys_count)) $value = array_slice($value, $limit_keys_count*(-1)+1);
        if (is_array($by_value))
        {
            $set_key = key($by_value);
            if (!empty($set_key)) $value[$set_key] = $by_value[$set_key];
            else $value[] = $by_value[0];
        }
        else $value[] = $by_value;
        return $value;
    }


}

