<?php

/***************************************************************************
 *
 *    OUGC Custom Reputation plugin (/inc/plugins/ougc/CustomReputation/hooks/admin.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users rate posts with custom post reputations with rich features.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace ougc\CustomReputation\Hooks\Admin;

use MyBB;

use function ougc\CustomReputation\Core\loadLanguage;

function admin_config_plugins_deactivate(): bool
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'ougc_customrep' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return false;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=ougc_customrep'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_config_action_handler(array $actionObjects): array
{
    $actionObjects['ougc_customrep'] = [
        'active' => 'ougc_customrep',
        'file' => 'ougc_customrep.php'
    ];

    return $actionObjects;
}

function admin_config_menu(array $subMenuItems): array
{
    global $lang;

    loadLanguage();

    $subMenuItems[] = [
        'id' => 'ougc_customrep',
        'title' => $lang->ougc_customrep,
        'link' => 'index.php?module=config-ougc_customrep'
    ];

    return $subMenuItems;
}

function admin_config_permissions(array $adminPermissions): array
{
    global $lang;

    loadLanguage();

    $adminPermissions['ougc_customrep'] = $lang->ougc_customrep_perm;

    return $adminPermissions;
}

function admin_config_settings_start(): bool
{
    return loadLanguage();
}

function admin_style_templates_set(): bool
{
    return loadLanguage();
}

function admin_config_settings_change(): bool
{
    global $mybb;

    loadLanguage();

    if ($mybb->request_method === 'post' && !isset($mybb->input['upsetting']['ougc_customrep_xthreads_hide'])) {
        $mybb->input['upsetting']['ougc_customrep_xthreads_hide'] = implode(
            ',',
            (array)$mybb->input['upsetting']['ougc_customrep_xthreads_hide']
        );
    }


    return true;
}