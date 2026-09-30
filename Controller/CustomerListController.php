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

namespace EasyCustomerManager\Controller;

use EasyCustomerManager\EasyCustomerManager;
use EasyCustomerManager\Event\TemplateFieldEvent;
use EasyCustomerManager\Service\CustomerDeleter;
use EasyCustomerManager\Service\CustomerListFilters;
use EasyCustomerManager\Service\CustomerListService;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Tools\TokenProvider;

#[Route('/admin/easy-customer-manager', name: 'easy_customer_manager.')]
class CustomerListController extends BaseAdminController
{
    /** The largest selection one submission can delete: the biggest page of the list. */
    private const MAX_DELETED_AT_ONCE = 100;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, CustomerListService $listService, EventDispatcherInterface $dispatcher, PaidOrderStatuses $paidOrderStatuses): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::VIEW)) {
            return $response;
        }

        $templateFields = new TemplateFieldEvent();
        $dispatcher->dispatch($templateFields, TemplateFieldEvent::CUSTOMER_MANAGER_TEMPLATE_FIELD);

        $page = $listService->page(CustomerListFilters::fromInput($request->query), $request);

        return $this->render('EasyCustomerManager/list', [
            'page' => $page,
            'query_parameters' => $page->filters->toQueryParameters(),
            'countries' => $listService->countries($request->getLocale()),
            'page_sizes' => CustomerListFilters::PAGE_SIZES,
            'min_search_length' => CustomerListFilters::MIN_SEARCH_LENGTH,
            'template_fields' => $templateFields->getTemplateFields(),
            'paid_statuses_configured' => [] !== $paidOrderStatuses->ids(),
        ]);
    }

    #[Route('/delete-selected', name: 'delete_selected', methods: ['POST'])]
    public function deleteSelected(Request $request, CustomerDeleter $deleter, TokenProvider $tokenProvider, UrlGeneratorInterface $urlGenerator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::DELETE)) {
            return $response;
        }

        $returnQuery = new InputBag(array_filter($request->request->all('return'), is_scalar(...)));
        $returnFilters = CustomerListFilters::fromInput($returnQuery);
        $listUrl = $urlGenerator->generate('easy_customer_manager.list', $returnFilters->toQueryParameters() + ($returnFilters->page > 1 ? ['page' => $returnFilters->page] : []));

        try {
            $tokenProvider->checkToken($request->request->getString('_token'));
        } catch (TokenAuthenticationException) {
            $this->addFlash('danger', $this->translator->trans('Your session has expired, please reload the page and try again', [], EasyCustomerManager::DOMAIN_NAME));

            return $this->generateRedirect($listUrl);
        }

        $customerIds = array_values(array_unique(array_filter(
            array_map(intval(...), array_filter($request->request->all('customer_ids'), is_scalar(...))),
            static fn (int $customerId): bool => $customerId > 0,
        )));

        if ([] === $customerIds || \count($customerIds) > self::MAX_DELETED_AT_ONCE) {
            $this->addFlash('warning', $this->translator->trans('Select between 1 and %max% customers', ['%max%' => self::MAX_DELETED_AT_ONCE], EasyCustomerManager::DOMAIN_NAME));

            return $this->generateRedirect($listUrl);
        }

        $deletion = $deleter->delete($customerIds);

        if ([] !== $deletion->deletedIds) {
            $this->addFlash('success', $this->translator->trans('%count% customer(s) deleted', ['%count%' => \count($deletion->deletedIds)], EasyCustomerManager::DOMAIN_NAME));
        }

        if ([] !== $deletion->keptIds) {
            $this->addFlash('warning', $this->translator->trans('%count% customer(s) not deleted because they have orders', ['%count%' => \count($deletion->keptIds)], EasyCustomerManager::DOMAIN_NAME));
        }

        if ([] !== $deletion->failedIds) {
            $this->addFlash('danger', $this->translator->trans('%count% customer(s) could not be deleted, see the logs', ['%count%' => \count($deletion->failedIds)], EasyCustomerManager::DOMAIN_NAME));
        }

        return $this->generateRedirect($listUrl);
    }
}
