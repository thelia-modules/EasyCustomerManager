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

final readonly class CustomerListRow
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $lastname,
        public string $firstname,
        public string $email,
        public string $registeredAt,
        public ?string $lastOrderAt,
        public ?string $lastOrderAmount,
        public string $revenue,
    ) {
    }
}
