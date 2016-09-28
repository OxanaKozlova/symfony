<?php

namespace Symfony\Component\Workflow\Exception;

use Symfony\Component\Workflow\Definition;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface DefinitionValidator
{
    /**
     * @param Definition $definition
     * @param string     $name
     *
     * @return bool
     *
     * @throws InvalidDefinitionException on invalid definition
     */
    public function validate(Definition $definition, $name);
}
