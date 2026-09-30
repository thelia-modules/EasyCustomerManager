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

final readonly class CustomerListPage
{
    /**
     * @param list<CustomerListRow> $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public int $pageCount,
        public CustomerListFilters $filters,
    ) {
    }
}
