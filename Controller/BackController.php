<?php
/*************************************************************************************/
/*      This file is part of the module EasyProductManager.                          */
/*                                                                                   */
/*      Copyright (c) Gilles Bourgeat                                                */
/*      email : gilles.bourgeat@gmail.com                                            */
/*                                                                                   */
/*      This module is not open source                                               /*
/*      please contact gilles.bourgeat@gmail.com for a license                       */
/*                                                                                   */
/*                                                                                   */
/*************************************************************************************/

namespace EasyCustomerManager\Controller;

use EasyCustomerManager\EasyCustomerManager;
use EasyCustomerManager\Event\BeforeFilterEvent;
use EasyCustomerManager\Event\TemplateFieldEvent;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\ProductController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;
use Symfony\Component\Routing\Annotation\Route;

/**
 * class BackController
 * @Route("/admin/easy-customer-manager/", name="back") 
 */
class BackController extends ProductController
{
    /**
     * @Route("/list", name="list") 
     */
    public function listAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, [], AccessManager::UPDATE)) {
            return $response;
        }

        if ($request->isXmlHttpRequest()) {

            $locale = $this->getRequest()->getSession()->getLang()->getLocale();

            $query = CustomerQuery::create();
            $query->useOrderQuery('order', Criteria::LEFT_JOIN)
                ->endUse()
                ->groupBy(CustomerTableMap::COL_ID);

            $this->applyOrder($request, $query);

            $queryCount = clone $query;

            $beforeFilterEvent = new BeforeFilterEvent($request, $query);
            $this->getDispatcher()->dispatch(BeforeFilterEvent::CUSTOMER_MANAGER_BEFORE_FILTER, $beforeFilterEvent);

            $this->filterByCountry($request, $query);
            $this->filterByCreatedAt($request, $query);

            $this->applySearchCustomer($request, $query);

            $querySearchCount = clone $query;

            $query->offset($this->getOffset($request));

            $customers = $query->limit(25)->find();

            $json = [
                "draw"=> $this->getDraw($request),
                "recordsTotal"=> $queryCount->count(),
                "recordsFiltered"=> $querySearchCount->count(),
                "data" => [],
                "customers" => count($customers->getData()),
            ];

            $moneyFormat = MoneyFormat::getInstance($request);

            /** @var Customer $customer */
            foreach ($customers as $customer) {

                $updateUrl = URL::getInstance()->absoluteUrl('admin/customer/update?customer_id='.$customer->getId());

                $customer->getOrders();

                $orderQuery = OrderQuery::create();
                $latest_order = $orderQuery->filterByCustomerId($customer->getId())
                    ->orderByCreatedAt(Criteria::DESC)
                    ->findOne();


                $latest_order_created_at = null;
                $latest_order_amount = null;

                if ($latest_order != null) {
                    $latest_order_created_at = $latest_order->getCreatedAt('d/m/y H:i:s');
                    $latest_order_amount = $moneyFormat->formatByCurrency(
                        $latest_order->getTotalAmount(),
                        2,
                        '.',
                        ' ',
                        $latest_order->getCurrencyId()
                    );
                }

                $total_amount = array_sum(array_map(function (Order $order) {
                    if (!in_array($order->getStatusId(), explode(',', EasyCustomerManager::getConfigValue('order_types')))) {
                        return 0;
                    }

                    return $order->getTotalAmount();
                }, iterator_to_array($customer->getOrders())));

                if ($total_amount != 0) {
                    $total_amount = $moneyFormat->formatByCurrency(
                        $total_amount,
                        2,
                        '.',
                        ' ',
                        $customer->getOrders()[0]->getCurrencyId()
                    );
                }



                $json['data'][] = [
                    [
                        'name' => $customer->getRef(),
                        'href' => $updateUrl
                    ],
                    [
                        'name' => $customer->getLastname(),
                        'href' => $updateUrl
                    ],
                    [
                        'name' => $customer->getFirstname(),
                        'href' => $updateUrl
                    ],
                    [
                        'email' => $customer->getEmail(),
                    ],
                    $customer->getCreatedAt('d/m/y H:i:s'),
                    $latest_order_created_at,
                    $latest_order_amount,
                    $total_amount,
                    [
                        'customer_id' => $customer->getId(),
                        'hrefUpdate' => $updateUrl,
                    ]
                ];
            }

            return new JsonResponse($json);
        }

        $templateFieldEvent = new TemplateFieldEvent();
        $this->getDispatcher()->dispatch(TemplateFieldEvent::CUSTOMER_MANAGER_TEMPLATE_FIELD, $templateFieldEvent);

        return $this->render('EasyCustomerManager/list', [
            'columnsDefinition' => $this->defineColumnsDefinition(),
            'theliaVersion' => Thelia::THELIA_VERSION,
            'moduleVersion' => EasyCustomerManager::MODULE_VERSION,
            'moduleName' => EasyCustomerManager::MODULE_NAME,
            'template_fields' => $templateFieldEvent->getTemplateFields()
        ]);
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getOrderColumnName(Request $request)
    {
        $columnDefinition = $this->defineColumnsDefinition(true)[
        (int) $request->get('order')[0]['column']
        ];

        return $columnDefinition['orm'];
    }

    protected function applyOrder(Request $request, CustomerQuery $query)
    {
        $query->orderBy(
            $this->getOrderColumnName($request),
            $this->getOrderDir($request)
        );
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getOrderDir(Request $request)
    {
        return (string) $request->get('order')[0]['dir'] === 'asc' ? Criteria::ASC : Criteria::DESC;
    }

    /**
     * @param Request $request
     * @return int
     */
    protected function getLength(Request $request)
    {
        return (int) $request->get('length');
    }

    /**
     * @param Request $request
     * @return int
     */
    protected function getOffset(Request $request)
    {
        return (int) $request->get('start');
    }


    /**
     * @param Request $request
     * @return int
     */
    protected function getDraw(Request $request)
    {
        return (int) $request->get('draw');
    }

    /**
     * @param bool $withPrivateData
     * @return array
     */
    protected function defineColumnsDefinition($withPrivateData = false)
    {
        $i = -1;

        $definitions = [
            [
                'name' => 'ref',
                'targets' => ++$i,
                'orm' => CustomerTableMap::COL_REF,
                'title' => 'Référence',
            ],
            [
                'name' => 'lastname',
                'targets' => ++$i,
                'title' => 'Nom',
                'orm' => CustomerTableMap::COL_LASTNAME,
            ],
            [
                'name' => 'firstname',
                'targets' => ++$i,
                'title' => 'Prénom',
                'orm' => CustomerTableMap::COL_FIRSTNAME,
            ],
            [
                'name' => 'email',
                'targets' => ++$i,
                'title' => 'Email',
                'orm' => CustomerTableMap::COL_EMAIL,
            ],
            [
                'name' => 'created_at',
                'targets' => ++$i,
                'orm' => CustomerTableMap::COL_CREATED_AT,
                'title' => 'Date d\'enregistrement',
            ],
            [
                'name' => 'latest_order',
                'targets' => ++$i,
                'orm' => OrderTableMap::COL_CREATED_AT,
                'title' => 'Date de la dernière commande',
            ],
            [
                'name' => 'amount_latest_order',
                'targets' => ++$i,
                'title' => 'Montant de la dernière commande',
                'orderable' => false,
            ],
            [
                'name' => 'total_amount',
                'targets' => ++$i,
                'title' => 'Chiffre d\'affaire client',
                'orderable' => false,
            ],
            [
                'name' => 'action',
                'targets' => ++$i,
                'title' => 'Action',
                'orderable' => false,
            ]
        ];

        if (!$withPrivateData) {
            foreach ($definitions as &$definition) {
                unset($definition['orm']);
            }
        }

        return $definitions;
    }

    protected function filterByCountry(Request $request, CustomerQuery $query)
    {
        if (0 !== $countryId = (int) $request->get('filter')['country']) {
            $query->useAddressQuery()
                ->filterByCountryId($countryId)
                ->endUse();
        }
    }



    protected function filterByCreatedAt(Request $request, CustomerQuery $query)
    {
        if ('' !== $createdAtFrom = $request->get('filter')['createdAtFrom']) {
            $query->filterByCreatedAt(sprintf("%s 00:00:00", $createdAtFrom), Criteria::GREATER_EQUAL);
        }
        if ('' !== $createdAtTo = $request->get('filter')['createdAtTo']) {
            $query->filterByCreatedAt(sprintf("%s 23:59:59", $createdAtTo), Criteria::LESS_EQUAL);
        }
    }

    protected function applySearchCustomer(Request $request, CustomerQuery $query)
    {
        $value = $this->getSearchValue($request, 'searchCustomer');

        if (strlen($value) > 2) {
            $query->where(CustomerTableMap::COL_REF . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
            $query->_or()->where(CustomerTableMap::COL_ID . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
            $query->_or()->where(CustomerTableMap::COL_FIRSTNAME . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
            $query->_or()->where(CustomerTableMap::COL_LASTNAME . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
            $query->_or()->where(CustomerTableMap::COL_EMAIL . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
        }
    }

    protected function getSearchValue(Request $request, $searchKey)
    {
        return (string) $request->get($searchKey)['value'];
    }
}
