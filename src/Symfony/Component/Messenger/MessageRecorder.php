<?php

namespace Symfony\Component\Messenger;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class MessageRecorder implements MessageRecorderInterface
{
    private $messages = [];

    /**
     * {@inheritdoc}
     */
    public function recordedMessages(): array
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseMessages(): void
    {
        $this->messages = [];
    }

    /**
     * {@inheritdoc}
     */
    public function record($message): void
    {
        $this->messages[] = $message;
    }
}
