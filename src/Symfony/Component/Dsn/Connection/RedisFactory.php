<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn\Connection;

use Predis\Connection\Factory;
use Symfony\Component\Dsn\Configuration\Path;
use Symfony\Component\Dsn\Configuration\Url;
use Symfony\Component\Dsn\ConnectionFactoryInterface;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\DsnTypeNotSupported;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisFactory implements ConnectionFactoryInterface
{
    private static $defaultConnectionOptions = array(
        'class' => null,
        'persistent' => 0,
        'persistent_id' => null,
        'timeout' => 30,
        'read_timeout' => 0,
        'retry_interval' => 0,
    );

    public function create(string $dsnString): object
    {
        $dsn = DsnParser::parse($dsnString);
        if ($dsn->getScheme() !== 'redis' && $dsn->getScheme() !== 'rediss') {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: "%s" does not start with "redis:" or "rediss".', $dsn));
        }

        $auth = $dsn->getPassword() ?? $dsn->getUser();
        $params['path'] = $dsn->getPath();
        $params['dbindex'] = 0;
        if (null !== $params['path'] && preg_match('#/(\d+)$#', $params['path'], $m)) {
            $params['dbindex'] = $m[1];
            $params['path'] = substr($params['path'], 0, -strlen($m[0]));
        }

        if ($dsn instanceof Url) {
            $scheme = 'tcp';
            $params['host'] = $dsn->getHost();
            $params['port'] = $dsn->getPort() ?? 6379;
        } elseif ($dsn instanceof Path) {
            $scheme = 'unix';
            $params['host'] = $params['path'];
            $params['port'] = null;
            unset($params['path']);
        } else {
            throw new DsnTypeNotSupported($dsn, 'Only Path and Url type of DSN is supported. ');
        }

        $params += $dsn->getParameters() + self::$defaultConnectionOptions;

        $class = null === $params['class'] ? (extension_loaded('redis') ? \Redis::class : \Predis\Client::class) : $params['class'];
        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();
            @$redis->{$connect}($params['host'], $params['port'], $params['timeout'], $params['persistent_id'], $params['retry_interval']);

            if (@!$redis->isConnected()) {
                $e = ($e = error_get_last()) && preg_match('/^Redis::p?connect\(\): (.*)/', $e['message'], $e) ? sprintf(' (%s)', $e[1]) : '';
                throw new InvalidArgumentException(sprintf('Redis connection failed%s: %s', $e, $dsn));
            }

            if ((null !== $auth && !$redis->auth($auth))
                || ($params['dbindex'] && !$redis->select($params['dbindex']))
                || ($params['read_timeout'] && !$redis->setOption(\Redis::OPT_READ_TIMEOUT, $params['read_timeout']))
            ) {
                $e = preg_replace('/^ERR /', '', $redis->getLastError());
                throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e, $dsn));
            }
        } elseif (is_a($class, \Predis\Client::class, true)) {
            $params['scheme'] = $scheme;
            $params['database'] = $params['dbindex'] ?: null;
            $params['password'] = $auth;
            $redis = new $class((new Factory())->create($params));
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Redis" or "Predis\Client"', $class));
        } else {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist', $class));
        }

        return $redis;
    }

    public function supports(string $dsn): bool
    {
        return strpos($dsn, 'redis:') === 0 || strpos($dsn, 'rediss:') === 0;
    }
}
