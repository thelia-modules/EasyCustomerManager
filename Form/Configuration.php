<?php

namespace EasyCustomerManager\Form;


use EasyCustomerManager\EasyCustomerManager;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;

        $form->add('order',TextType::class,[
            'data'=> EasyCustomerManager::getConfigValue('order_types')
        ]);
    }

    public static function getName(){
        return 'easy_customer_manager_configuration';
    }
}