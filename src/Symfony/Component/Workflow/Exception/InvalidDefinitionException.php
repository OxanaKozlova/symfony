<?php

namespace Symfony\Component\Workflow\Exception;

/**
 * Thrown by the DefinitionValidator when the definition is invalid.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class InvalidDefinitionException extends \LogicException implements ExceptionInterface
{
}
