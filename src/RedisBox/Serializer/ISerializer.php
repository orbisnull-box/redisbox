<?php

namespace RedisBox\Serializer;

interface ISerializer
{
    public function serialize($value);

    public function unserialize($value);
}