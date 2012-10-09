<?php

namespace RedisBox\Serializer;

class Php implements ISerializer
{
    public function serialize($value)
    {
        return serialize($value);
    }

    public function unserialize($value)
    {
        return unserialize($value);
    }

}