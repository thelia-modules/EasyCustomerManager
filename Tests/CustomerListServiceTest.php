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

namespace EasyCustomerManager\Tests;

use EasyCustomerManager\Service\CustomerListFilters;
use EasyCustomerManager\Service\CustomerListRow;
use EasyCustomerManager\Service\CustomerListService;
use EasyCustomerManager\Service\CustomerSort;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\InputBag;
use Thelia\Model\OrderStatus;
use Thelia\Tools\MoneyFormat;

final class CustomerListServiceTest extends EasyCustomerTestCase
{
    private CustomerListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(CustomerListService::class);
    }

    public function testSearchMatchesNamesAndTreatsWildcardsAsText(): void
    {
        $this->customer('Zorglub');
        $this->customer('Zorglax');
        $this->customer('Other');

        self::assertSame(['Zorglax', 'Zorglub'], $this->lastnames(['search' => 'Zorgl', 'order' => 'lastname', 'direction' => 'asc']));
        self::assertSame([], $this->lastnames(['search' => '%%%']));
        self::assertSame([], $this->lastnames(['search' => 'Z_rglub']));
    }

    public function testCountryAndRegistrationDateFilters(): void
    {
        $france = $this->fixtures->country();
        $germany = $this->fixtures->country(['isocode' => 'DE', 'isoalpha2' => 'DE', 'isoalpha3' => 'DEU']);
        $frenchCustomer = $this->customer('Frenchy');
        $germanCustomer = $this->customer('Germanic');
        $this->fixtures->address($frenchCustomer, $france);
        $this->fixtures->address($germanCustomer, $germany);
        $this->fixtures->address($germanCustomer, $germany);
        $germanCustomer->setCreatedAt('2026-03-10 12:00:00')->save();
        $frenchCustomer->setCreatedAt('2026-05-10 12:00:00')->save();

        self::assertSame(['Germanic'], $this->lastnames(['country' => (string) $germany->getId()]));
        self::assertSame(['Frenchy'], $this->lastnames(['from' => '2026-05-10']));
        self::assertSame(['Germanic'], $this->lastnames(['to' => '2026-03-10']));
        self::assertSame(['Germanic'], $this->lastnames(['from' => '2026-03-01', 'to' => '2026-04-01']));
    }

    public function testSortByLastOrderDateAndPagination(): void
    {
        $names = [];
        foreach (range(1, 12) as $index) {
            $customer = $this->customer(\sprintf('Paged%02d', $index));
            $this->order($customer, OrderStatus::CODE_NOT_PAID, 10.0, \sprintf('2026-02-%02d 10:00:00', $index));
            $names[] = $customer->getLastname();
        }

        $filters = ['search' => 'Paged', 'order' => 'last_order', 'direction' => 'desc', 'length' => '10'];

        $first = $this->service->page($this->filters($filters), $this->currentRequest());
        self::assertSame(12, $first->total);
        self::assertSame(2, $first->pageCount);
        self::assertSame(array_slice(array_reverse($names), 0, 10), array_map(static fn (CustomerListRow $row): string => $row->lastname, $first->rows));

        $second = $this->service->page($this->filters($filters + ['page' => '2']), $this->currentRequest());
        self::assertSame(['Paged02', 'Paged01'], array_map(static fn (CustomerListRow $row): string => $row->lastname, $second->rows));

        $beyond = $this->service->page($this->filters($filters + ['page' => '9']), $this->currentRequest());
        self::assertSame(2, $beyond->filters->page);
        self::assertCount(2, $beyond->rows);
    }

    public function testRevenueCountsOnlyTheConfiguredStatusesAndTheLatestOrderIsShown(): void
    {
        $customer = $this->customer('Revenue');
        $paid = $this->order($customer, OrderStatus::CODE_PAID, 100.0, '2026-01-01 10:00:00');
        $this->order($customer, OrderStatus::CODE_NOT_PAID, 500.0, '2026-02-01 10:00:00');
        $latestPaid = $this->order($customer, OrderStatus::CODE_PAID, 50.0, '2026-01-15 10:00:00');
        $this->getService(PaidOrderStatuses::class)->store((string) $this->paidStatusId());

        $row = $this->service->page($this->filters(['search' => 'Revenue']), $this->currentRequest())->rows[0];

        $money = MoneyFormat::getInstance($this->currentRequest());
        $expectedRevenue = $paid->getTotalAmount() + $latestPaid->getTotalAmount();
        self::assertSame(180.0, $expectedRevenue);
        self::assertSame($money->formatByCurrency($expectedRevenue, 2, '.', ' ', $paid->getCurrencyId()), $row->revenue);
        self::assertSame('01/02/26 10:00:00', $row->lastOrderAt);
        self::assertSame($money->formatByCurrency(500.0 * 1.2, 2, '.', ' ', $paid->getCurrencyId()), $row->lastOrderAmount);
    }

    public function testACustomerWithoutOrderShowsNoOrderAndNoRevenue(): void
    {
        $this->customer('Fresh');

        $row = $this->service->page($this->filters(['search' => 'Fresh']), $this->currentRequest())->rows[0];

        self::assertNull($row->lastOrderAt);
        self::assertNull($row->lastOrderAmount);
        self::assertSame('0', $row->revenue);
    }

    public function testNothingIsPaidUntilStatusesAreConfigured(): void
    {
        $customer = $this->customer('Unconfigured');
        $this->order($customer, OrderStatus::CODE_PAID, 100.0);
        $this->getService(PaidOrderStatuses::class)->store('');

        self::assertSame('0', $this->service->page($this->filters(['search' => 'Unconfigured']), $this->currentRequest())->rows[0]->revenue);
    }

    public function testAPageOfCustomersWithoutOrdersCostsTheSameNumberOfQueriesWhateverTheNumberOfCustomers(): void
    {
        foreach (range(1, 2) as $index) {
            $this->customer('Few'.$index);
        }
        foreach (range(1, 12) as $index) {
            $this->customer('Many'.$index);
        }

        $queriesForTwo = $this->countQueries(fn () => $this->service->page($this->filters(['search' => 'Few']), $this->currentRequest()));
        $queriesForTwelve = $this->countQueries(fn () => $this->service->page($this->filters(['search' => 'Many']), $this->currentRequest()));

        self::assertSame($queriesForTwo, $queriesForTwelve);
    }

    public function testTheOrdersOfAPageCostOnlyWhatTheCorePricingCostsPerOrder(): void
    {
        $this->getService(PaidOrderStatuses::class)->store((string) $this->paidStatusId());
        foreach (range(1, 2) as $index) {
            $this->order($this->customer('Few'.$index), OrderStatus::CODE_PAID, 10.0);
        }
        foreach (range(1, 12) as $index) {
            $this->order($this->customer('Many'.$index), OrderStatus::CODE_PAID, 10.0);
        }
        $priced = $this->order($this->customer('Alone'), OrderStatus::CODE_PAID, 10.0);
        $warmUp = $this->order($this->customer('Warm'), OrderStatus::CODE_PAID, 10.0);

        // The pool the shop runs with: the currency read to format an amount is read once.
        Propel::enableInstancePooling();
        try {
            // What is read once for the whole process (tax engine, configuration) is not counted.
            $warmUp->getTotalAmount();
            $this->service->page($this->filters(['search' => 'Warm']), $this->currentRequest());

            $queriesForOneOrder = $this->countQueries(fn () => $priced->getTotalAmount());
            $queriesForTwo = $this->countQueries(fn () => $this->service->page($this->filters(['search' => 'Few']), $this->currentRequest()));
            $queriesForTwelve = $this->countQueries(fn () => $this->service->page($this->filters(['search' => 'Many']), $this->currentRequest()));
        } finally {
            Propel::disableInstancePooling();
        }

        self::assertGreaterThan(0, $queriesForOneOrder);
        self::assertSame($queriesForTwo + 10 * $queriesForOneOrder, $queriesForTwelve, \sprintf('one order: %d, two customers: %d, twelve customers: %d', $queriesForOneOrder, $queriesForTwo, $queriesForTwelve));
    }

    /**
     * @param array<string, string> $input
     *
     * @return list<string>
     */
    private function lastnames(array $input): array
    {
        return array_map(
            static fn (CustomerListRow $row): string => $row->lastname,
            $this->service->page($this->filters($input), $this->currentRequest())->rows,
        );
    }

    /**
     * @param array<string, string> $input
     */
    private function filters(array $input): CustomerListFilters
    {
        return CustomerListFilters::fromInput(new InputBag($input + ['order' => CustomerSort::Lastname->value, 'direction' => 'asc']));
    }

    private function countQueries(callable $callable): int
    {
        $connection = Propel::getReadConnection('TheliaMain');
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        $callable();

        return $connection->getQueryCount() - $before;
    }
}
