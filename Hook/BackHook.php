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

namespace EasyCustomerManager\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class BackHook extends BaseHook
{
    public function onMainInTopMenuItems(HookRenderEvent $event)
    {
        $event->add(
            $this->render('EasyCustomerManager/hook/main.in.top.menu.items.html', [])
        );
    }
}
