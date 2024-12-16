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

use MybbStuff_MyAlerts_AlertManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_Alert;

use const ougc\CustomReputation\ROOT;

const URL = 'showthread.php';

const REPUTATION_TYPE_NONE = 0;

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

function reputationSync(int $userID): bool
{
    global $db;

    $query = $db->simple_select('reputation', 'SUM(reputation) AS totalUserReputation', "uid='{$userID}'");

    $totalUserReputation = (int)$db->fetch_field($query, 'totalUserReputation');

    $db->update_query('users', ['reputation' => $totalUserReputation], "uid='{$userID}'");

    return true;
}

function rateGet(int $rateID = 0): array
{
    global $customReputationCacheRates;

    is_array($customReputationCacheRates) || $customReputationCacheRates = [];

    if (!isset($customReputationCacheRates[$rateID])) {
        global $db;

        $customReputationCacheRates = [];

        $query = $db->simple_select('ougc_customrep', '*', "rid='{$rateID}'");

        $reputation = $db->fetch_array($query);

        if (isset($reputation['rid'])) {
            $customReputationCacheRates[$rateID] = $reputation;
        }
    }

    return $customReputationCacheRates[$rateID];
}

function rateInsert(array $rateData = [], bool $isUpdate = false, int $rateID = 0)
{
    global $db, $plugins;

    $insertData = [];

    foreach (
        [
            'name',
            'image',
            'groups',
            'forums'
        ] as $columnFieldName
    ) {
        if (isset($rateData[$columnFieldName])) {
            if (is_array($rateData[$columnFieldName])) {
                $rateData[$columnFieldName] = implode(
                    ',',
                    array_unique(array_map('intval', $rateData[$columnFieldName]))
                );
            }

            $insertData[$columnFieldName] = $db->escape_string($rateData[$columnFieldName]);
        }
    }

    foreach (
        [
            'disporder',
            'visible',
            'firstpost',
            'allowdeletion',
            'customvariable',
            'requireattach',
            'ignorepoints',
            'inmultiple',
            'createCoreReputationType'
        ] as $columnFieldName
    ) {
        if (isset($rateData[$columnFieldName])) {
            $insertData[$columnFieldName] = (int)$rateData[$columnFieldName];
        }
    }

    if (isset($rateData['points'])) {
        $insertData['points'] = (float)$rateData['points'];
    }

    $insertData['reptype'] = REPUTATION_TYPE_NONE;

    if (!empty($rateData['reptype'])) {
        $insertData['reptype'] = (int)$rateData['reptype'];
    }

    $hookArguments = [
        'insertData' => &$insertData
    ];

    if ($isUpdate) {
        $hookArguments['rateID'] = $rateID;

        $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_update_start', $hookArguments);
    } else {
        $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_insert_start', $hookArguments);
    }

    if ($insertData) {
        if ($isUpdate) {
            $db->update_query('ougc_customrep', $insertData, "rid='{$rateID}'");

            $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_update_end', $hookArguments);
        } else {
            $hookArguments['rateID'] = $rateID = (int)$db->insert_query('ougc_customrep', $insertData);

            $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_insert_end', $hookArguments);
        }
    }
}

function rateUpdate(array $updateData = [], int $rateID = 0)
{
    rateInsert($updateData, true, $rateID);
}

function rateDelete(int $rateID): bool
{
    global $db, $plugins;

    $logIDs = [];

    $hookArguments = [
        'rateID' => $rateID,
        'logIDs' => &$logIDs
    ];

    $query = $db->simple_select('ougc_customrep_log', 'lid', "rid='{$rateID}'");

    while ($logID = (int)$db->fetch_field($query, 'lid')) {
        $logIDs[$logID] = $logID;
    }

    $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_delete_start', $hookArguments);

    foreach ($logIDs as $logID) {
        logDelete($logID);
    }

    $db->delete_query('ougc_customrep', "rid='{$rateID}'");

    return true;
}

function rateImageGet(string $rateImage, int $rateID): string
{
    global $customReputationCacheImages;

    is_array($customReputationCacheImages) || $customReputationCacheImages = [];

    if (!isset($customReputationCacheImages[$rateID])) {
        global $mybb, $theme;

        $replaces = [
            '{bburl}' => $mybb->settings['bburl'],
            '{homeurl}' => $mybb->settings['homeurl'],
            '{imgdir}' => $theme['imgdir']
        ];

        $customReputationCacheImages[$rateID] = $mybb->get_asset_url(
            str_replace(array_keys($replaces), array_values($replaces), $rateImage)
        );
    }

    return $customReputationCacheImages[$rateID];
}

function logGet(int $logID): array
{
    global $customReputationOCacheLogs;

    is_array($customReputationOCacheLogs) || $customReputationOCacheLogs = [];

    if (!isset($customReputationOCacheLogs[$logID])) {
        $customReputationOCacheLogs[$logID] = [];

        global $db;

        $query = $db->simple_select('ougc_customrep_log', '*', "lid='{$logID}'");

        $logData = $db->fetch_array($query);

        if (isset($logData['lid'])) {
            $customReputationOCacheLogs[$logID] = $logData;
        }
    }

    return $customReputationOCacheLogs[$logID];
}

