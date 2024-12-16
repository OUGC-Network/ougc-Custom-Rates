<?php

/***************************************************************************
 *
 *    OUGC Custom Reputation plugin (/inc/plugins/ougc/CustomReputation/hooks/forum.php)
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

namespace ougc\CustomReputation\Hooks\Forum;

use MyBB;
use MybbStuff_MyAlerts_AlertFormatterManager;
use ougc\CustomReputation\Core\MyAlertsFormatter;

use function ougc\CustomReputation\Core\alertsIsInstalled;
use function ougc\CustomReputation\Core\getTemplate;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\logDelete;
use function ougc\CustomReputation\Core\logGet;
use function ougc\CustomReputation\Core\logInsert;

use function ougc\CustomReputation\Core\logUpdate;

use function ougc\CustomReputation\Core\rateGetImage;

use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEGATIVE;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEUTRAL;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_POSITIVE;
use const ougc\CustomReputation\Core\POST_VISIBLE_STATUS_DRAFT;
use const ougc\CustomReputation\ROOT;

function global_start(): bool
{
    global $templatelist, $mybb, $lang;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    if (defined('THIS_SCRIPT')) {
        if (in_array(
            THIS_SCRIPT,
            [
                'forumdisplay.php',
                'portal.php',
                'reputation.php',
                'showthread.php',
                'editpost.php',
                'member.php',
                'attachment.php'
            ]
        )) {
            $templatelist .= 'ougccustomrep_headerinclude, ougccustomrep_headerinclude_fa, ougccustomrep_rep_number, ougccustomrep_rep_img, ougccustomrep_rep_img_fa, ougccustomrep_rep, ougccustomrep_rep_fa, ougccustomrep, ougccustomrep_rep_voted, ougccustomrep_xthreads_js, ougccustomrep_headerinclude_xthreads_editpost, ougccustomrep_headerinclude_xthreads';
        }

        if (THIS_SCRIPT === 'alerts.php') {
            loadLanguage();
        }
    }

    if (alertsIsInstalled()) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        require_once ROOT . '/class_alerts.php';

        $formatterManager->registerFormatter(
            new MyAlertsFormatter($mybb, $lang, 'ougc_customrep')
        );
    }

    return true;
}

function reputation_do_add_process(): bool
{
    global $reputation, $existing_reputation;

    if (empty($reputation['pid'])) {
        return false;
    }

    $postID = (int)$reputation['pid'];

    $postData = get_post($postID);

    if (empty($postData['pid'])) {
        return false;
    }

    global $mybb, $db;

    $userID = (int)$mybb->user['uid'];

    $threadData = get_thread($postData['tid']);

    global $customReputationObjects, $existingCustomReputationLogs;

    $customReputationObjects = [];

    foreach ($mybb->cache->read('ougc_customrep') as $customReputationID => $customReputationData) {
        if (empty($customReputationData['createCoreReputationType']) || !is_member(
                $customReputationData['groups']
            ) || !is_member(
                $customReputationData['forums'],
                ['usergroup' => $postData['fid'], 'additionalgroups' => '']
            ) || (!empty($customReputationData['firstpost']) && $postID !== (int)$threadData['firstpost'])/* || (!empty($customReputationData['points']) && (float)$mybb->user['newpoints'] < (float)$customReputationData['points'])*/) {
            continue;
            // todo, currently ignores the newpoints setting
        }

        $customReputationObjects[$customReputationID] = [
            //'allowdeletion' => $customReputationData['allowdeletion'],
            'inmultiple' => $customReputationData['inmultiple'],
            'createCoreReputationType' => $customReputationData['createCoreReputationType'],
        ];
    }

    $customReputationIDs = implode("','", array_keys($customReputationObjects));

    $dbQuery = $db->simple_select(
        'ougc_customrep_log',
        'lid, rid, coreReputationID',
        "pid='{$postID}' AND uid='{$userID}' AND rid IN ('{$customReputationIDs}')"
    );

    $existingCustomReputationLogs = [];

    while ($logData = $db->fetch_array($dbQuery)) {
        isset($existingCustomReputationLogs[(int)$logData['rid']]) || $existingCustomReputationLogs[(int)$logData['rid']] = [];

        $existingCustomReputationLogs[(int)$logData['rid']][(int)$logData['lid']] = (int)$logData['coreReputationID'];
    }

    return true;
}

