<?php

namespace Symfony\Component\Workflow\Exception;

use Symfony\Component\Workflow\Definition;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class WorkflowValidator implements DefinitionValidator
{
    public function validate(Definition $definition, $name)
    {
    }
}
