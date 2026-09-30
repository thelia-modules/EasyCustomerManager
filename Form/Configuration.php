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

namespace EasyCustomerManager\Form;

use EasyCustomerManager\EasyCustomerManager;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder->add('order', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'constraints' => [
                new Regex(
                    pattern: '/^\s*(\d+(\s*,\s*\d+)*)?\s*$/',
                    message: $translator->trans('Enter order status ids separated by commas, for example 2,4', [], EasyCustomerManager::DOMAIN_NAME),
                ),
            ],
            'label' => $translator->trans('Ids of the order statuses counted as paid', [], EasyCustomerManager::DOMAIN_NAME),
        ]);
    }

    public static function getName(): string
    {
        return 'easy_customer_manager_configuration';
    }
}
