<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\DependencyInjection\ResettableContainerInterface;


/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class MessageRecorder implements MessageRecorderInterface
{
    private $messages = array();

    /**
     * {@inheritdoc}
     */
    public function fetch(): array
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->messages = array();
    }

    /**
     * {@inheritdoc}
     */
    public function record($message): void
    {
        $this->messages[] = $message;
    }
}
