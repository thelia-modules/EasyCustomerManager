<?php

namespace EasyCustomerManager\Event;

use Thelia\Core\Event\ActionEvent;

class TemplateFieldEvent extends ActionEvent
{
    public const CUSTOMER_MANAGER_TEMPLATE_FIELD = 'customer.manager.template.field';

    protected $templateFields = [];

    public function addTemplateField($name, $template)
    {
        $this->templateFields[$name] = $template;
    }

    public function removeTemplateField($name)
    {
        if (isset($this->templateFields[$name])) {
            unset($this->templateFields[$name]);
        }
    }

    public function getTemplateFields()
    {
        return $this->templateFields;
    }
}
