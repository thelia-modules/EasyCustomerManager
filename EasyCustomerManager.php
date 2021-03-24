<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace EasyCustomerManager;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

class EasyCustomerManager extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'easycustomermanager';
    const MODULE_VERSION = '1.0.0';
    const MODULE_NAME = 'EasyCustomerManager';


    public function getHooks()
    {
        return [
            [
                "type" => TemplateDefinition::FRONT_OFFICE,
                "code" => "easycustomermanager.js",
                "title" => [
                    "en_US" => "Easy Customer Manager js",
                    "fr_FR" => "Js pour Easy Customer Manager",
                ],
                "active" => true,
                "module" => true,
            ]
        ];
    }
}
