<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Message;

use Psr\Container\ContainerInterface;
use Symfony\Component\Message\Exception\NoHandlerForMessageException;

/**
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 */
class ContainerHandlerLocator implements HandlerLocatorInterface
{
    /**
     * @var ContainerInterface
     */
    private $serviceLocator;

    public function __construct(ContainerInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function resolve($message): callable
    {
        $messageKey = get_class($message);

        if (!$this->serviceLocator->has($messageKey)) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s"', $messageKey));
        }

        return $this->serviceLocator->get($messageKey);
    }
}
