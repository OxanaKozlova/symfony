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

use Symfony\Contracts\Http\ResponseInterface;

/**
 * When a 5xx response is returned.
 */
interface ServerExceptionInterface extends ResponseInterface, ExceptionInterface
{
}
