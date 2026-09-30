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

use EasyCustomerManager\EasyCustomerManager;

/**
 * The order statuses whose orders count in the revenue of a customer, kept in the module
 * configuration as "2,4".
 */
final readonly class PaidOrderStatuses
{
    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return self::parse((string) EasyCustomerManager::getConfigValue(EasyCustomerManager::PAID_STATUSES_CONFIG_KEY, ''));
    }

    public function raw(): string
    {
        return implode(',', $this->ids());
    }

    public function store(string $value): void
    {
        EasyCustomerManager::setConfigValue(
            EasyCustomerManager::PAID_STATUSES_CONFIG_KEY,
            implode(',', self::parse($value)),
        );
    }

    /**
     * @return list<int>
     */
    private static function parse(string $value): array
    {
        $ids = array_map(intval(...), preg_split('/\s*,\s*/', trim($value), -1, \PREG_SPLIT_NO_EMPTY) ?: []);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
