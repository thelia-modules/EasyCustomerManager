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

use Thelia\Core\Event\ActionEvent;

/**
 * Lets a module add filter fields to the list: each template is included inside the filter form,
 * so its inputs are sent with the other filters (query string of the list page).
 */
final class TemplateFieldEvent extends ActionEvent
{
    public const CUSTOMER_MANAGER_TEMPLATE_FIELD = 'customer.manager.template.field';

    /** @var array<string, string> */
    private array $templateFields = [];

    public function addTemplateField(string $name, string $template): void
    {
        $this->templateFields[$name] = $template;
    }

    public function removeTemplateField(string $name): void
    {
        unset($this->templateFields[$name]);
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateFields(): array
    {
        return $this->templateFields;
    }
}
