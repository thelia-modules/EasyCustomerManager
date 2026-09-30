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

use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

/**
 * The order statuses with the number of orders in each, to help find the ids to count as paid.
 */
final readonly class OrderStatusOverview
{
    /**
     * @return list<array{id: int, code: string, color: string, title: string, orderCount: int}>
     */
    public function all(string $locale): array
    {
        $counts = [];
        $rows = OrderQuery::create()
            ->groupByStatusId()
            ->withColumn('COUNT('.OrderTableMap::COL_ID.')', 'OrderCount')
            ->select(['StatusId', 'OrderCount'])
            ->find();
        foreach ($rows as $row) {
            $counts[(int) $row['StatusId']] = (int) $row['OrderCount'];
        }

        $statuses = [];
        foreach (OrderStatusQuery::create()->joinWithI18n($locale)->orderByPosition()->find() as $status) {
            $status->setLocale($locale);
            $statuses[] = [
                'id' => (int) $status->getId(),
                'code' => (string) $status->getCode(),
                'color' => (string) $status->getColor(),
                'title' => (string) $status->getTitle(),
                'orderCount' => (int) ($counts[$status->getId()] ?? 0),
            ];
        }

        return $statuses;
    }
}
