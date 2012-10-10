<?php

namespace RedisBox;

use \Bind\FastStorage\IKeyLocker;

class KeyLocker implements IKeyLocker
{
    protected $key;
    protected $unLock;

    /**
     * @param callable $Unlock
     */
    public function __construct($unLock)
    {
        if (is_callable($unLock)) {
            $this->unLock = $unLock;
        }
    }

    public function __destruct()
    {
        if (is_callable($this->unLock)) {
            call_user_func($this->unLock, $this);
        }
    }

    public function revoke()
    {
        $this->unLock = NULL;
    }

    /** @return string */
    public function getKey()
    {
        return $this->key;
    }

    /** @param string $key */
    public function setKey($key)
    {
        $this->key = $key;
    }

}