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

use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

final class ConfigurationControllerTest extends EasyCustomerTestCase
{
    private const FORM = 'easy_customer_manager_configuration';

    public function testSavingStoresTheStatusIdsNormalised(): void
    {
        $session = $this->moduleAdminSession();

        $response = $this->save($session, ' 2 , 4,4 ', $this->formToken($session, self::FORM));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame([2, 4], $this->getService(PaidOrderStatuses::class)->ids());
    }

    public function testInvalidIdsAreRefusedAndTheStoredValueIsKept(): void
    {
        $session = $this->moduleAdminSession();
        $this->getService(PaidOrderStatuses::class)->store('3');

        $this->save($session, '2;DROP', $this->formToken($session, self::FORM));

        self::assertSame([3], $this->getService(PaidOrderStatuses::class)->ids());
        self::assertSame(['danger'], array_keys($session->getFlashBag()->peekAll()));
    }

    public function testSavingWithoutTheFormTokenChangesNothing(): void
    {
        $session = $this->moduleAdminSession();
        $this->getService(PaidOrderStatuses::class)->store('3');

        $this->save($session, '9', null);

        self::assertSame([3], $this->getService(PaidOrderStatuses::class)->ids());
    }

    public function testSavingIsRefusedWithoutTheModuleUpdateRight(): void
    {
        $session = $this->adminSession($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $this->getService(PaidOrderStatuses::class)->store('3');

        $response = $this->save($session, '9', $this->formToken($session, self::FORM));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([3], $this->getService(PaidOrderStatuses::class)->ids());
    }

    private function moduleAdminSession(): Session
    {
        return $this->adminSession($this->moduleAdmin([AccessManager::VIEW, AccessManager::UPDATE]));
    }

    private function save(Session $session, string $statusIds, ?string $token): Response
    {
        $form = ['order' => $statusIds];
        if (null !== $token) {
            $form['_token'] = $token;
        }

        $request = Request::create('/admin/module/EasyCustomerManager/save', 'POST', [self::FORM => $form]);
        $request->setSession($session);

        return $this->handleAsMainRequest($request);
    }
}
