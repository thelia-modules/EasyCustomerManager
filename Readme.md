# Easy Customer Manager

One back-office list to find, sort and clean up customers: search by name, first name, email, reference or id, filter by
country and registration date, sort on any column, and see for each customer the date and amount of the last order and
the revenue made with the orders in a paid status. Customers without orders can be deleted from the list, one by one or
by selection.

Thelia 3.2 or later. The 2.x line of this module is for Thelia 2.

## Installation

```
composer require thelia/easy-customer-manager-module:^3.0
php bin/console module:refresh
php bin/console module:activate EasyCustomerManager
```

## Usage

Once activated, a "Customer manager" link appears in the back-office menu.

The module configuration page holds the ids of the order statuses counted as paid (for example `2,4`), used for the
"Customer revenue" column, and lists the order statuses with their ids.

Rights: the list needs the view right on customers, the deletion the delete right on customers, the configuration the
update right on the module. The deletion form is protected by the back-office token; a customer who has orders is never
deleted.

## Events

Two events let another module add its own filter:

```
EasyCustomerManager\Event\TemplateFieldEvent::CUSTOMER_MANAGER_TEMPLATE_FIELD
EasyCustomerManager\Event\BeforeFilterEvent::CUSTOMER_MANAGER_BEFORE_FILTER
```

With `TemplateFieldEvent`, `addTemplateField($name, $template)` adds a Twig template inside the filter form: its inputs are
sent with the other filters, in the query string of the list. `BeforeFilterEvent` gives the request and the `CustomerQuery`
(already narrowed by the module's own filters, before the count and the pagination) to narrow it with those inputs.

## Changes in 3.0.0

- Thelia 3.2: Symfony 7.4 attribute routes, services, hooks and forms registered by `configureServices()`, `config.xml`
  and `routing.xml` emptied, Smarty templates removed in favour of Twig templates for the default-twig back-office.
- The list is a server-rendered page (GET filters, sort links, pagination) instead of a DataTables screen loading jQuery,
  select2, lodash and DataTables from third-party CDNs. The 2.x JSON list endpoint and the external "module
  information" frame are gone. Route names: `backlist` becomes `easy_customer_manager.list`, `backdelete_selected`
  becomes `easy_customer_manager.delete_selected`, `easy_customer_managerset` becomes
  `easy_customer_manager.configuration.save` (URL `/admin/module/EasyCustomerManager/save`).
- The customers are read one page at a time (10, 25, 50 or 100), the orders of the whole page with one query, whatever the
  number of customers; every filter is a bound parameter, the sort column is one of a fixed set and `%` and `_` typed in
  the search are searched as text.
- Rights: the list needs the view right on customers (it was the update right), the deletion the delete right, the
  configuration the update right on the module. Bulk deletion is a POST protected by the back-office token (the token was
  a query parameter of an AJAX call) and goes through the core customer deletion event.
- Customer names and emails are escaped (they were injected as HTML by the DataTables renderers).
- The "paid statuses" setting is validated (`2,4`) and is now stored without a locale. A value saved by 2.x through the
  configuration page was stored under a locale named `1` and has to be entered again.
- `BeforeFilterEvent` and `TemplateFieldEvent` keep their names, but the request shape changes for a listener: the
  filter inputs added through `TemplateFieldEvent` are fields of the GET filter form, read from `$request->query`
  (2.x sent them as `filter[...]` in a DataTables POST). Parameters the module does not know are kept in the
  pagination, sort and deletion links. The query given to `BeforeFilterEvent` no longer carries the `LEFT JOIN order`
  and the `GROUP BY customer.id` of 2.x, and is counted and paginated after the event: a listener that filtered on
  order columns has to add its own join or a subquery (an `EXISTS` avoids duplicate customers), and must not rely on
  the grouping.
- The menu entry is shown only to administrators who can view customers, and a notice on the list reminds that the
  customer revenue is 0 until the paid order statuses are chosen in the module configuration. A deletion that fails for
  another reason than existing orders is logged and reported, and the administrator lands back on the same page and
  filters.
- Translations: English source, French and German catalogues; the module texts are no longer hard-coded in French.
- Removed: the unused front-office hook `easycustomermanager.js`, `FrontHook`, `MODULE_VERSION`, the empty `schema.xml`.
