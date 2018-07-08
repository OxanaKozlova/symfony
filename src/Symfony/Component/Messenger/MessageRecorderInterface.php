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
    public function fetch(): array;

    /**
     * Erase messages that were recorded since the last call to eraseMessages().
     */
    public function reset();

    /**
     * Record a message.
     *
     * @param object $message
     */
    public function record($message);
}
