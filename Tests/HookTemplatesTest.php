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

use EasyCustomerManager\Form\Configuration;
use EasyCustomerManager\Service\OrderStatusOverview;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The hook templates are only rendered inside the back-office pages, whose hook rows are written when the
 * container is compiled: they are rendered here directly, with what the hooks hand them.
 */
final class HookTemplatesTest extends EasyCustomerTestCase
{
    public function testTheConfigurationFragmentRendersTheFormAndTheOrderStatuses(): void
    {
        $session = $this->adminSession($this->moduleAdmin([AccessManager::VIEW, AccessManager::UPDATE]));
        $request = Request::create('/admin/module/EasyCustomerManager');
        $request->setSession($session);
        $this->requestStack()->push($request);

        try {
            $this->getService(PaidOrderStatuses::class)->store('2,4');
            $form = $this->getService(TheliaFormFactory::class)->createForm(Configuration::class, data: ['order' => '2,4']);

            $html = $this->twig()->render('EasyCustomerManager/config/module-config.html.twig', [
                'form' => $form->createView()->getView(),
                'order_statuses' => $this->getService(OrderStatusOverview::class)->all('en_US'),
            ]);
        } finally {
            $this->requestStack()->pop();
        }

        self::assertStringContainsString('action="/admin/module/EasyCustomerManager/save"', $html);
        self::assertStringContainsString('name="easy_customer_manager_configuration[order]"', $html);
        self::assertStringContainsString('value="2,4"', $html);
        self::assertStringContainsString('name="easy_customer_manager_configuration[_token]"', $html);
        self::assertStringContainsString('class="form-control', $html, 'The form is themed for the back-office.');
        self::assertStringContainsString('status_ids%5B0%5D='.$this->paidStatusId(), $html, 'The order count links to the order list filtered by status.');
        self::assertStringContainsString('>paid<', $html, 'The seeded order statuses are listed with their code.');
    }

    public function testTheMenuEntryPointsToTheListForAnAdministratorWhoCanViewCustomers(): void
    {
        $html = $this->renderMenuAs($this->fixtures->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));

        self::assertStringContainsString('href="/admin/easy-customer-manager"', $html);
        self::assertStringContainsString('nav-link active', $html);
    }

    public function testTheMenuEntryIsHiddenWithoutTheViewRight(): void
    {
        self::assertSame('', trim($this->renderMenuAs($this->fixtures->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]))));
    }

    private function renderMenuAs(Admin $admin): string
    {
        $request = Request::create('/admin');
        $request->setSession($this->adminSession($admin));

        // The security context reads the administrator from the main request, the bottom of the stack.
        $requestStack = $this->requestStack();
        $pushedRequests = [];
        while (null !== $pushedRequest = $requestStack->pop()) {
            $pushedRequests[] = $pushedRequest;
        }
        $requestStack->push($request);

        try {
            return $this->twig()->render('EasyCustomerManager/hook/main.in.top.menu.items.html.twig', ['is_active' => true]);
        } finally {
            $requestStack->pop();
            foreach (array_reverse($pushedRequests) as $pushedRequest) {
                $requestStack->push($pushedRequest);
            }
        }
    }

    /**
     * The back-office Twig environment, where the module templates are found the way the hooks find them.
     */
    private function twig(): Environment
    {
        $twig = $this->getService(Environment::class);
        $loader = $twig->getLoader();
        self::assertInstanceOf(FilesystemLoader::class, $loader);
        $loader->addPath(\dirname(__DIR__).'/templates/backOffice/default-twig');

        return $twig;
    }
}
