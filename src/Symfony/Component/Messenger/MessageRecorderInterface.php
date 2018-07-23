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

use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
interface MessageRecorderInterface extends ResetInterface
{
    /**
     * Fetch recorded messages.
     *
     * @return object[]
     */
    public function fetch(): array;

    /**
     * Record a message.
     *
     * @param object $message
     */
    public function record($message);
}
