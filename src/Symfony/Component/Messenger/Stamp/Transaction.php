<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Messenger\Stamp;

/**
 * Marker item to tell this message should be handled in a different transaction.
 *
 * @see \Symfony\Component\Messenger\Middleware\HandleMessageInNewTransactionMiddleware
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Transaction implements StampInterface
{
}
