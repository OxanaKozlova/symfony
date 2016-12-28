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
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * An adapter that logs and collects all your cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class TraceableAdapter implements AdapterInterface
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
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @param CacheItemPoolInterface $cachePool
     * @param Stopwatch              $stopwatch
     */
    public function __construct(CacheItemPoolInterface $cachePool, Stopwatch $stopwatch)
    {
        $this->cachePool = $cachePool;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return object
     */
    private function timeCall($name, array $arguments = array())
    {
        $time = 0;
        $event = $this->stopwatch->start(get_class($this->cachePool), 'cache');
        $result = call_user_func_array(array($this->cachePool, $name), $arguments);
        if ($event->isStarted()) {
            $event->stop();
            $time = $event->getEndTime() - $event->getStartTime();
        }

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
            $call->result = $this->getValueRepresentation($result);
        } else {
            $call->result = null;
        }

        $this->calls[] = $call;

        return $result;
    }

    public function hasItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, array($key));
        $this->calls[] = $call;

        return $call->result;
    }

    public function deleteItem($key)
    {
        $call = $this->timeCall(__FUNCTION__, array($key));
        $this->calls[] = $call;

        return $call->result;
    }

    public function save(CacheItemInterface $item)
    {
        $arg = $this->getValueRepresentation($item);

        $call = $this->timeCall(__FUNCTION__, array($item));
        $call->arguments = array($arg);
        $this->calls[] = $call;

        return $call->result;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $arg = $this->getValueRepresentation($item);

        $call = $this->timeCall(__FUNCTION__, array($item));
        $call->arguments = array($arg);
        $this->calls[] = $call;

        return $call->result;
    }

    public function getItems(array $keys = array())
    {
        $call = $this->timeCall(__FUNCTION__, array($keys));
        $result = $call->result;

        $f = function() use ($result, $call) {
            $hits = 0;
            $items = array();
            foreach ($result as $item) {
                $items[] = $item;
                if ($item->isHit()) {
                    ++$hits;
                }

                $call->result = $this->getValueRepresentation($items);
                $call->hits = $hits;
                $call->count = count($items);

                yield $item;
            }
        };

        $call->result = 'NULL';
        $call->hits = 0;
        $call->count = 0;
        $this->calls[] = $call;

        return $f();
    }

    public function clear()
    {
        $call = $this->timeCall(__FUNCTION__, array());
        $this->calls[] = $call;

        return $call->result;
    }

    public function deleteItems(array $keys)
    {
        $call = $this->timeCall(__FUNCTION__, array($keys));
        $this->calls[] = $call;

        return $call->result;
    }

    public function commit()
    {
        $call = $this->timeCall(__FUNCTION__);
        $this->calls[] = $call;

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
     * Get a string to represent the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getValueRepresentation($value)
    {
        $type = gettype($value);
        if (in_array($type, array('array', 'boolean', 'integer', 'double', 'string', 'NULL'))) {
            $rep = $value;
        } elseif ($type === 'object') {
            $rep = clone $value;
        } else {
            $rep = sprintf('<DATA:%s>', $type);
        }

        return $rep;
    }
}
