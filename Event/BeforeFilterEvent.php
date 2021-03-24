<?php

namespace EasyCustomerManager\Event;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CustomerQuery;

class BeforeFilterEvent extends ActionEvent
{
    public const CUSTOMER_MANAGER_BEFORE_FILTER = 'customer.manager.before.filter';

    /** @var Request */
    protected $request;
    /** @var CustomerQuery */
    protected $query;

    protected $templateFields = [];

    public function __construct(Request $request, CustomerQuery $query)
    {
        $this->request = $request;
        $this->query = $query;
    }

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

    public function getRequest()
    {
        return $this->request;
    }

    public function getQuery()
    {
        return $this->query;
    }
}