function reputation_do_add_end(): bool
{
    global $mybb, $db;
    global $uid, $existing_reputation;
    global $customReputationObjects, $existingCustomReputationLogs;

    $isExistingReputation = !empty($existing_reputation['uid']);

    if ($isExistingReputation) {
        //return false;
    }

    $userID = (int)$mybb->user['uid'];

    $postID = $mybb->get_input('pid', MyBB::INPUT_INT);

    $reputationValue = $mybb->get_input('reputation', MyBB::INPUT_INT);

    $dbQuery = $db->simple_select(
        'reputation',
        'rid',
        "uid='{$uid}' AND adduid='{$userID}' AND pid='{$postID}'"
    );

    $currentCoreReputationID = (int)$db->fetch_field($dbQuery, 'rid');

    foreach ($customReputationObjects as $customReputationID => $customReputationData) {
        $executeCustomReputation = false;

        if ((int)$customReputationData['createCoreReputationType'] === CORE_REPUTATION_TYPE_POSITIVE && $reputationValue > 0) {
            $executeCustomReputation = $reputationValue;
        } elseif ((int)$customReputationData['createCoreReputationType'] === CORE_REPUTATION_TYPE_NEUTRAL && $reputationValue === 0) {
            $executeCustomReputation = 0;
        } elseif ((int)$customReputationData['createCoreReputationType'] === CORE_REPUTATION_TYPE_NEGATIVE && $reputationValue < 0) {
            $executeCustomReputation = $reputationValue;
        }

        if (!isset($existingCustomReputationLogs[$customReputationID])) {
            if ($executeCustomReputation === false) {
                continue;
            }

            if (empty($customReputationData['inmultiple'])) {
                // todo, check if there are existing rates which don't allow in multiple
                // if so, then this rate should not be inserted
            }

            $insertData = [
                'pid' => $postID,
                'rid' => (int)$customReputationID,
                'coreReputationID' => $currentCoreReputationID,
            ];

            $logID = logInsert($insertData);

            $db->update_query(
                'reputation',
                ['ougcCustomReputationCreatedOnLogID' => $logID],
                "rid='{$currentCoreReputationID}'"
            );
        } else {
            foreach ($existingCustomReputationLogs[$customReputationID] as $exitingLogID => $coreReputationID) {
                $existingLogData = logGet((int)$exitingLogID);

                if ($executeCustomReputation === false && !empty($existingLogData['lid'])) {
                    logDelete((int)$exitingLogID);

                    $db->update_query(
                        'reputation',
                        ['ougcCustomReputationCreatedOnLogID' => 0],
                        "ougcCustomReputationCreatedOnLogID='{$exitingLogID}'"
                    );
                }
            }
        }
    }

    return true;
}

function reputation_delete_end(): bool
{
    global $existing_reputation;

    if (empty($existing_reputation['ougcCustomReputationCreatedOnLogID'])) {
        return false;
    }

    logDelete((int)$existing_reputation['ougcCustomReputationCreatedOnLogID']);

    return true;
}

