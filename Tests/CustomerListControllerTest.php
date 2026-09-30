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

use EasyCustomerManager\Controller\CustomerListController;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderStatus;

final class CustomerListControllerTest extends EasyCustomerTestCase
{
    public function testTheListRendersForAnAdministratorAllowedToViewCustomers(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));
        $this->customer('Listed');
        $this->customer('<script>alert(1)</script>');

        $response = $this->get('/admin/easy-customer-manager?search=Listed', $session);

        self::assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 1500));
        self::assertStringContainsString('data-testid="ecm-table"', (string) $response->getContent());
        self::assertStringContainsString('Listed', (string) $response->getContent());
        self::assertStringNotContainsString('Delete the selected customers', (string) $response->getContent(), 'A view-only administrator gets no delete button.');

        $escaped = $this->get('/admin/easy-customer-manager?search=alert', $session);
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $escaped->getContent());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', (string) $escaped->getContent());
    }

    public function testFiltersAddedByAnotherModuleSurviveInThePaginationAndSortLinks(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));
        foreach (range(1, 12) as $index) {
            $this->customer(\sprintf('Linked%02d', $index));
        }

        $content = (string) $this->get('/admin/easy-customer-manager?search=Linked&length=10&myfilter=zzz', $session)->getContent();

        self::assertMatchesRegularExpression('/href="[^"]*myfilter=zzz[^"]*"[^>]*data-testid="sort-ref"/', $content);
        self::assertMatchesRegularExpression('/href="[^"]*(?:page=2[^"]*myfilter=zzz|myfilter=zzz[^"]*page=2)[^"]*"/', $content);
        self::assertMatchesRegularExpression('/name="return\[myfilter\]" value="zzz"/', $content);
    }

    public function testAWarningShowsUntilPaidStatusesAreConfigured(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));

        $this->getService(PaidOrderStatuses::class)->store('');
        self::assertStringContainsString('ecm-paid-statuses-warning', (string) $this->get('/admin/easy-customer-manager', $session)->getContent());

        $this->getService(PaidOrderStatuses::class)->store((string) $this->paidStatusId());
        self::assertStringNotContainsString('ecm-paid-statuses-warning', (string) $this->get('/admin/easy-customer-manager', $session)->getContent());
    }

    public function testDeletingComesBackToTheSamePageWithTheSameFilters(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::DELETE]]));

        $response = $this->post('/admin/easy-customer-manager/delete-selected', [
            'customer_ids' => [],
            '_token' => $this->tokenFor($session),
            'return' => ['search' => 'abc', 'myfilter' => 'zzz', 'page' => '3'],
        ], $session);

        self::assertSame('/admin/easy-customer-manager?myfilter=zzz&search=abc&order=created_at&direction=desc&length=25&page=3', $response->headers->get('Location'));
    }

    public function testTheListIsRefusedWithoutTheViewRight(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::UPDATE, AccessManager::DELETE]]));

        self::assertSame(403, $this->get('/admin/easy-customer-manager', $session)->getStatusCode());
    }

    public function testDeletingWithoutTheTokenDeletesNothing(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::DELETE]]));
        $customer = $this->customer('Keep');

        $response = $this->post('/admin/easy-customer-manager/delete-selected', ['customer_ids' => [$customer->getId()]], $session);

        self::assertSame(302, $response->getStatusCode());
        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));

        $wrongToken = $this->post('/admin/easy-customer-manager/delete-selected', ['customer_ids' => [$customer->getId()], '_token' => 'forged'], $session);
        self::assertSame(302, $wrongToken->getStatusCode());
        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
    }

    public function testDeletingRemovesTheCustomersWithoutOrdersAndKeepsTheOthers(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::DELETE]]));
        $withoutOrder = $this->customer('Removable');
        $withOrder = $this->customer('Ordering');
        $this->order($withOrder, OrderStatus::CODE_PAID, 10.0);

        $response = $this->post('/admin/easy-customer-manager/delete-selected', [
            'customer_ids' => [$withoutOrder->getId(), $withOrder->getId(), 'abc', '-1'],
            '_token' => $this->tokenFor($session),
            'return' => ['search' => 'Ordering', 'order' => 'email'],
        ], $session);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/easy-customer-manager?search=Ordering&order=email&direction=desc&length=25', $response->headers->get('Location'));
        self::assertNull(CustomerQuery::create()->findPk($withoutOrder->getId()));
        self::assertNotNull(CustomerQuery::create()->findPk($withOrder->getId()));
    }

    public function testDeletingIsRefusedWithoutTheDeleteRight(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $customer = $this->customer('Protected');

        $response = $this->post('/admin/easy-customer-manager/delete-selected', [
            'customer_ids' => [$customer->getId()],
            '_token' => $this->tokenFor($session),
        ], $session);

        self::assertSame(403, $response->getStatusCode());
        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
    }

    public function testTheDeletionRouteAcceptsOnlyPost(): void
    {
        $route = (new \ReflectionMethod(CustomerListController::class, 'deleteSelected'))->getAttributes(Route::class)[0]->newInstance();

        self::assertSame(['POST'], $route->methods);
    }

    public function testCustomerIdsSentInTheQueryStringAreNotRead(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::DELETE]]));
        $customer = $this->customer('QueryProof');

        $request = Request::create('/admin/easy-customer-manager/delete-selected?customer_ids[]='.$customer->getId(), 'POST', ['_token' => $this->tokenFor($session)]);
        $request->setSession($session);
        $this->handleAsMainRequest($request);

        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
    }

    private function get(string $uri, \Thelia\Core\HttpFoundation\Session\Session $session): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($uri, 'GET');
        $request->setSession($session);

        return $this->handleAsMainRequest($request);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function post(string $uri, array $parameters, \Thelia\Core\HttpFoundation\Session\Session $session): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($uri, 'POST', $parameters);
        $request->setSession($session);

        return $this->handleAsMainRequest($request);
    }
}
