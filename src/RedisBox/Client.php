<?php

namespace RedisBox;

class Client
{
    protected $connection;
    protected $repeat_reconnected = false;
    private $host = 'localhost';
    private $port = 6379;


    public function __construct($options)
    {
        /*$this->host = $host;
        $this->port = $port;*/
    }

    public function connect($host, $port)
    {
        if (!empty($this->connection))
        {
            fclose($this->connection);
            $this->connection = NULL;
        }
        $socket = fsockopen($host, $port, $errno, $errstr);
        if (!$socket)
        {
            throw new Exception('Connection error: '.$errno.':'.$errstr);
        }
        $this->connection = $socket;
        return $this->connection;
    }

    /**
     * Execute send_command and return the result
     * Each entity of the send_command should be passed as argument
     * Example:
     *  send_command('set','key','example value');
     * or:
     *  send_command('multi');
     *  send_command('set','a', serialize($arr));
     *  send_command('set','b', 1);
     *  send_command('execute');
     * @return array|bool|int|null|string
     */
    public function sendCommand()
    {
        return $this->send(func_get_args());
    }

    protected function send($args)
    {
        if (empty($this->connection)) {
            if (!$this->connect($this->host, $this->port)) {
                return false;
            }
        }
        $command = '*'.count($args)."\r\n";
        foreach ($args as $arg) {
            $command .= "$".strlen($arg)."\r\n".$arg."\r\n";
        }
        $w = fwrite($this->connection, $command);
        if (!$w) {
            //if connection was lost
            $this->connect($this->host, $this->port);
            if (!fwrite($this->connection, $command)) {
                throw new Exception('command was not sent');
            }
        }
        $answer = $this->readReply();
        if ($answer===false && $this->readReply()) {
            if (fwrite($this->connection, $command)) {
                $answer = $this->readReply();
            }
            $this->repeat_reconnected = false;
        }
        return $answer;
    }

    protected function readReply()
    {
        $serverReply = fgets($this->connection);
        if ($serverReply===false)
        {
            if (!$this->connect($this->host, $this->port))
            {
                return false;
            }
            else
            {
                $serverReply = fgets($this->connection);
                if (empty($serverReply))
                {
                    $this->repeat_reconnected = true;
                    return false;
                }
            }
        }
        $reply    = trim($serverReply);
        $response = null;
        /**
         * Thanks to Justin Poliey for original code of parsing the answer
         * https://github.com/jdp
         * Error was fixed there: https://github.com/jamm/redisent
         */
        switch ($reply[0])
        {
            /* Error reply */
            case '-':
                $this->reportError('error: '.$reply);
                return false;
            /* Inline reply */
            case '+':
                return substr($reply, 1);
            /* Bulk reply */
            case '$':
                if ($reply=='$-1') return null;
                $response = null;
                $size     = intval(substr($reply, 1));
                if ($size > 0)
                {
                    $response = stream_get_contents($this->connection, $size);
                }
                fread($this->connection, 2); /* discard crlf */
                break;
            /* Multi-bulk reply */
            case '*':
                $count = substr($reply, 1);
                if ($count=='-1') return null;
                $response = array();
                for ($i = 0; $i < $count; $i++)
                {
                    $response[] = $this->_read_reply();
                }
                break;
            /* Integer reply */
            case ':':
                return intval(substr($reply, 1));
                break;
            default:
                $this->reportError('Non-protocol answer: '.print_r($serverReply, 1));
                return false;
        }
        return $response;
    }

    /**
     * used if some command is not wrapped
    */
    /*public function __call($name, $args)
    {
        array_unshift($args, str_replace('_', ' ', $name));
        return $this->_send($args);
    }*/

    public function setNX($key, $value)
    {
        return $this->send(array('setnx', $key, $value));
    }

    public function expire($key, $seconds)
    {
        return $this->_send(array('expire', $key, $seconds));
    }

    public function sAdd($set, $value)
    {
        /*if (!is_array($value)) $value = func_get_args();
        else array_unshift($value, $set);
        return $this->__call('sadd', $value);*/
    }





}