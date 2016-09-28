<?php

namespace Symfony\Component\Workflow\Exception;

use Symfony\Component\Workflow\Definition;

/**
 * If the marking can contain only one place, we should control the definition.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SinglePlaceWorkflowValidator extends WorkflowValidator
{
    public function validate(Definition $definition, $name)
    {
        foreach ($definition->getTransitions() as $transition) {
            if (1 < count($transition->getTos())) {
                throw new InvalidDefinitionException(
                    sprintf(
                        'The marking store of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.',
                        $name,
                        $transition->getName(),
                        count($transition->getTos())
                    )
                );
            }
        }

        return parent::validate($definition, $name);
    }
}
