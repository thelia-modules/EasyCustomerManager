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

namespace EasyCustomerManager;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class EasyCustomerManager extends BaseModule
{
    public const DOMAIN_NAME = 'easycustomermanager';

    /** Module configuration key holding the comma separated ids of the order statuses that count as paid. */
    public const PAID_STATUSES_CONFIG_KEY = 'order_types';

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/Event/*',
                __DIR__.'/I18n/*',
                __DIR__.'/Tests/*',
                __DIR__.'/templates/*',
            ])
            ->autowire()
            ->autoconfigure();
    }
}
