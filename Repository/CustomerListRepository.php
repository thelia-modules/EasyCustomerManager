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

namespace EasyCustomerManager\Repository;

use EasyCustomerManager\Event\BeforeFilterEvent;
use EasyCustomerManager\Service\CustomerListFilters;
use EasyCustomerManager\Service\CustomerSort;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * Every query of the list: parameters are always bound, the sort column comes from an enum and
 * the customers are read one page at a time.
 */
final readonly class CustomerListRepository
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * The customers matching the filters, without pagination.
     */
    public function filteredQuery(CustomerListFilters $filters, Request $request): CustomerQuery
    {
        $query = CustomerQuery::create();

        if ($filters->hasSearch()) {
            $pattern = '%'.addcslashes($filters->search, '\\%_').'%';

            $conditions = [];
            foreach ([CustomerTableMap::COL_REF, CustomerTableMap::COL_ID, CustomerTableMap::COL_FIRSTNAME, CustomerTableMap::COL_LASTNAME, CustomerTableMap::COL_EMAIL] as $index => $column) {
                $query->condition('search'.$index, $column.' LIKE ?', $pattern, \PDO::PARAM_STR);
                $conditions[] = 'search'.$index;
            }
            $query->where($conditions, Criteria::LOGICAL_OR);
        }

        if ($filters->countryId > 0) {
            $query->where(
                'EXISTS (SELECT 1 FROM address WHERE address.customer_id = '.CustomerTableMap::COL_ID.' AND address.country_id = ?)',
                $filters->countryId,
                \PDO::PARAM_INT,
            );
        }

        if (null !== $filters->registeredFrom) {
            $query->filterByCreatedAt($filters->registeredFrom.' 00:00:00', Criteria::GREATER_EQUAL);
        }

        if (null !== $filters->registeredTo) {
            $query->filterByCreatedAt($filters->registeredTo.' 23:59:59', Criteria::LESS_EQUAL);
        }

        $this->dispatcher->dispatch(new BeforeFilterEvent($request, $query), BeforeFilterEvent::CUSTOMER_MANAGER_BEFORE_FILTER);

        return $query;
    }

    /**
     * @return list<Customer>
     */
    public function page(CustomerQuery $query, CustomerListFilters $filters): array
    {
        $direction = $filters->descending ? Criteria::DESC : Criteria::ASC;

        match ($filters->sort) {
            CustomerSort::Reference => $query->orderBy(CustomerTableMap::COL_REF, $direction),
            CustomerSort::Lastname => $query->orderBy(CustomerTableMap::COL_LASTNAME, $direction),
            CustomerSort::Firstname => $query->orderBy(CustomerTableMap::COL_FIRSTNAME, $direction),
            CustomerSort::Email => $query->orderBy(CustomerTableMap::COL_EMAIL, $direction),
            CustomerSort::Registration => $query->orderBy(CustomerTableMap::COL_CREATED_AT, $direction),
            CustomerSort::LastOrder => $query
                ->withColumn('(SELECT MAX(last_order.created_at) FROM `order` last_order WHERE last_order.customer_id = '.CustomerTableMap::COL_ID.')', 'LastOrderAt')
                ->orderBy('LastOrderAt', $direction),
        };

        // The id keeps two customers with the same sort value in the same order from one page to the next.
        $query->orderBy(CustomerTableMap::COL_ID, Criteria::ASC);

        return array_values(iterator_to_array(
            $query->offset(($filters->page - 1) * $filters->pageSize)->limit($filters->pageSize)->find(),
        ));
    }

    /**
     * Id, customer, currency, status and date of every order of the customers, in one query.
     *
     * @param list<int> $customerIds
     *
     * @return list<array{Id: int, CustomerId: int, CurrencyId: int, StatusId: int, CreatedAt: string}>
     */
    public function ordersOf(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }

        /** @var list<array{Id: int, CustomerId: int, CurrencyId: int, StatusId: int, CreatedAt: string}> $orders */
        $orders = OrderQuery::create()
            ->filterByCustomerId($customerIds, Criteria::IN)
            ->orderById()
            ->select(['Id', 'CustomerId', 'CurrencyId', 'StatusId', 'CreatedAt'])
            ->find()
            ->getArrayCopy();

        return $orders;
    }

    /**
     * @param list<int> $orderIds
     *
     * @return array<int, Order> indexed by order id
     */
    public function ordersById(array $orderIds): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $orders = [];
        foreach (OrderQuery::create()->filterById($orderIds, Criteria::IN)->find() as $order) {
            $orders[(int) $order->getId()] = $order;
        }

        return $orders;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function countries(string $locale): array
    {
        $countries = [];
        /** @var Country $country */
        foreach (CountryQuery::create()->joinWithI18n($locale)->find() as $country) {
            $countries[] = ['id' => (int) $country->getId(), 'title' => (string) $country->setLocale($locale)->getTitle()];
        }

        usort($countries, static fn (array $left, array $right): int => strcasecmp($left['title'], $right['title']));

        return $countries;
    }
}