function member_profile_end(): bool
{
    global $db, $mybb, $memprofile, $lang, $theme, $footer;

    $memprofile['customrep'] = '';

    if (empty($mybb->settings['ougc_customrep_stats_profile'])) {
        return false;
    }

    $fontAwesomeCode = '';

    if ($mybb->settings['ougc_customrep_fontawesome']) {
        $fontAwesomeCode = eval(getTemplate('headerinclude_fa'));
    }

    $font_awesome = &$fontAwesomeCode;

    $customThreadFieldsVariables = $customThreadFieldsHideSkip = $customThreadFieldsVariablesEditPost = '';
    $xthreads_variables = $xthreads_hideskip = $xthreads_variables_editpost = '';

    $footer .= eval(getTemplate('headerinclude'));

    loadLanguage();

    $whereClauses = ["t.visible='1'", "t.closed NOT LIKE 'moved|%'", "p.visible='1'"];

    $unviewableForumsIDs = get_unviewable_forums(true);

    if ($unviewableForumsIDs) {
        $whereClauses[] = "t.fid NOT IN ($unviewableForumsIDs)";
        $whereClauses[] = "t.fid NOT IN ($unviewableForumsIDs)";
    }

    $inactiveForumsIDs = get_inactive_forums();

    if ($inactiveForumsIDs) {
        $whereClauses[] = "t.fid NOT IN ($inactiveForumsIDs)";
        $whereClauses[] = "t.fid NOT IN ($inactiveForumsIDs)";
    }

    $ratesCache = (array)$mybb->cache->read('ougc_customrep');

    $ratesIDs = implode("','", array_keys($ratesCache));

    $whereClauses[] = "l.rid IN ('{$ratesIDs}')";

    $memprofile['uid'] = (int)$memprofile['uid'];

    $whereClauses['user'] = "l.uid='{$memprofile['uid']}'";

    $statsGiven = $statsReceived = [];

    $query = $db->simple_select(
        "ougc_customrep_log l LEFT JOIN {$db->table_prefix}posts p ON (p.pid=l.pid) LEFT JOIN {$db->table_prefix}threads t ON (t.tid=p.tid)",
        'l.rid',
        implode(' AND ', $whereClauses)
    );

    while ($rateID = (int)$db->fetch_field($query, 'rid')) {
        ++$statsGiven[$rateID];
    }

    $whereClauses['user'] = "p.uid='{$memprofile['uid']}'";

    $imageTemplateName = 'rep_img';

    if (empty($mybb->settings['ougc_customrep_fontawesome'])) {
        $imageTemplateName = 'rep_img_fa';
    }

    $query = $db->simple_select(
        'ougc_customrep_log l LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid=l.pid) LEFT JOIN ' . TABLE_PREFIX . 'threads t ON (t.tid=p.tid)',
        'l.rid',
        implode(' AND ', $whereClauses)
    );

    while ($rateID = (int)$db->fetch_field($query, 'rid')) {
        ++$statsReceived[$rateID];
    }

    $userRatesReceivedCode = $userRatesGivenCode = '';

    foreach (
        [
            ['statsCache' => &$statsReceived, 'ratesCode' => &$userRatesReceivedCode],
            ['statsCache' => &$statsGiven, 'ratesCode' => &$userRatesGivenCode]
        ] as &$buildParams
    ) {
        $ratesListCode = '';

        $trow = alt_trow(true);

        foreach ($ratesCache as $rateID => &$rateData) {
            if (empty($buildParams['statsCache'][$rateID])) {
                continue;
            }

            $rateName = $rateTitleText = $lang_val = htmlspecialchars_uni($rateData['name']);

            $totalReceivedRates = $number = my_number_format($buildParams['statsCache'][$rateID]);

            $totalReceivedRates = $number = eval(getTemplate('profile_number'));

            $rateImage = rateGetImage($rateData['image'], (int)$rateID);

            $reputation = ['image' => $rateImage, 'name' => $rateName];

            $rateImage = eval(getTemplate($imageTemplateName, false));

            $rid = &$rateID;

            $image = &$rateImage;

            $postID = 0;

            $ratesListCode .= eval(getTemplate('rep'));

            $trow = alt_trow();
        }

        $ratesListCode = trim($ratesListCode);

        $reputations = &$ratesListCode;

        if (!$ratesListCode) {
            $buildParams['ratesCode'] = eval(getTemplate('profile_empty'));
        } else {
            $buildParams['ratesCode'] = eval(getTemplate('profile_row'));
        }
    }

    $rates_received = &$userRatesReceivedCode;

    $rates_given = &$userRatesGivenCode;

    $lang->ougc_customrep_profile_stats = $lang->sprintf($lang->ougc_customrep_profile_stats, $memprofile['username']);

    $memprofile['customrep'] = eval(getTemplate('profile'));

    return true;
}

function editpost_end(): bool
{
    global $mybb;
    global $footer;
    global $threadfields;

    $fontAwesomeCode = '';

    if (empty($mybb->settings['ougc_customrep_fontawesome'])) {
        $fontAwesomeCode = eval(getTemplate('headerinclude_fa'));
    }

    $font_awesome = &$fontAwesomeCode;

    $customThreadFieldsVariablesEditPost = $customThreadFieldsHideSkip = '';

    if (!empty($mybb->settings['ougc_customrep_xthreads_hide'])) {
        $customThreadFieldsKeys = explode(
            ',',
            str_replace('_require', '', $mybb->settings['ougc_customrep_xthreads_hide'])
        );

        foreach ($customThreadFieldsKeys as $customThreadFieldKey) {
            $xt_field = &$customThreadFieldKey;

            if (!isset($threadfields[$customThreadFieldKey])) {
                continue;
            }

            $customThreadFieldsHideSkip .= eval(getTemplate('headerinclude_xthreads_editpost_hidecode'));

            $customThreadFieldDefaultValue = (int)$threadfields[$customThreadFieldKey . '_require'];

            if (isset($mybb->input['xthreads_ougc_customrep'])) {
                $customThreadFieldDefaultValue = $mybb->get_input('xthreads_ougc_customrep', MyBB::INPUT_INT);
            }

            $default_value = &$customThreadFieldDefaultValue;

            $customThreadFieldsVariablesEditPost .= eval(getTemplate('headerinclude_xthreads_editpost', false));
        }
    }

    $xthreads_hideskip = &$customThreadFieldsHideSkip;

    $xthreads_variables_editpost = &$customThreadFieldsVariablesEditPost;

    $customThreadFieldsVariables = '';

    $footer .= eval(getTemplate('headerinclude'));

    return true;
}

