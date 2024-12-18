<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/ougc/CustomReputation/hooks/admin.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Create custom rates for users to use in posts.
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

namespace ougc\CustomRates\Hooks\Admin;

use MyBB;

use function admin_redirect;
use function ougc\CustomRates\Core\loadLanguage;
use function ougc\CustomRates\Core\logUpdate;

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
        'title' => $lang->ougc_custom_rates_admin_menu,
        'link' => 'index.php?module=config-ougc_custom_rates'
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

function admin_user_users_merge_commit(): bool
{
    global $db, $destination_user, $source_user;

    $fromUserID = (int)$source_user['uid'];

    $newUserID = (int)$destination_user['uid'];

    $query = $db->simple_select('ougc_customrep_log', 'lid', "uid='{$fromUserID}'");

    while ($logID = (int)$db->fetch_field($query, 'lid')) {
        logUpdate($logID, ['uid' => $newUserID]);
    }

    return true;
}

function admin_formcontainer_output_row(array &$hookArguments): array
{
    global $lang, $cache, $form, $mybb;

    loadLanguage();

    if (
        empty($hookArguments['title']) ||
        empty($lang->setting_ougc_xthreads_hide) ||
        $hookArguments['title'] !== $lang->setting_ougc_xthreads_hide
    ) {
        return $hookArguments;
    }

    $customThreadFieldsCache = $cache->read('threadfields');

    if (!function_exists('xthreads_gettfcache') || empty($customThreadFieldsCache)) {
        $hookArguments['content'] = $lang->setting_ougc_xthreads_information;

        return $hookArguments;
    }

    $currentItems = $mybb->settings['ougc_customrep_xthreads_hide'];

    if (isset($mybb->input['upsetting']['ougc_customrep_xthreads_hide'])) {
        $currentItems = $mybb->input['upsetting']['ougc_customrep_xthreads_hide'];
    }

    $optionItems = $selectedItems = [];

    foreach ($customThreadFieldsCache as $customThreadFieldData) {
        if (array_intersect([$customThreadFieldData['field']], explode(',', $currentItems))) {
            $selectedItems[] = $customThreadFieldData['field'];
        }

        $optionItems[$customThreadFieldData['field']] = $customThreadFieldData['field'];
    }

    $hookArguments['content'] = $form->generate_select_box(
        'upsetting[ougc_customrep_xthreads_hide][]',
        $optionItems,
        $selectedItems,
        ['id' => 'row_setting_ougc_customrep_xthreads_hide', 'size' => 5, 'multiple' => true]
    );

    return $hookArguments;
}