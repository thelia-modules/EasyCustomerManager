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

namespace EasyCustomerManager\Hook;

use EasyCustomerManager\Form\Configuration;
use EasyCustomerManager\Service\OrderStatusOverview;
use EasyCustomerManager\Service\PaidOrderStatuses;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

class ConfigHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly PaidOrderStatuses $paidOrderStatuses,
        private readonly OrderStatusOverview $orderStatusOverview,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    /**
     * @return array<string, list<array{type: string, method: string}>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(Configuration::class, data: ['order' => $this->paidOrderStatuses->raw()]);

        $event->add($this->render('EasyCustomerManager/config/module-config.html.twig', [
            'form' => $form->createView()->getView(),
            'order_statuses' => $this->orderStatusOverview->all($this->getRequest()?->getLocale() ?? 'en_US'),
        ]));
    }
}
