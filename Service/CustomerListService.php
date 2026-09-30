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

use EasyCustomerManager\Repository\CustomerListRepository;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Customer;
use Thelia\Tools\MoneyFormat;

/**
 * Builds one page of the customer list: the customers are read one page at a time and their orders
 * with a single query for the whole page, whatever the number of customers.
 */
final readonly class CustomerListService
{
    private const DATE_FORMAT = 'd/m/y H:i:s';

    public function __construct(
        private CustomerListRepository $repository,
        private PaidOrderStatuses $paidOrderStatuses,
    ) {
    }

    public function page(CustomerListFilters $filters, Request $request): CustomerListPage
    {
        $query = $this->repository->filteredQuery($filters, $request);
        $total = $query->count();
        $pageCount = max(1, (int) ceil($total / $filters->pageSize));
        $filters = $filters->withPage(min($filters->page, $pageCount));

        $customers = $this->repository->page($query, $filters);
        $orders = $this->repository->ordersOf(array_map(static fn (Customer $customer): int => (int) $customer->getId(), $customers));

        return new CustomerListPage($this->rows($customers, $orders, $request), $total, $pageCount, $filters);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function countries(string $locale): array
    {
        return $this->repository->countries($locale);
    }

    /**
     * @param list<Customer>                                                                           $customers
     * @param list<array{Id: int, CustomerId: int, CurrencyId: int, StatusId: int, CreatedAt: string}> $orders    ordered by id
     *
     * @return list<CustomerListRow>
     */
    private function rows(array $customers, array $orders, Request $request): array
    {
        $paidStatusIds = $this->paidOrderStatuses->ids();
        $latestOrders = [];
        $firstCurrencies = [];
        $paidOrderIds = [];
        $orderIdsToPrice = [];

        foreach ($orders as $order) {
            $customerId = $order['CustomerId'];
            $firstCurrencies[$customerId] ??= $order['CurrencyId'];

            if (!isset($latestOrders[$customerId]) || $order['CreatedAt'] >= $latestOrders[$customerId]['CreatedAt']) {
                $latestOrders[$customerId] = $order;
            }

            if (\in_array($order['StatusId'], $paidStatusIds, true)) {
                $paidOrderIds[$customerId][] = $order['Id'];
                $orderIdsToPrice[] = $order['Id'];
            }
        }

        foreach ($latestOrders as $latestOrder) {
            $orderIdsToPrice[] = $latestOrder['Id'];
        }

        $amounts = [];
        foreach ($this->repository->ordersById(array_values(array_unique($orderIdsToPrice))) as $orderId => $order) {
            $amounts[$orderId] = $order->getTotalAmount();
        }

        $money = MoneyFormat::getInstance($request);

        $rows = [];
        foreach ($customers as $customer) {
            $customerId = (int) $customer->getId();
            $latestOrder = $latestOrders[$customerId] ?? null;

            $revenue = 0.0;
            foreach ($paidOrderIds[$customerId] ?? [] as $orderId) {
                $revenue += $amounts[$orderId] ?? 0.0;
            }

            $rows[] = new CustomerListRow(
                id: $customerId,
                reference: (string) $customer->getRef(),
                lastname: (string) $customer->getLastname(),
                firstname: (string) $customer->getFirstname(),
                email: (string) $customer->getEmail(),
                registeredAt: $customer->getCreatedAt(self::DATE_FORMAT),
                lastOrderAt: null === $latestOrder ? null : (new \DateTimeImmutable($latestOrder['CreatedAt']))->format(self::DATE_FORMAT),
                lastOrderAmount: null === $latestOrder ? null : $money->formatByCurrency($amounts[$latestOrder['Id']] ?? 0.0, 2, '.', ' ', $latestOrder['CurrencyId']),
                revenue: 0.0 === $revenue ? '0' : $money->formatByCurrency($revenue, 2, '.', ' ', $firstCurrencies[$customerId]),
            );
        }

        return $rows;
    }
}
