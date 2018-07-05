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
 * When handling recorded messaged one or more handlers caused an exception.
 * This exception contains all those handler exceptions.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MessageRecorderHandlerException extends \RuntimeException implements ExceptionInterface
{
    private $exceptions = array();

    public static function create(array $exceptions): self
    {
        $message = sprintf(
            "One or more handlers for reordered messages threw an exception. Their messages were: \n\n%s",
            implode(", \n", array_map(function (\Throwable $e) {
                return $e->getMessage();
            }, $exceptions))
        );

        return new MessageRecorderHandlerException($message);
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}