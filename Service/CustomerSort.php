<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EasyCustomerManager\Service;

/**
 * The columns the list can be sorted on. The case value is what the `order` query parameter carries;
 * nothing else ever reaches the ORDER BY clause.
 */
enum CustomerSort: string
{
    case Reference = 'ref';
    case Lastname = 'lastname';
    case Firstname = 'firstname';
    case Email = 'email';
    case Registration = 'created_at';
    case LastOrder = 'last_order';

    public static function fromInput(mixed $value): self
    {
        return \is_string($value) ? (self::tryFrom($value) ?? self::Registration) : self::Registration;
    }
}
