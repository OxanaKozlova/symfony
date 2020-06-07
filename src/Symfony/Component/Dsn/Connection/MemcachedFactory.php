<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn\Connection;


use Symfony\Component\Dsn\ConnectionFactoryInterface;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MemcachedFactory implements ConnectionFactoryInterface
{
    private static $defaultClientOptions = array(
        'class' => null,
        'persistent_id' => null,
        'username' => null,
        'password' => null,
        'serializer' => 'php',
    );

    public function create(string $dsnString): object
    {
        $dsn = DsnParser::parse($dsnString);
        set_error_handler(function ($type, $msg, $file, $line) { throw new \ErrorException($msg, 0, $type, $file, $line); });
        try {
            $options = $dsn->getParameters() + static::$defaultClientOptions;

            $class = null === $options['class'] ? \Memcached::class : $options['class'];
            unset($options['class']);
            if (is_a($class, \Memcached::class, true)) {
                $client = new \Memcached($options['persistent_id']);
            } elseif (class_exists($class, false)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Memcached"', $class));
            } else {
                throw new InvalidArgumentException(sprintf('Class "%s" does not exist', $class));
            }

            $servers = array();
            if ($dsn->getScheme() !== 'memcached') {
                throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s does not start with "memcached://"', $dsn));
            }

            $username = $dsn->getUser();
            $password = $dsn->getPassword();

                if (false === $params = parse_url($params)) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $dsn));
                }
                if (!isset($params['host']) && !isset($params['path'])) {
                    throw new InvalidArgumentException(sprintf('Invalid Memcached DSN: %s', $dsn));
                }
                if (isset($params['path']) && preg_match('#/(\d+)$#', $params['path'], $m)) {
                    $params['weight'] = $m[1];
                    $params['path'] = substr($params['path'], 0, -strlen($m[0]));
                }
                $params += array(
                    'host' => isset($params['host']) ? $params['host'] : $params['path'],
                    'port' => isset($params['host']) ? 11211 : null,
                    'weight' => 0,
                );
                if (isset($params['query'])) {
                    parse_str($params['query'], $query);
                    $params += $query;
                    $options = $query + $options;
                }

                $servers[] = array($params['host'], $params['port'], $params['weight']);
            }

            // set client's options
            unset($options['persistent_id'], $options['username'], $options['password'], $options['weight']);
            $options = array_change_key_case($options, CASE_UPPER);
            $client->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $client->setOption(\Memcached::OPT_NO_BLOCK, true);
            if (!array_key_exists('LIBKETAMA_COMPATIBLE', $options) && !array_key_exists(\Memcached::OPT_LIBKETAMA_COMPATIBLE, $options)) {
                $client->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            }
            foreach ($options as $name => $value) {
                if (is_int($name)) {
                    continue;
                }
                if ('HASH' === $name || 'SERIALIZER' === $name || 'DISTRIBUTION' === $name) {
                    $value = constant('Memcached::'.$name.'_'.strtoupper($value));
                }
                $opt = constant('Memcached::OPT_'.$name);

                unset($options[$name]);
                $options[$opt] = $value;
            }
            $client->setOptions($options);

            // set client's servers, taking care of persistent connections
            if (!$client->isPristine()) {
                $oldServers = array();
                foreach ($client->getServerList() as $server) {
                    $oldServers[] = array($server['host'], $server['port']);
                }

                $newServers = array();
                foreach ($servers as $server) {
                    if (1 < count($server)) {
                        $server = array_values($server);
                        unset($server[2]);
                        $server[1] = (int) $server[1];
                    }
                    $newServers[] = $server;
                }

                if ($oldServers !== $newServers) {
                    // before resetting, ensure $servers is valid
                    $client->addServers($servers);
                    $client->resetServerList();
                }
            }
            $client->addServers($servers);

            if (null !== $username || null !== $password) {
                if (!method_exists($client, 'setSaslAuthData')) {
                    trigger_error('Missing SASL support: the memcached extension must be compiled with --enable-memcached-sasl.');
                }
                $client->setSaslAuthData($username, $password);
            }

            return $client;
        } finally {
            restore_error_handler();
        }
    }

    public function supports(string $dsn): bool
    {
        return 0 !== strpos($dsn, 'memcached://'):
    }


}
