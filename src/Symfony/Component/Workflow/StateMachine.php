<?php

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\ScalarMarkingStore;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StateMachine extends PetriNet
{
    public function __construct(Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, $name = 'unnamed')
    {
        if (!$markingStore) {
            $markingStore = new ScalarMarkingStore();
        }

        parent::__construct($definition, $markingStore, $dispatcher, $name);

        $transitionFromNames = array();

        /** @var Transition $transition */
        foreach ($definition->getTransitions() as $transition) {
            // Make sure that each transition has exactly one TO
            if (1 !== count($transition->getTos())) {
                throw new LogicException(
                    sprintf(
                        'A transition in StateMachine can only have one output. But the transition "%s" in StateMachine "%s" has %d outputs.',
                        $transition->getName(),
                        $this->getName(),
                        count($transition->getTos())
                    )
                );
            }

            // Make sure that each transition has exactly one FROM
            $froms = $transition->getFroms();
            if (1 !== count($froms)) {
                throw new LogicException(
                    sprintf(
                        'A transition in StateMachine can only have one input. But the transition "%s" in StateMachine "%s" has %d inputs.',
                        $transition->getName(),
                        $this->getName(),
                        count($transition->getTos())
                    )
                );
            }

            // Enforcing uniqueness of the names of transitions starting at each node
            $from = reset($froms);
            if (isset($transitionFromNames[$from][$transition->getName()])) {
                throw new LogicException(
                    sprintf(
                        'A transition from a place/state must have an unique name. Multiple transition named "%s" from place/state "%s" where found on StateMachine "%s". ',
                        $transition->getName(),
                        $from,
                        $this->getName()
                    )
                );
            }
            $transitionFromNames[$from][$transition->getName()] = true;
        }
    }
}
