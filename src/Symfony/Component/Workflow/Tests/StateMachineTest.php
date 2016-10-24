<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\StateMachineValidator;

class StateMachineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage A transition from a place/state must have an unique name.
     */
    public function testConstructorWithMultipleTransitionWithSameNameShareInput()
    {
        $places = array('a', 'b', 'c');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'a', 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');
    }

    public function testCanWithStateMachineMarkingStore()
    {
        $places = array('a', 'b', 'c', 'd');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'd', 'b');
        $transitions[] = new Transition('t2', 'b', 'c');
        $transitions[] = new Transition('t3', 'b', 'd');
        $definition = new Definition($places, $transitions);

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place a you should be able to apply t1
        $subject->marking = 'a';
        $this->assertTrue($net->can($subject, 't1'));
        $subject->marking = 'd';
        $this->assertTrue($net->can($subject, 't1'));

        $subject->marking = 'b';
        $this->assertFalse($net->can($subject, 't1'));
    }

    public function testCanWithMultipleTos()
    {
        $places = array('a', 'b', 'c', 'd');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'c', 'd');
        $definition = new Definition($places, $transitions);

        $net = new StateMachine($definition);
        $subject = new \stdClass();

        // If you are in place a you should be able to apply t1
        $subject->marking = 'a';
        $this->assertTrue($net->can($subject, 't1'));

        $subject->marking = 'c';
        $this->assertTrue($net->can($subject, 't1'));
    }
}
