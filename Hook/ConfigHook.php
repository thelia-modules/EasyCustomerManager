<?php

namespace EasyCustomerManager\Hook;

use EasyCustomerManager\Form\Configuration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

class ConfigHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(Configuration::getName());

        $event->add(
            $this->render('EasyCustomerManager/config/module-config.html.twig', [
                'form' => $form->createView()->getView(),
                'order_statuses' => $this->getOrderStatuses(),
            ])
        );
    }

    /**
     * @return array<int, array{id: int, code: string, color: string, title: string, position: int, orderCount: int}>
     */
    private function getOrderStatuses(): array
    {
        $request = $this->getRequest();
        $locale = $request?->getSession()?->getLang()?->getLocale() ?? 'en_US';

        $statuses = OrderStatusQuery::create()
            ->orderByPosition()
            ->find();

        $result = [];

        foreach ($statuses as $status) {
            $status->setLocale($locale);

            $result[] = [
                'id' => $status->getId(),
                'code' => (string) $status->getCode(),
                'color' => (string) $status->getColor(),
                'title' => (string) $status->getTitle(),
                'position' => $status->getPosition(),
                'orderCount' => OrderQuery::create()->filterByStatusId($status->getId())->count(),
            ];
        }

        return $result;
    }
}
