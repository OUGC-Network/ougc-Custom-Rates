<?php

/***************************************************************************
 *
 *    OUGC Custom Reputation plugin (/inc/plugins/ougc/CustomReputation/core.php)
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

namespace ougc\CustomReputation\Core;

use MybbStuff_MyAlerts_AlertTypeManager;

use const ougc\CustomReputation\Core\SETTINGS;
use const ougc\CustomReputation\Core\DEBUG;
use const ougc\CustomReputation\ROOT;

const URL = 'showthread.php';

const LINK_REPUTATION_TYPE_NONE = 0;

const CORE_REPUTATION_TYPE_POSITIVE = 1;

const CORE_REPUTATION_TYPE_NEUTRAL = 2;

const CORE_REPUTATION_TYPE_NEGATIVE = 3;

function loadLanguage(): bool
{
    global $lang;

    if (isset($lang->ougc_customrep)) {
        return true;
    }

    if (defined('IN_ADMINCP')) {
        $lang->load('config_ougc_customrep');
    } else {
        $lang->load('ougc_customrep');
    }

    return true;
}

function addHooks(string $namespace): bool
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }

    return true;
}

function urlHandler(string $newUrl = ''): string
{
    static $setUrl = URL;

    if (($newUrl = trim($newUrl))) {
        $setUrl = $newUrl;
    }

    return $setUrl;
}

function urlHandlerSet(string $newUrl): string
{
    return urlHandler($newUrl);
}

function urlHandlerGet(): string
{
    return urlHandler();
}

function urlHandlerBuild(array $urlAppend = [], bool $fetchImportUrl = false, bool $encode = true): string
{
    global $PL;

    if (!is_object($PL)) {
        $PL or require_once PLUGINLIBRARY;
    }

    if ($fetchImportUrl === false) {
        if ($urlAppend && !is_array($urlAppend)) {
            $urlAppend = explode('=', $urlAppend);
            $urlAppend = [$urlAppend[0] => $urlAppend[1]];
        }
    }

    return $PL->url_append(urlHandlerGet(), $urlAppend, '&amp;', $encode);
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return isset(SETTINGS[$settingKey]) ? SETTINGS[$settingKey] : (
    isset($mybb->settings['ougc_customrep_' . $settingKey]) ? $mybb->settings['ougc_customrep_' . $settingKey] : false
    );
}

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return "ougccustomrep{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

function reputationSync(int $user_id): bool
{
    global $db;

    $query = $db->simple_select('reputation', 'SUM(reputation) AS reputation_count', "uid='{$user_id}'");

    $reputation_count = (int)$db->fetch_field($query, 'reputation_count');

    $db->update_query('users', ['reputation' => $reputation_count], "uid='{$user_id}'");

    return true;
}

function logDelete(int $log_id): bool
{
    global $mybb;
    global $db, $plugins;

    $reputation_delete_data = [];

    $hookArguments = [
        'log_id' => $log_id,
        'reputation_delete_data' => &$reputation_delete_data
    ];

    $query = $db->simple_select('reputation', 'rid, uid, pid', "lid='{$log_id}'");

    while ($reputation_data = $db->fetch_array($query)) {
        $reputation_delete_data[(int)$reputation_data['rid']] = [
            'user_id' => (int)$reputation_data['uid'],
            'post_id' => (int)$reputation_data['pid']
        ];
    }

    $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_delete_start', $hookArguments);

    foreach ($reputation_delete_data as $reputation_id => $reputation_data) {
        $db->delete_query('reputation', "rid='{$reputation_id}'");

        reputationSync($reputation_data['user_id']);

        alertDelete($reputation_data['user_id'], (int)$mybb->user['uid'], $reputation_data['post_id']);
    }

    $db->delete_query('ougc_customrep_log', "lid='{$log_id}'");

    return true;
}

function alertsObject(): MybbStuff_MyAlerts_AlertTypeManager
{
    myalerts_create_instances();

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

    if (is_null($alertTypeManager) || $alertTypeManager === false) {
        global $db, $cache;

        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance(
            $db,
            $cache
        );
    }

    return $alertTypeManager;
}

function alertDelete(int $user_id, int $from_user_id, int $post_id): bool
{
    if (!function_exists('myalerts_create_instances')) {
        return false;
    }

    global $db;

    $alertType = alertsObject()->getByCode('rep');

    if (is_int($alertType)/* && $alertType->getEnabled()*/) {
        $db->delete_query(
            'alerts',
            "uid='{$user_id}' AND from_user_id='{$from_user_id}' AND alert_type_id='{$alertType}' AND object_id='{$post_id}'"
        );
    }

    return true;
}