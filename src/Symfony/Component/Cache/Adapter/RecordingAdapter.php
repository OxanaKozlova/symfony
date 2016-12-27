<?php

/*
 * This file is part of php-cache\cache-bundle package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * An adapter that logs and collects all your cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class RecordingAdapter implements AdapterInterface, RecordingAdapterInterface
{
    /**
     * @var array
     */
    private $calls = array();

    /**
     * @var CacheItemPoolInterface
     */
    private $cachePool;

    /**
     * LoggingCachePool constructor.
     *
     * @param CacheItemPoolInterface $cachePool
     */
    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return object
     */
    private function timeCall($name, array $arguments = array())
    {
        $start = microtime(true);
        $result = call_user_func_array(array($this->cachePool, $name), $arguments);
        $time = microtime(true) - $start;

        $object = (object) compact('name', 'arguments', 'start', 'time', 'result');

        return $object;
    }

    public function getItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, array($key));
        $result = $call->result;
        $call->isHit = $result->isHit();

        // Display the result in a good way depending on the data type
        if ($call->isHit) {
            $call->result = $this->getValueRepresentation($result->get());
        } else {
            $call->result = null;
        }

        $this->addCall($call);

        return $result;
    }

    public function hasItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, array($key));
        $this->addCall($call);

        return $call->result;
    }

    public function deleteItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, array($key));
        $this->addCall($call);

        return $call->result;
    }

    public function save(CacheItemInterface $item)
    {
        $key = $item->getKey();
        $value = $this->getValueRepresentation($item->get());

        $call = $this->timeCall(__FUNCTION__, array($item));
        $call->arguments = array('<CacheItem>', $key, $value);
        $this->addCall($call);

        return $call->result;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $key = $item->getKey();
        $value = $this->getValueRepresentation($item->get());

        $call = $this->timeCall(__FUNCTION__, array($item));
        $call->arguments = array('<CacheItem>', $key, $value);
        $this->addCall($call);

        return $call->result;
    }

    public function getItems(array $keys = array())
    {
        $call = $this->timeCall(__FUNCTION__, array($keys));
        $result = $call->result;
        $call->result = sprintf('<DATA:%s>', gettype($result));
        $this->addCall($call);

        return $result;
    }

    public function clear()
    {
        $call = $this->timeCall(__FUNCTION__, array());
        $this->addCall($call);

        return $call->result;
    }

    public function deleteItems(array $keys)
    {
        $call = $this->timeCall(__FUNCTION__, array($keys));
        $this->addCall($call);

        return $call->result;
    }

    public function commit()
    {
        $call = $this->timeCall(__FUNCTION__);
        $this->addCall($call);

        return $call->result;
    }

    /**
     * {@inheritdoc}
     */
    public function getCalls()
    {
        return $this->calls;
    }

    /**
     * Record a call.
     *
     * @param $call
     */
    private function addCall($call)
    {
        $this->calls[] = $call;
    }

    /**
     * Get a string to represent the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getValueRepresentation($value)
    {
        $type = gettype($value);
        if (in_array($type, array('boolean', 'integer', 'double', 'string', 'NULL'))) {
            $rep = $value;
        } elseif ($type === 'array') {
            $rep = json_encode($value);
        } elseif ($type === 'object') {
            $rep = get_class($value);
        } else {
            $rep = sprintf('<DATA:%s>', $type);
        }

        return $rep;
    }
}
