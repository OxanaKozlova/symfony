<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Http\Exception;

/**
 * When any error happens at the transport level, i.e. before or during the connection with the server.
 */
interface TransportExceptionInterface extends ExceptionInterface
{
}
