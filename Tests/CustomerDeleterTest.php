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

use EasyCustomerManager\Service\CustomerDeleter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\OrderStatus;

final class CustomerDeleterTest extends EasyCustomerTestCase
{
    public function testCustomersAreSortedIntoDeletedKeptAndFailed(): void
    {
        $removable = $this->customer('Removable');
        $ordering = $this->customer('Ordering');
        $this->order($ordering, OrderStatus::CODE_PAID, 10.0);
        $broken = $this->customer('Broken');

        $dispatcher = $this->getService('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $listener = static function (CustomerEvent $event) use ($broken): void {
            if ($event->getModel()?->getId() === $broken->getId()) {
                throw new \RuntimeException('a listener failed');
            }
        };
        $dispatcher->addListener(TheliaEvents::CUSTOMER_DELETEACCOUNT, $listener, 200);

        try {
            $deletion = $this->getService(CustomerDeleter::class)->delete([$removable->getId(), $ordering->getId(), $broken->getId()]);
        } finally {
            $dispatcher->removeListener(TheliaEvents::CUSTOMER_DELETEACCOUNT, $listener);
        }

        self::assertSame([$removable->getId()], $deletion->deletedIds);
        self::assertSame([$ordering->getId()], $deletion->keptIds);
        self::assertSame([$broken->getId()], $deletion->failedIds);
    }
}
