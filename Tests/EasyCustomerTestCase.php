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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\TokenProvider;

/**
 * Runs on the test database of the project, never on the shop's: the database name must end with `_test`.
 * The requests go through the kernel itself: symfony/browser-kit is not required by the module.
 */
abstract class EasyCustomerTestCase extends IntegrationTestCase
{
    protected FixtureFactory $fixtures;

    private static int $sequence = 0;

    protected function setUp(): void
    {
        $databaseName = $_SERVER['DATABASE_NAME'] ?? getenv('DATABASE_NAME');
        if (!\is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
            self::fail(\sprintf('Refusing to run on the database "%s": use a *_test database.', (string) $databaseName));
        }

        parent::setUp();

        $connectedDatabase = $this->getPropelConnection()->query('SELECT DATABASE()')->fetchColumn();
        if (!\is_string($connectedDatabase) || $connectedDatabase !== $databaseName) {
            self::fail(\sprintf('Refusing to run: the connection points at "%s", not at "%s".', (string) $connectedDatabase, $databaseName));
        }

        $this->fixtures = $this->createFixtureFactory();
    }

    protected function customer(string $lastname, array $overrides = []): Customer
    {
        ++self::$sequence;

        return $this->fixtures->customer($this->fixtures->customerTitle(), $overrides + [
            'lastname' => $lastname,
            'firstname' => 'Test'.self::$sequence,
            'email' => 'ecm'.self::$sequence.'@example.test',
        ]);
    }

    /**
     * An order with one line of the given amount (tax excluded, 20 % tax), created at the given date.
     */
    protected function order(Customer $customer, string $statusCode, float $untaxedAmount, string $createdAt = '2026-01-01 10:00:00'): Order
    {
        $order = $this->fixtures->order($customer, ['statusCode' => $statusCode]);

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('REF')
            ->setProductSaleElementsRef('PSE')
            ->setTitle('Line')
            ->setQuantity(1.0)
            ->setPrice((string) $untaxedAmount)
            ->setPromoPrice((string) $untaxedAmount)
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        (new OrderProductTax())
            ->setOrderProductId($orderProduct->getId())
            ->setTitle('VAT')
            ->setAmount((string) round($untaxedAmount * 0.2, 2))
            ->setPromoAmount((string) round($untaxedAmount * 0.2, 2))
            ->save($this->getPropelConnection());

        $order->setCreatedAt($createdAt)->save($this->getPropelConnection());

        return $order;
    }

    protected function paidStatusId(): int
    {
        return $this->statusId(OrderStatus::CODE_PAID);
    }

    protected function statusId(string $code): int
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status);

        return (int) $status->getId();
    }

    /**
     * An administrator restricted to the given accesses on the module: the module resource and the module itself.
     *
     * @param list<string> $accesses
     */
    protected function moduleAdmin(array $accesses): Admin
    {
        $admin = $this->fixtures->restrictedAdmin([AdminResources::MODULE => $accesses]);
        $module = ModuleQuery::create()->findOneByCode('EasyCustomerManager');
        self::assertNotNull($module, 'The module is not registered in the test database.');

        $accessManager = new AccessManager(0);
        $accessManager->build($accesses);
        (new ProfileModule())
            ->setProfileId($admin->getProfileId())
            ->setModuleId($module->getId())
            ->setAccess($accessManager->getAccessValue())
            ->save($this->getPropelConnection());

        return $admin;
    }

    /**
     * The request of the test, which carries a session: the money formatting reads the language from it.
     */
    protected function currentRequest(): Request
    {
        $request = $this->requestStack()->getCurrentRequest();
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    protected function adminSession(Admin $admin): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->setAdminUser($admin);
        $lang = \Thelia\Model\LangQuery::create()->findOneByLocale('en_US');
        self::assertNotNull($lang, 'The test database has no en_US language.');
        $session->set('thelia.current.admin_lang', $lang);
        $session->setAdminEditionLang($lang);

        return $session;
    }

    /**
     * The token of a Symfony form, bound to the session of the request.
     */
    protected function formToken(Session $session, string $formName): string
    {
        $request = Request::create('/admin');
        $request->setSession($session);

        $requestStack = $this->requestStack();
        $requestStack->push($request);
        try {
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');
            self::assertInstanceOf(CsrfTokenManagerInterface::class, $tokenManager);

            return $tokenManager->getToken($formName)->getValue();
        } finally {
            $requestStack->pop();
        }
    }

    protected function tokenFor(Session $session): string
    {
        $request = Request::create('/admin');
        $request->setSession($session);

        $requestStack = $this->requestStack();
        $requestStack->push($request);
        try {
            return (string) $this->getService(TokenProvider::class)->assignToken();
        } finally {
            $requestStack->pop();
        }
    }

    /**
     * IntegrationTestCase pushes a synthetic request: it is taken off the stack while the
     * kernel handles this one, so that this request is the main request the controller reads.
     */
    protected function handleAsMainRequest(Request $request): Response
    {
        $requestStack = $this->requestStack();
        $pushedRequests = [];
        while (null !== $pushedRequest = $requestStack->pop()) {
            $pushedRequests[] = $pushedRequest;
        }

        try {
            return self::$kernel->handle($request);
        } finally {
            while (null !== $requestStack->pop()) {
            }
            foreach (array_reverse($pushedRequests) as $pushedRequest) {
                $requestStack->push($pushedRequest);
            }
        }
    }

    protected function requestStack(): RequestStack
    {
        $requestStack = static::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);

        return $requestStack;
    }
}
