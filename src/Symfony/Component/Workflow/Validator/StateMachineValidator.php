<?php

namespace Symfony\Component\Workflow\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StateMachineValidator implements DefinitionValidatorInterface
{
    public function validate(Definition $definition, $name)
    {
        $transitionFromNames = array();
        foreach ($definition->getTransitions() as $transition) {
            // Make sure that each transition has exactly one TO
            if (1 !== count($transition->getTos())) {
                throw new InvalidDefinitionException(
                    sprintf(
                        'A transition in StateMachine can only have one output. But the transition "%s" in StateMachine "%s" has %d outputs.',
                        $transition->getName(),
                        $name,
                        count($transition->getTos())
                    )
                );
            }

            // Make sure that each transition has exactly one FROM
            $froms = $transition->getFroms();
            if (1 !== count($froms)) {
                throw new InvalidDefinitionException(
                    sprintf(
                        'A transition in StateMachine can only have one input. But the transition "%s" in StateMachine "%s" has %d inputs.',
                        $transition->getName(),
                        $name,
                        count($transition->getTos())
                    )
                );
            }

            // Enforcing uniqueness of the names of transitions starting at each node
            $from = reset($froms);
            if (isset($transitionFromNames[$from][$transition->getName()])) {
                throw new InvalidDefinitionException(
                    sprintf(
                        'A transition from a place/state must have an unique name. Multiple transition named "%s" from place/state "%s" where found on StateMachine "%s". ',
                        $transition->getName(),
                        $from,
                        $name
                    )
                );
            }
            $transitionFromNames[$from][$transition->getName()] = true;
        }

        return true;
    }
}
