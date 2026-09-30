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
use EasyCustomerManager\Form\Configuration;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;

class ConfigurationController extends BaseAdminController
{
    #[Route('/admin/module/EasyCustomerManager/save', name: 'easy_customer_manager.configuration.save', methods: ['POST'])]
    public function save(PaidOrderStatuses $paidOrderStatuses, UrlGeneratorInterface $urlGenerator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, EasyCustomerManager::DOMAIN_NAME, AccessManager::UPDATE)) {
            return $response;
        }

        try {
            $validForm = $this->validateForm($this->createForm(Configuration::class));
            $paidOrderStatuses->store((string) $validForm->get('order')->getData());

            $this->addFlash('success', $this->translator->trans('The configuration has been saved', [], EasyCustomerManager::DOMAIN_NAME));
        } catch (FormValidationException $exception) {
            $this->addFlash('danger', $this->createStandardFormValidationErrorMessage($exception));
        }

        return $this->generateRedirect($urlGenerator->generate('admin.module.configure', ['module_code' => EasyCustomerManager::getModuleCode()]));
    }
}
