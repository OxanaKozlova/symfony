<?php

namespace Symfony\Component\Messenger;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
interface MessageRecorderInterface
{
    /**
     * Fetch recorded messages.
     *
     * @return object[]
     */
    public function recordedMessages(): array;

    /**
     * Erase messages that were recorded since the last call to eraseMessages().
     */
    public function eraseMessages(): void;

    /**
     * Record a message.
     *
     * @param object $message
     */
    public function record($message): void;
}