function attachment_start(): bool
{
    global $mybb, $cache, $db, $lang;
    global $attachment;

    if (empty($attachment['aid']) || isset($mybb->input['thumbnail'])) {
        return false;
    }

    if (empty($attachment['thumbnail']) && isset($mybb->input['thumbnail'])) {
        return true;
    }

    $attachmentTypesCache = (array)$cache->read('attachtypes');

    $attachmentExtension = get_extension($attachment['filename']);

    if (empty($attachmentTypesCache[$attachmentExtension])) {
        return true;
    }

    $postID = (int)$attachment['pid'];

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    if (empty($postID) || (int)$attachment['uid'] === $mybb->user['uid']) {
        return true;
    }

    $postData = get_post($postID);

    if (empty($postData['pid']) || (int)$postData['visible'] === POST_VISIBLE_STATUS_DRAFT) {
        return true;
    }

    $thread = get_thread($postData['tid']);

    if (empty($thread['tid'])) {
        return true;
    }

    $ratesCache = (array)$cache->read('ougc_customrep');

    $isFirstPost = (int)$thread['firstpost'] === $postID;

    if (!empty($mybb->settings['ougc_customrep_firstpost']) && !$isFirstPost) {
        return true;
    }

    $requiredRatesIDs = [];

    foreach ($ratesCache as $rateID => $rateData) {
        if (
            !empty($rateData['requireattach']) &&
            is_member($rateData['groups']) &&
            is_member($rateData['forums'], ['usergroup' => $thread['fid'], 'additionalgroups' => '']) &&
            (!$isFirstPost || !empty($rateData['firstpost']))
        ) {
            $requiredRatesIDs[(int)$rateID] = 1;
        }
    }

    if (empty($requiredRatesIDs)) {
        return true;
    }

    $rateIDs = implode("','", array_keys($requiredRatesIDs));

    $query = $db->simple_select(
        'ougc_customrep_log',
        'lid',
        "pid='{$postID}' AND rid IN ('{$rateIDs}') AND uid='{$mybb->user['uid']}'"
    );

    if ($db->num_rows($query)) {
        return true;
    }

    loadLanguage();

    error($lang->ougc_customrep_error_nopermission_attachment);

    return false;
}

function class_moderation_delete_thread_start(int &$threadID): int
{
    global $db;

    $postIDs = [];

    $query = $db->simple_select('posts', 'pid', "tid='{$threadID}'");

    while ($postID = (int)$db->fetch_field($query, 'pid')) {
        $postIDs[$postID] = 1;
    }

    if ($postIDs) {
        $postIDs = implode("','", array_keys($postIDs));

        $query = $db->simple_select('ougc_customrep_log', 'lid', "pid IN ('{$postIDs}')");

        while ($logID = (int)$db->fetch_field($query, 'lid')) {
            logDelete($logID);
        }
    }

    return $threadID;
}

function class_moderation_delete_post_start(&$postID): int
{
    global $db;

    $postID = (int)$postID;

    $query = $db->simple_select('ougc_customrep_log', 'lid', "pid='{$postID}'");

    while ($logID = (int)$db->fetch_field($query, 'lid')) {
        logDelete($logID);
    }

    return $postID;
}

function class_moderation_merge_posts(array &$hookArguments): array
{
    global $db;

    $postIDs = implode("','", array_map('intval', $hookArguments['pids']));

    $query = $db->simple_select(
        'posts',
        'pid',
        "pid IN ('{$postIDs}')",
        ['limit' => 1, 'order_by' => 'dateline', 'order_dir' => 'asc']
    );

    $masterPostID = (int)$db->fetch_field($query, 'pid');

    // First get all the logs attached to these posts
    $query = $db->simple_select('ougc_customrep_log', 'lid', "pid IN ('{$postIDs}')");

    while ($logID = (int)$db->fetch_field($query, 'lid')) {
        logUpdate($logID, ['pid' => $masterPostID]);
    }

    return $hookArguments;
}

// todo, class_moderation_split_posts maybe?
// todo, maybe allowdeletion should be checked when deleting a reputation to disable ?