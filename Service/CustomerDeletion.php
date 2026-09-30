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

final readonly class CustomerDeletion
{
    /**
     * @param list<int> $deletedIds
     * @param list<int> $keptIds    customers the core refused to delete, because they have orders
     * @param list<int> $failedIds  customers whose deletion failed for another reason (logged)
     */
    public function __construct(
        public array $deletedIds,
        public array $keptIds,
        public array $failedIds = [],
    ) {
    }
}
