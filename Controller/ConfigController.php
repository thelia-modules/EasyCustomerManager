<?php

namespace EasyCustomerManager\Controller;

use EasyCustomerManager\EasyCustomerManager;
use EasyCustomerManager\Form\Configuration;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * class ConfigController
 */
class ConfigController extends BaseAdminController
{
    #[Route('/admin/module/EasyCustomerManager/save', name: 'easy_customer_manager.config.save', methods: ['POST'])]
    public function setAction(): RedirectResponse
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::getName());

        try {
            $configForm = $this->validateForm($form);
            EasyCustomerManager::setConfigValue('order_types', $configForm->get('order')->getData(), true, true);
        } catch (\Exception $exception) {
            $this->setupFormErrorContext('Configuration', $exception->getMessage(), $form, $exception);
        }

        return $this->generateRedirectFromRoute(
            'admin.module.configure',
            [],
            ['module_code' => 'EasyCustomerManager']
        );
    }
}
