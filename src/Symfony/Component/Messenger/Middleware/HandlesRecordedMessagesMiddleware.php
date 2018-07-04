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

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessageRecorderInterface;

/**
 * A middleware that takes all recorded messages and dispatch them to the bus.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class HandlesRecordedMessagesMiddleware implements MiddlewareInterface
{
    private $messageRecorder;
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus, MessageRecorderInterface $messageRecorder)
    {
        $this->messageRecorder = $messageRecorder;
        $this->messageBus = $messageBus;
    }

    public function handle($message, callable $next)
    {
        try {
            $next($message);
        } catch (\Throwable $exception) {
            $this->messageRecorder->eraseMessages();

            throw $exception;
        }

        $recordedMessages = $this->messageRecorder->recordedMessages();
        $this->messageRecorder->eraseMessages();

        foreach ($recordedMessages as $recordedMessage) {
            $this->messageBus->dispatch($recordedMessage);
        }
    }
}
