<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DataCollector;

use Symfony\Component\Cache\Adapter\RecordingAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheDataCollector extends DataCollector
{
    /**
     * @var RecordingAdapter[]
     */
    private $instances = array();

    /**
     * @param string           $name
     * @param RecordingAdapter $instance
     */
    public function addInstance($name, RecordingAdapter $instance)
    {
        $this->instances[$name] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $empty = array('calls' => array(), 'config' => array(), 'options' => array(), 'statistics' => array());
        $this->data = array('instances' => $empty, 'total' => $empty);
        foreach ($this->instances as $name => $instance) {
            $this->data['instances']['calls'][$name] = $instance->getCalls();
        }

        $this->data['instances']['statistics'] = $this->calculateStatistics();
        $this->data['total']['statistics'] = $this->calculateTotalStatistics();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'cache';
    }

    /**
     * Method returns amount of logged Cache reads: "get" calls.
     *
     * @return array
     */
    public function getStatistics()
    {
        return $this->data['instances']['statistics'];
    }

    /**
     * Method returns the statistic totals.
     *
     * @return array
     */
    public function getTotals()
    {
        return $this->data['total']['statistics'];
    }

    /**
     * Method returns all logged Cache call objects.
     *
     * @return mixed
     */
    public function getCalls()
    {
        return $this->data['instances']['calls'];
    }

    /**
     * @return array
     */
    private function calculateStatistics()
    {
        $statistics = array();
        foreach ($this->data['instances']['calls'] as $name => $calls) {
            $statistics[$name] = array(
                'calls' => 0,
                'time' => 0,
                'reads' => 0,
                'hits' => 0,
                'misses' => 0,
                'writes' => 0,
                'deletes' => 0,
            );
            foreach ($calls as $call) {
                $statistics[$name]['calls'] += 1;
                $statistics[$name]['time'] += $call->time;
                if ($call->name === 'getItem') {
                    $statistics[$name]['reads'] += 1;
                    if ($call->isHit) {
                        $statistics[$name]['hits'] += 1;
                    } else {
                        $statistics[$name]['misses'] += 1;
                    }
                } elseif ($call->name === 'hasItem') {
                    $statistics[$name]['reads'] += 1;
                    if ($call->result === false) {
                        $statistics[$name]['misses'] += 1;
                    }
                } elseif ($call->name === 'save') {
                    $statistics[$name]['writes'] += 1;
                } elseif ($call->name === 'deleteItem') {
                    $statistics[$name]['deletes'] += 1;
                }
            }
            if ($statistics[$name]['reads']) {
                $statistics[$name]['ratio'] = round(100 * $statistics[$name]['hits'] / $statistics[$name]['reads'], 2).'%';
            } else {
                $statistics[$name]['ratio'] = 'N/A';
            }
        }

        return $statistics;
    }

    /**
     * @return array
     */
    private function calculateTotalStatistics()
    {
        $statistics = $this->getStatistics();
        $totals = array('calls' => 0, 'time' => 0, 'reads' => 0, 'hits' => 0, 'misses' => 0, 'writes' => 0);
        foreach ($statistics as $name => $values) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $statistics[$name][$key];
            }
        }
        if ($totals['reads']) {
            $totals['ratio'] = round(100 * $totals['hits'] / $totals['reads'], 2).'%';
        } else {
            $totals['ratio'] = 'N/A';
        }

        return $totals;
    }
}
