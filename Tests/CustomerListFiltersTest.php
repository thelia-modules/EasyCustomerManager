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
use EasyCustomerManager\Service\CustomerSort;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

final class CustomerListFiltersTest extends TestCase
{
    public function testDefaults(): void
    {
        $filters = CustomerListFilters::fromInput(new InputBag());

        self::assertSame(CustomerSort::Registration, $filters->sort);
        self::assertTrue($filters->descending);
        self::assertSame(1, $filters->page);
        self::assertSame(25, $filters->pageSize);
        self::assertNull($filters->registeredFrom);
    }

    public function testAnUnknownSortColumnFallsBackInsteadOfReachingTheQuery(): void
    {
        $filters = CustomerListFilters::fromInput(new InputBag(['order' => 'email; DROP TABLE customer', 'direction' => 'sideways']));

        self::assertSame(CustomerSort::Registration, $filters->sort);
        self::assertTrue($filters->descending);
    }

    public function testPageSizeIsOneOfTheOfferedSizes(): void
    {
        self::assertSame(25, CustomerListFilters::fromInput(new InputBag(['length' => '100000']))->pageSize);
        self::assertSame(50, CustomerListFilters::fromInput(new InputBag(['length' => '50']))->pageSize);
        self::assertSame(1, CustomerListFilters::fromInput(new InputBag(['page' => '-4']))->page);
    }

    public function testAMalformedDateIsIgnored(): void
    {
        $filters = CustomerListFilters::fromInput(new InputBag(['from' => '2026-13-45', 'to' => '2026-02-03']));

        self::assertNull($filters->registeredFrom);
        self::assertSame('2026-02-03', $filters->registeredTo);
    }

    public function testASearchShorterThanThreeCharactersIsNotApplied(): void
    {
        self::assertFalse(CustomerListFilters::fromInput(new InputBag(['search' => ' ab ']))->hasSearch());
        self::assertTrue(CustomerListFilters::fromInput(new InputBag(['search' => 'abc']))->hasSearch());
    }

    public function testParametersOfOtherModulesSurviveInTheQueryParameters(): void
    {
        $filters = CustomerListFilters::fromInput(new InputBag(['search' => 'martin', 'page' => '3', 'myfilter' => 'zzz', 'empty' => '', 'nested' => ['a' => 'b'], '_token' => 'secret']));

        self::assertSame('zzz', $filters->toQueryParameters()['myfilter']);
        self::assertArrayNotHasKey('empty', $filters->toQueryParameters());
        self::assertArrayNotHasKey('nested', $filters->toQueryParameters());
        self::assertArrayNotHasKey('_token', $filters->toQueryParameters());
        self::assertArrayNotHasKey('page', $filters->toQueryParameters());
        self::assertSame('zzz', $filters->withPage(2)->toQueryParameters()['myfilter']);
    }

    public function testQueryParametersGiveTheSameListBack(): void
    {
        $filters = CustomerListFilters::fromInput(new InputBag(['search' => 'martin', 'country' => '3', 'order' => 'email', 'direction' => 'asc', 'length' => '50']));

        self::assertEquals($filters, CustomerListFilters::fromInput(new InputBag($filters->toQueryParameters())));
    }
}
