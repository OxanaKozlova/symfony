<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn\Exception;

/**
 * Base InvalidArgumentException for the Dsn component.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
