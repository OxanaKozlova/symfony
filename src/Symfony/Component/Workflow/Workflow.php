<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\UniqueTransitionOutputInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Workflow extends PetriNet
{
    public function __construct(
        Definition $definition,
        MarkingStoreInterface $markingStore,
        EventDispatcherInterface $dispatcher = null,
        $name = 'unnamed'
    ) {
        parent::__construct($definition, $markingStore, $dispatcher, $name);

        // If the marking can contain only one place, we should control the definition
        if ($markingStore instanceof UniqueTransitionOutputInterface) {
            /** @var Transition $transition */
            foreach ($definition->getTransitions() as $transition) {
                if (1 < count($transition->getTos())) {
                    throw new LogicException(
                        sprintf(
                            'The marking store (%s) of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.',
                            get_class($markingStore),
                            $this->getName(),
                            $transition->getName(),
                            count($transition->getTos())
                        )
                    );
                }
            }
        }
    }
}
