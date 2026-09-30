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

namespace EasyCustomerManager\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;

/**
 * Deletes customers through the core event, which refuses a customer who has orders.
 */
final readonly class CustomerDeleter
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<int> $customerIds
     */
    public function delete(array $customerIds): CustomerDeletion
    {
        $deleted = [];
        $kept = [];
        $failed = [];

        if ([] === $customerIds) {
            return new CustomerDeletion($deleted, $kept);
        }

        foreach (CustomerQuery::create()->filterById($customerIds, Criteria::IN)->find() as $customer) {
            $customerId = (int) $customer->getId();

            try {
                $this->dispatcher->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);
                $deleted[] = $customerId;
            } catch (CustomerException) {
                $kept[] = $customerId;
            } catch (\Throwable $exception) {
                $this->logger->error('EasyCustomerManager: customer {id} not deleted: {message}', ['id' => $customerId, 'message' => $exception->getMessage(), 'exception' => $exception]);
                $failed[] = $customerId;
            }
        }

        return new CustomerDeletion($deleted, $kept, $failed);
    }
}
