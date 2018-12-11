<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\QueuedMessageHandlingException;
use Symfony\Component\Messenger\Stamp\Transaction;

/**
 * Allow to configure messages to be handled in a new transaction.
 * I.e, messages dispatched from a handler with a Transaction stamp will actually be handled
 * once the current message being dispatched is fully handled or sent.
 *
 * For instance, using this middleware before the DoctrineTransactionMiddleware
 * means sub-dispatched messages with a Transaction item would be handled after
 * the Doctrine transaction has been committed.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class HandleMessageInNewTransactionMiddleware implements MiddlewareInterface
{
    /**
     * @var QueuedEnvelope[] A queue of messages and next middleware
     */
    private $queue = array();

    /**
     * @var bool Indicates if we are running the middleware or not. I.e, are we called during a dispatch?
     */
    private $isRunning = false;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(Transaction::class)) {
            if (!$this->isRunning) {
                throw new \LogicException(sprintf('You can only use a "%s" stamp to define a new transaction in the context of a message handling.', Transaction::class));
            }
            $this->queue[] = new QueuedEnvelope($envelope, $stack->next());

            return $envelope;
        }

        if ($this->isRunning) {
            /*
             * If come inside a second dispatch, just continue as normal.
             * We should not run the stored messages until first call is finished.
             */
            return $stack->next()->handle($envelope, $stack);
        }

        // First time we get here, mark as inside a root dispatch call:
        $this->isRunning = true;
        try {
            // Execute the whole middleware stack & message handling for main dispatch:
            $returnedEnvelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $exception) {
            /*
             * Whenever an exception occurs while handling a message that has
             * queued other messages, we drop the queued ones.
             * This is intentional since the queued commands were likely dependent
             * on the preceding command.
             */
            $this->queue = array();
            $this->isRunning = false;

            throw $exception;
        }

        // Root dispatch call is finished, dispatch stored ones for real:
        $exceptions = array();
        while (null !== $queueItem = array_shift($this->queue)) {
            try {
                // Execute the stored messages
                $queueItem->getNext()->handle($queueItem->getEnvelope(), $stack);
            } catch (\Throwable $exception) {
                // Gather all exceptions
                $exceptions[] = $exception;
            }
        }

        $this->isRunning = false;
        if (\count($exceptions) > 0) {
            throw new QueuedMessageHandlingException($exceptions);
        }

        return $returnedEnvelope;
    }
}

/**
 * @internal
 */
final class QueuedEnvelope
{
    /** @var Envelope */
    private $envelope;

    /** @var MiddlewareInterface */
    private $next;

    public function __construct(Envelope $envelope, MiddlewareInterface $next)
    {
        $this->envelope = $envelope;
        $this->next = $next;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getNext(): MiddlewareInterface
    {
        return $this->next;
    }
}
