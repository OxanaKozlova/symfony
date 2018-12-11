<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * When handling queued messages from {@link HandleMessageInNewTransactionMiddleware},
 * some handlers caused an exception. This exception contains all those handler exceptions.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class QueuedMessageHandlingException extends \RuntimeException implements ExceptionInterface
{
    private $exceptions;

    public function __construct(array $exceptions)
    {
        $message = sprintf(
            "Some handlers for queued messages threw an exception: \n\n%s",
            implode(", \n", array_map(function (\Throwable $e) {
                return \get_class($e).': '.$e->getMessage();
            }, $exceptions))
        );

        $this->exceptions = $exceptions;

        parent::__construct($message, 0, 1 === \count($exceptions) ? $exceptions[0] : null);
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
