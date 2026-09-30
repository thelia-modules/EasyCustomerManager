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

namespace EasyCustomerManager\Event;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CustomerQuery;

/**
 * Dispatched once the module has applied its own filters, before the count and the pagination:
 * a listener narrows the customer query with the fields it added through TemplateFieldEvent.
 */
final class BeforeFilterEvent extends ActionEvent
{
    public const CUSTOMER_MANAGER_BEFORE_FILTER = 'customer.manager.before.filter';

    public function __construct(
        private readonly Request $request,
        private readonly CustomerQuery $query,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getQuery(): CustomerQuery
    {
        return $this->query;
    }
}
