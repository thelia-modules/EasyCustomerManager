<?php

namespace EasyCustomerManager\Controller;


use EasyCustomerManager\Form\Configuration;
use EasyCustomerManager\EasyCustomerManager;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\ConfigQuery;
use TheliaSmarty\Template\Plugins\Form;

class ConfigController extends BaseAdminController
{
    public function setAction()
    {
        $form = new Configuration($this->getRequest());
        $response = null;

        $configForm = $this->validateForm($form);
        EasyCustomerManager::setConfigValue('order_types',$configForm->get('order')->getData(),true, true);

        $response = $this->render(
            'module-configure',
            ['module_code' => 'EasyCustomerManager']
        );
        return $response;
    }
}