function logInsert(
    array $logData,
    int $reputationValue = REPUTATION_TYPE_NONE
): int {
    global $mybb, $plugins, $db;

    $insertData = [];

    if (isset($logData['pid'])) {
        $insertData['pid'] = (int)$logData['pid'];
    } else {
        return 0;
    }

    $postData = get_post($insertData['pid']);

    if (empty($postData['pid'])) {
        return 0;
    }

    $postUserID = (int)$postData['uid'];

    if (isset($logData['uid'])) {
        $insertData['uid'] = (int)$logData['uid'];
    } else {
        $insertData['uid'] = (int)$mybb->user['uid'];
    }

    if (isset($logData['rid'])) {
        $insertData['rid'] = (int)$logData['rid'];
    } else {
        return 0;
    }

    if (isset($logData['points'])) {
        $insertData['points'] = (float)$logData['points'];
    }

    if (isset($logData['dateline'])) {
        $insertData['dateline'] = (int)$logData['dateline'];
    } else {
        $insertData['dateline'] = TIME_NOW;
    }

    if (isset($logData['coreReputationID'])) {
        $insertData['coreReputationID'] = (float)$logData['coreReputationID'];
    }

    $hookArguments = [
        'reputationValue' => &$reputationValue,
        'insertData' => &$insertData
    ];

    $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_insert_start', $hookArguments);

    $hookArguments['logID'] = $logID = (int)$db->insert_query('ougc_customrep_log', $insertData);

    if ($reputationValue !== REPUTATION_TYPE_NONE) {
        $reputationInsertData = [
            'pid' => $insertData['pid'],
            'uid' => $postUserID,
            'adduid' => (int)$mybb->user['uid'],
            'reputation' => $reputationValue,
            'comments' => '',
            'lid' => $logID,
            'dateline' => TIME_NOW
        ];

        $hookArguments['reputationInsertData'] = &$reputationInsertData;

        $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_insert_reputation', $hookArguments);

        $hookArguments['reputationID'] = $reputationID = (int)$db->insert_query('reputation', $reputationInsertData);

        if ($reputationID) {
            alertInsert($postUserID, 'rep', $insertData['pid']);

            reputationSync($postUserID);
        }
    }

    if ($logID) {
        alertInsert($postUserID, 'ougc_customrep', $logID, [
            'pid' => $insertData['pid'],
            'tid' => $postData['tid'],
            'fid' => $postData['fid'],
            'rid' => $insertData['rid'],
        ]);
    }

    $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_insert_end', $hookArguments);

    return $logID;
}

function logUpdate(int $logID, array $newLogData = []): bool
{
    global $db;

    $updateData = [];

    if (isset($newLogData['pid'])) {
        $updateData['pid'] = (int)$newLogData['pid'];
    }

    if (isset($newLogData['uid'])) {
        $updateData['uid'] = (int)$newLogData['uid'];
    }

    if (isset($newLogData['rid'])) {
        $updateData['rid'] = (int)$newLogData['rid'];
    }

    if (isset($newLogData['dateline'])) {
        $updateData['dateline'] = (int)$newLogData['dateline'];
    }

    if ($updateData) {
        $db->update_query('ougc_customrep_log', $updateData, "lid='{$logID}'");

        if (!empty($updateData['pid'])) {
            $query = $db->simple_select('reputation', 'rid, uid', "lid='{$logID}'");

            while ($reputationData = $db->fetch_array($query)) {
                $reputationID = (int)$reputationData['rid'];

                $db->update_query('reputation', ['pid' => $updateData['pid']], "rid='{$reputationID}'");

                reputationSync((int)$reputationData['uid']);
            }
        }

        return true;
    }

    return false;
}

function logDelete(int $logID): bool
{
    global $mybb;
    global $db, $plugins;

    $reputationDeleteObjects = [];

    $hookArguments = [
        'logID' => $logID,
        'reputationDeleteObjects' => &$reputationDeleteObjects
    ];

    $query = $db->simple_select('reputation', 'rid, uid, pid', "lid='{$logID}'");

    while ($reputationData = $db->fetch_array($query)) {
        $reputationDeleteObjects[(int)$reputationData['rid']] = [
            'userID' => (int)$reputationData['uid'],
            'postID' => (int)$reputationData['pid']
        ];
    }

    $hookArguments = $plugins->run_hooks('ougc_custom_reputation_log_delete_start', $hookArguments);

    foreach ($reputationDeleteObjects as $reputationID => $reputationData) {
        $db->delete_query('reputation', "rid='{$reputationID}'");

        reputationSync($reputationData['userID']);

        alertDelete($reputationData['userID'], (int)$mybb->user['uid'], $reputationData['postID']);
    }

    $db->delete_query('ougc_customrep_log', "lid='{$logID}'");

    return true;
}

function logAdminAction(int $rateID = 0)
{
    if (!empty($rateID)) {
        log_admin_action($rateID);
    } else {
        log_admin_action();
    }
}

function alertsIsInstalled(): bool
{
    return function_exists('myalerts_create_instances') && class_exists('MybbStuff_MyAlerts_AlertFormatterManager');
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

function alertInsert(int $userID, string $alertType = 'rep', int $objectID = 0, array $extraDetails = []): bool
{
    if (!alertsIsInstalled()) {
        return false;
    }

    $alertType = alertsObject()->getByCode($alertType);

    if ($alertType != null && $alertType->getEnabled()) {
        $alert = new MybbStuff_MyAlerts_Entity_Alert($userID, $alertType, $objectID);

        if ($extraDetails) {
            $alert->setExtraDetails($extraDetails);
        }

        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
    }

    return true;
}

function alertDelete(int $userID, int $fromUserID, int $objectID): bool
{
    if (!alertsIsInstalled()) {
        return false;
    }

    global $db;

    $alertType = alertsObject()->getByCode('rep');

    if ($alertType != null && $alertType->getEnabled()) {
        $alertType = (int)$alertType;

        $db->delete_query(
            'alerts',
            "uid='{$userID}' AND from_user_id='{$fromUserID}' AND alert_type_id='{$alertType}' AND object_id='{$objectID}'"
        );
    }

    return true;
}