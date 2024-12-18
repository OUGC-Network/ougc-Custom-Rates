<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/ougc/CustomReputation/hooks/forum.php)
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

namespace ougc\CustomRates\Hooks\Forum;

use MyBB;
use MybbStuff_MyAlerts_AlertFormatterManager;
use ougc\CustomRates\Core\MyAlertsFormatter;
use postParser;

use function ougc\CustomRates\Core\alertsIsInstalled;
use function ougc\CustomRates\Core\forumGetRates;
use function ougc\CustomRates\Core\getTemplate;
use function ougc\CustomRates\Core\isAllowedForum;
use function ougc\CustomRates\Core\loadLanguage;
use function ougc\CustomRates\Core\logDelete;
use function ougc\CustomRates\Core\logGet;
use function ougc\CustomRates\Core\logInsert;
use function ougc\CustomRates\Core\logUpdate;
use function ougc\CustomRates\Core\modalRender;
use function ougc\CustomRates\Core\modalRenderError;
use function ougc\CustomRates\Core\outputAjaxData;
use function ougc\CustomRates\Core\postRatesParse;
use function ougc\CustomRates\Core\rateGet;
use function ougc\CustomRates\Core\rateGetImage;
use function ougc\CustomRates\Core\rateGetName;
use function ougc\CustomRates\Core\urlHandlerBuild;
use function ougc\CustomRates\Core\urlHandlerSet;

use const ougc\CustomRates\Core\CORE_REPUTATION_TYPE_NEGATIVE;
use const ougc\CustomRates\Core\CORE_REPUTATION_TYPE_NEUTRAL;
use const ougc\CustomRates\Core\CORE_REPUTATION_TYPE_POSITIVE;
use const ougc\CustomRates\Core\POST_VISIBLE_STATUS_DRAFT;
use const ougc\CustomRates\ROOT;

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
    global $existing_reputation;

    if (!empty($existing_reputation['uid'])) {
        return false;
    }

    global $reputation;

    if (empty($reputation['pid'])) {
        return false;
    }

    $postID = (int)$reputation['pid'];

    $postData = get_post($postID);

    if (empty($postData['pid'])) {
        return false;
    }

    global $mybb, $db, $plugins;
    global $customReputationObjects, $existingCustomReputationLogs;

    $customReputationObjects = [];

    $hookArguments = [];

    $userID = (int)$mybb->user['uid'];

    $threadData = get_thread($postData['tid']);

    foreach ((array)$mybb->cache->read('ougc_customrep') as $rateID => $rateData) {
        $hookArguments['rateData'] = &$rateData;

        $hookArguments = $plugins->run_hooks('ougc_custom_rates_reputation_add_process_start', $hookArguments);

        if (
            empty($rateData['createCoreReputationType']) ||
            !is_member($rateData['groups']) ||
            !is_member($rateData['forums'], ['usergroup' => $postData['fid'], 'additionalgroups' => '']) ||
            (!empty($rateData['firstpost']) && $postID !== (int)$threadData['firstpost'])
        ) {
            continue;
        }

        $customReputationObjects[$rateID] = [
            //'allowdeletion' => $rateData['allowdeletion'],
            'inmultiple' => $rateData['inmultiple'],
            'createCoreReputationType' => $rateData['createCoreReputationType'],
        ];
    }

    $rateIDs = implode("','", array_keys($customReputationObjects));

    $dbQuery = $db->simple_select(
        'ougc_customrep_log',
        'lid, rid, coreReputationID',
        "pid='{$postID}' AND uid='{$userID}' AND rid IN ('{$rateIDs}')"
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

    if (!empty($existing_reputation['uid'])) {
        return false;
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

    foreach ($customReputationObjects as $rateID => $rateData) {
        $executeCustomReputation = false;

        if ((int)$rateData['createCoreReputationType'] === CORE_REPUTATION_TYPE_POSITIVE && $reputationValue > 0) {
            $executeCustomReputation = $reputationValue;
        } elseif ((int)$rateData['createCoreReputationType'] === CORE_REPUTATION_TYPE_NEUTRAL && $reputationValue === 0) {
            $executeCustomReputation = 0;
        } elseif ((int)$rateData['createCoreReputationType'] === CORE_REPUTATION_TYPE_NEGATIVE && $reputationValue < 0) {
            $executeCustomReputation = $reputationValue;
        }

        if (!isset($existingCustomReputationLogs[$rateID])) {
            if ($executeCustomReputation === false) {
                continue;
            }

            if (empty($rateData['inmultiple'])) {
                // todo, check if there are existing rates which don't allow in multiple
                // if so, then this rate should not be inserted
            }

            $insertData = [
                'pid' => $postID,
                'rid' => (int)$rateID,
                'coreReputationID' => $currentCoreReputationID,
            ];

            $logID = logInsert($insertData);

            $db->update_query(
                'reputation',
                ['ougcCustomReputationCreatedOnLogID' => $logID],
                "rid='{$currentCoreReputationID}'"
            );
        } else {
            foreach ($existingCustomReputationLogs[$rateID] as $exitingLogID => $coreReputationID) {
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

function forumdisplay_thread(): bool
{
    global $mybb, $db;
    global $fid, $footer, $threadcache;

    static $done = false;

    if ($done) {
        return false;
    }

    $done = true;

    if (empty($mybb->settings['ougc_customrep_threadlist']) || !is_member(
            $mybb->settings['ougc_customrep_threadlist'],
            ['usergroup' => $fid, 'additionalgroups' => '']
        )) {
        return false;
    }

    $forumID = (int)$fid;

    if (!isAllowedForum($forumID)) {
        return false;
    }

    $fontAwesomeCode = '';

    if (!empty($mybb->settings['ougc_customrep_fontawesome'])) {
        $fontAwesomeCode .= eval(getTemplate('headerinclude_fa'));
    }

    $font_awesome = &$fontAwesomeCode;

    $customThreadFieldsVariables = $customThreadFieldsHideSkip = $customThreadFieldsVariablesEditPost = '';
    $xthreads_variables = $xthreads_hideskip = $xthreads_variables_editpost = '';

    $footer .= eval(getTemplate('headerinclude'));

    $postsIDs = [];

    foreach ($threadcache as $threadData) {
        $postsIDs[] = (int)$threadData['firstpost'];
    }

    if (empty($postsIDs)) {
        return false;
    }

    $postsIDs = implode("','", $postsIDs);

    $forumRatesCache = forumGetRates($forumID);

    $ratesIDs = implode("','", array_keys($forumRatesCache));

    $query = $db->simple_select(
        'ougc_customrep_log',
        '*',
        "pid IN ('{$postsIDs}') AND rid IN ('{$ratesIDs}')"
    );

    global $customReputationCacheQuery;

    is_array($customReputationCacheQuery) || $customReputationCacheQuery = [];

    while ($logData = $db->fetch_array($query)) {
        $customReputationCacheQuery[$logData['rid']][$logData['pid']][$logData['lid']][$logData['uid']] = 1;
    }

    return true;
}

function forumdisplay_thread_end(): bool
{
    global $thread;

    if (my_substr($thread['closed'], 0, 6) === 'moved|') {
        return false;
    }

    urlHandlerSet(get_thread_link($thread['tid']));

    postRatesParse($thread, (int)$thread['firstpost']);

    return true;
}

function portal_announcement(): bool
{
    global $mybb, $db;
    global $footer;
    global $tids, $annfidswhere, $tunviewwhere, $announcement;

    if (empty($mybb->settings['ougc_customrep_portal'])) {
        return false;
    }

    if (!is_member(
        $mybb->settings['ougc_customrep_portal'],
        ['usergroup' => $announcement['fid'], 'additionalgroups' => '']
    )) {
        return false;
    }

    static $ratesCachePortal = null;

    if ($ratesCachePortal !== null && empty($ratesCachePortal)) {
        return false;
    }

    if ($ratesCachePortal === null) {
        $ratesCachePortal = [];

        $query = $db->simple_select(
            'threads t',
            't.firstpost, t.fid',
            "t.tid IN (0{$tids}){$annfidswhere}{$tunviewwhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'"
        );

        $postIDs = $forumIDs = [];

        while ($threadData = $db->fetch_array($query)) {
            $forumIDs[] = (int)$threadData['fid'];

            $postIDs[] = (int)$threadData['firstpost'];
        }

        if (empty($postIDs)) {
            return false;
        }

        foreach ($forumIDs as $forumID) {
            $ratesCachePortal[$forumID] = isAllowedForum($forumID);
        }

        if (empty($ratesCachePortal)) {
            return false;
        }

        $postIDs = implode("','", $postIDs);

        $forumRatesCache = forumGetRates((int)$announcement['fid']);

        $ratesIDs = implode("','", array_keys($forumRatesCache));

        $query = $db->simple_select(
            'ougc_customrep_log',
            'rid, pid, lid, uid',
            "pid IN ('{$postIDs}') AND rid IN ('{$ratesIDs}')"
        );

        global $customReputationCacheQuery;

        is_array($customReputationCacheQuery) || $customReputationCacheQuery = [];

        while ($logData = $db->fetch_array($query)) {
            $customReputationCacheQuery[$logData['rid']][$logData['pid']][$logData['lid']][$logData['uid']] = 1;
        }

        $fontAwesomeCode = '';

        if (!empty($mybb->settings['ougc_customrep_fontawesome'])) {
            $fontAwesomeCode .= eval(getTemplate('headerinclude_fa'));
        }

        $font_awesome = &$fontAwesomeCode;

        $customThreadFieldsVariables = $customThreadFieldsHideSkip = $customThreadFieldsVariablesEditPost = '';
        $xthreads_variables = $xthreads_hideskip = $xthreads_variables_editpost = '';

        $footer .= eval(getTemplate('headerinclude'));
    }

    if (empty($ratesCachePortal[$announcement['fid']])) {
        return false;
    }

    urlHandlerSet(get_thread_link($announcement['tid']));

    postRatesParse($announcement, (int)$announcement['firstpost']);

    return true;
}

function reputation_start(): bool
{
    global $mybb;

    if ($mybb->get_input('action') !== 'delete') {
        return false;
    }

    verify_post_check($mybb->get_input('my_post_key'));

    global $db;

    $reputationID = $mybb->get_input('rid', 1);

    $query = $db->simple_select(
        "reputation r LEFT JOIN {$db->table_prefix}users u ON (u.uid=r.adduid)",
        'r.adduid, r.lid, u.uid, u.username',
        "r.rid='{$reputationID}'"
    );

    $reputationData = $db->fetch_array($query);

    if (
        empty($mybb->usergroup['cancp']) &&
        empty($mybb->usergroup['issupermod']) &&
        (int)$reputationData['adduid'] !== (int)$mybb->user['uid']
    ) {
        error_no_permission();
    }

    if ($reputationData['lid'] > 0) {
        logDelete((int)$reputationData['lid']);

        global $uid, $user, $lang;

        log_moderator_action(
            ['uid' => $user['uid'], 'username' => $user['username']],
            $lang->sprintf($lang->delete_reputation_log, $reputationData['username'], $reputationData['adduid'])
        );

        redirect("reputation.php?uid={$uid}", $lang->vote_deleted_message);
    }

    return true;
}

function showthread_start09()
{
    global $mybb;

    if (!in_array($mybb->get_input('action'), ['customReputation', 'customReputationPopUp'])) {
        return;
    }

    global $templates, $lang;
    global $tid, $thread, $fid, $errorFunction;

    loadLanguage();

    $forumID = (int)$fid;

    $ajaxIsEnabled = !empty($mybb->settings['use_xmlhttprequest']) && !empty($mybb->settings['ougc_customrep_enableajax']);

    urlHandlerSet(get_thread_link($tid)); //TODO

    $templates->cache(
        'ougccustomrep_misc_row, ougccustomrep_misc_error, ougccustomrep_misc_multipage, ougccustomrep_misc, ougccustomrep_postbit_reputation, ougccustomrep_modal'
    );

    $errorFunction = 'error';

    if ($mybb->get_input('action') == 'customReputationPopUp') {
        $errorFunction = '\ougc\CustomRates\Core\modalRenderError';
    } elseif ($ajaxIsEnabled) {
        $errorFunction = '\ougc\CustomRates\Core\ajaxError';
    }

    $rateID = $mybb->get_input('rid', MyBB::INPUT_INT);

    if (!($rateData = rateGet($rateID))) {
        $errorFunction($lang->ougc_customrep_error_invalidrep);
    }

    $forumRatesCache = forumGetRates($forumID);

    if (empty($rateData['visible']) || !array_key_exists($rateID, $forumRatesCache)) {
        $errorFunction($lang->ougc_customrep_error_invalidrep);
    }

    $post = get_post($mybb->get_input('pid', 1));

    $firstPostID = (int)$thread['firstpost'];

    $postID = (int)($post['pid'] ?? 0);

    $postUserID = (int)($post['uid'] ?? 0);

    $postThreadID = (int)($post['tid'] ?? 0);

    $firstPostOnly = !empty($mybb->settings['ougc_customrep_firstpost']) || !empty($rateData['firstpost']);

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    if ($mybb->get_input('action') == 'customReputationPopUp') {
        if (!$mybb->user['uid'] && empty($mybb->settings['ougc_customrep_guests_popup'])) {
            modalRenderError($lang->ougc_customrep_error_nopermission_guests);
        }

        if (!isAllowedForum($forumID)) {
            modalRenderError($lang->ougc_customrep_error_invalidforum);
        }

        if (empty($post)) {
            modalRenderError($lang->ougc_customrep_error_invlidadpost);
        }

        if ($firstPostOnly && $postID !== $firstPostID) {
            modalRenderError($lang->ougc_customrep_error_invlidadpost);
        }

        global $db, $theme, $headerinclude, $parser;

        if (!is_object($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';

            $parser = new postParser();
        }

        $ratePopUpUrl = urlHandlerBuild([
            'pid' => $postID,
            'my_post_key' => $mybb->post_code,
            'action' => 'customReputationPopUp',
            'rid' => $rateID
        ]);

        $query = $db->simple_select(
            'ougc_customrep_log',
            'COUNT(lid) AS totalPostLogs',
            "pid='{$postID}' AND rid='{$rateID}'"
        );

        $totalPostLogs = (int)$db->fetch_field($query, 'totalPostLogs');

        $currentPage = $mybb->get_input('page', 1);

        $perPage = (int)$mybb->settings['ougc_customrep_perpage'];

        if ($currentPage > 0) {
            $startPage = ($currentPage - 1) * $perPage;

            $totalPages = ceil($totalPostLogs / $perPage);

            if ($currentPage > $totalPages) {
                $startPage = 0;

                $currentPage = 1;
            }
        } else {
            $startPage = 0;

            $currentPage = 1;
        }

        urlHandlerSet(get_post_link($postID, $postThreadID));

        $pagination = multipage(
            $totalPostLogs,
            $perPage,
            $currentPage,
            "javascript:MyBB.popupWindow('/{$ratePopUpUrl}&amp;page={page}');"
        );

        $query = $db->simple_select(
            "ougc_customrep_log r LEFT JOIN {$db->table_prefix}users u ON (u.uid=r.uid)",
            'r.*, u.username, u.usergroup, u.displaygroup, u.avatar, u.avatartype, u.avatardimensions',
            "r.pid='{$postID}' AND r.rid='{$rateID}'",
            ['order_by' => 'r.dateline', 'order_dir' => 'DESC', 'limit' => $perPage, 'limit_start' => $startPage]
        );

        $modalContent = '';

        $trow = alt_trow(true);

        global $plugins;

        $hookArguments = [];

        while ($logData = $db->fetch_array($query)) {
            $profileLinkFormatted = build_profile_link(
                format_name(
                    htmlspecialchars_uni($logData['username']),
                    $logData['usergroup'],
                    $logData['displaygroup']
                ),
                $logData['uid'],
                '_blank'
            );

            $hookArguments['profileLinkFormatted'] = &$profileLinkFormatted;

            $hookArguments = $plugins->run_hooks('ougc_custom_rates_reputation_popup_user', $hookArguments);

            $logDate = $lang->sprintf(
                $lang->ougc_customrep_popup_date,
                my_date($mybb->settings['dateformat'], $logData['dateline']),
                my_date($mybb->settings['timeformat'], $logData['dateline'])
            );

            $date = &$logDate;

            $log = ['profilelink_f' => &$profileLinkFormatted];

            $modalContent .= eval(getTemplate('misc_row'));

            $trow = alt_trow();
        }

        if (!$modalContent) {
            $errorMessage = $error_message = $lang->ougc_customrep_popup_empty;

            $modalContent = eval(getTemplate('misc_error'));
        }

        if ($pagination) {
            $multipage = $pagination;

            $pagination = eval(getTemplate('misc_multipage'));
        }

        modalRender(
            $modalContent,
            $lang->sprintf(
                $lang->ougc_customrep_popuptitle,
                htmlspecialchars_uni($rateData['name']),
                $parser->parse_badwords($post['subject'])
            ),
            $lang->sprintf($lang->ougc_customrep_popup_latest, my_number_format($totalPostLogs)),
            $pagination
        );
    }

    if (empty($mybb->user['uid'])) {
        $errorFunction($lang->ougc_customrep_error_nopermission);
    }

    verify_post_check($mybb->get_input('my_post_key'));

    if (empty($post)) {
        $errorFunction($lang->ougc_customrep_error_invlidadpost);
    }

    if ($mybb->user['uid'] === $postUserID) {
        $errorFunction($lang->ougc_customrep_error_selftrating);
    }

    if (!isAllowedForum($forumID)) {
        $errorFunction($lang->ougc_customrep_error_invalidforum);
    }

    if (!is_member($rateData['groups'])) {
        $errorFunction($lang->ougc_customrep_error_nopermission);
    }

    if ($postThreadID !== (int)$thread['tid'] || $firstPostOnly && $postID !== $firstPostID) {
        $errorFunction($lang->ougc_customrep_error_invlidadpost);
    }

    global $db;

    if ($mybb->get_input('delete', 1) == 1) {
        if (empty($mybb->settings['ougc_customrep_delete'])) {
            $errorFunction($lang->ougc_customrep_error_nopermission);
        }

        if (!$rateData['allowdeletion']) {
            $errorFunction($lang->ougc_customrep_error_nopermission_rate);
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            'uid, lid',
            "pid='{$postID}' AND uid='{$mybb->user['uid']}' AND rid='{$rateID}'"
        );

        if (!$db->num_rows($query)) {
            $errorFunction($lang->ougc_customrep_error_invalidrating);
        }

        while ($logData = $db->fetch_array($query)) {
            logDelete((int)$logData['lid']);
        }
    } else {
        if (!empty($mybb->settings['ougc_customrep_multiple'])) {
            if (!empty($rateData['inmultiple'])) {
                $query = $db->simple_select(
                    'ougc_customrep_log',
                    'rid',
                    "pid='{$postID}' AND uid='{$mybb->user['uid']}' AND rid='{$rateID}'",
                    ['limit' => 1]
                );

                $userAlreadyRated = (bool)$db->fetch_field($query, 'rid');

                if ($userAlreadyRated) {
                    $errorFunction($lang->ougc_customrep_error_multiple);
                }
            } else {
                $rateIDs = [];

                foreach ($forumRatesCache as $_rateID => $_rateData) {
                    if (empty($_rateData['inmultiple'])) {
                        $rateIDs[(int)$_rateID] = (int)$_rateID;
                    }
                }

                $rateIDs = implode("','", $rateIDs);

                $query = $db->simple_select(
                    'ougc_customrep_log',
                    'lid',
                    "pid='{$postID}' AND uid='{$mybb->user['uid']}' AND rid IN ('{$rateIDs}')",
                    ['limit' => 1]
                );

                $userAlreadyRated = (bool)$db->fetch_field($query, 'lid');

                if ($userAlreadyRated) {
                    $errorFunction($lang->ougc_customrep_error_multiple_single);
                }
            }

            $query = $db->simple_select(
                'ougc_customrep_log',
                'rid',
                "pid='{$postID}' AND uid='{$mybb->user['uid']}' AND rid='{$rateID}'",
                ['limit' => 1]
            );

            $userAlreadyRated = (bool)$db->fetch_field($query, 'rid');
        } else {
            $query = $db->simple_select(
                'ougc_customrep_log',
                'lid',
                "pid='{$postID}' AND uid='{$mybb->user['uid']}'",
                ['limit' => 1]
            );

            $userAlreadyRated = (bool)$db->fetch_field($query, 'lid');
        }

        if ($userAlreadyRated) {
            $errorFunction($lang->ougc_customrep_error_multiple);
        }

        $insertData = [
            'pid' => $postID,
            'rid' => $rateID
        ];

        logInsert($insertData, (int)$rateData['reptype']);
    }

    if (!$ajaxIsEnabled) {
        $mybb->settings['redirects'] = $mybb->user['showredirect'] = 0;

        redirect(get_post_link($postID, $postThreadID) . '#' . $postThreadID);

        exit;
    }

    // > On postbit, the plugin loads ALL votes, and does a summation + check for current user voting on this.  This can potentially be problematic if there happens to be a large number of votes.
    $query = $db->simple_select(
        'ougc_customrep_log',
        'lid, rid, pid, uid',
        "pid='{$postID}'"
    ); //  AND rid='{$rateID}'

    global $customReputationCacheQuery;

    is_array($customReputationCacheQuery) || $customReputationCacheQuery = [];

    while ($logData = $db->fetch_array($query)) {
        $customReputationCacheQuery[$logData['rid']][$logData['pid']][$logData['lid']][$logData['uid']] = 1;
    }

    $query = $db->simple_select('users', 'reputation', "uid='{$postUserID}'");

    $post = [
        'pid' => $postID,
        'userreputation' => get_reputation((int)$db->fetch_field($query, 'reputation'), $postUserID),
        'content' => ''
    ];

    postRatesParse($post, $postID, $rateID);

    $userReputation = &$post['userreputation'];

    outputAjaxData([
        'success' => 1,
        'pid' => $postID,
        'rid' => $rateID,
        'content' => $post['customrep'],
        'content_rep' => $post['customrep_' . $rateData['rid']],
        'userreputation' => eval(getTemplate('postbit_reputation')),
    ]);
}

function postbit(array &$post): array
{
    global $mybb;
    global $fid, $templates, $thread;

    $postID = (int)$post['pid'];

    $firstPostID = (int)$thread['firstpost'];

    $post['customrep'] = $post['customrep_ignorebit'] = $post['customrep_post_visibility'] = '';

    if (!empty($mybb->settings['ougc_customrep_firstpost']) && $postID !== $firstPostID) {
        return $post;
    }

    static $ignoreRules = [];

    global $customReputationCacheQuery;

    $forumID = (int)$fid;

    if (!isAllowedForum($forumID)) {
        return $post;
    }

    if (!isset($customReputationCacheQuery)) {
        $customReputationCacheQuery = [];

        global $mybb;
        global $footer;

        $fontAwesomeCode = '';

        if ($mybb->settings['ougc_customrep_fontawesome']) {
            $fontAwesomeCode .= eval(getTemplate('headerinclude_fa'));
        }

        $font_awesome = &$fontAwesomeCode;

        $customThreadFieldsVariables = '';

        if (!empty($mybb->settings['ougc_customrep_xthreads_hide'])) {
            foreach (
                explode(
                    ',',
                    str_replace('_require', '', $mybb->settings['ougc_customrep_xthreads_hide'])
                ) as $customThreadFieldKey
            ) {
                $xt_field = &$customThreadFieldKey;

                $customThreadFieldsVariables .= eval(getTemplate('headerinclude_xthreads', false));
            }
        }

        $xthreads_variables = &$customThreadFieldsVariables;

        $customThreadFieldsHideSkip = $customThreadFieldsVariablesEditPost = '';
        $xthreads_hideskip = $xthreads_variables_editpost = '';

        $footer .= eval(getTemplate('headerinclude'));

        global $db, $thread;

        $forumsRateCache = forumGetRates($forumID);

        $ratesIDs = implode("','", array_keys($forumsRateCache));

        $whereClauses = ["rid IN ('{$ratesIDs}')"];

        if (!empty($mybb->settings['ougc_customrep_firstpost'])) {
            $whereClauses[] = "pid='{$firstPostID}'";
        } // Bug: http://mybbhacks.zingaburga.com/showthread.php?tid=1587&pid=12762#pid12762
        elseif ($mybb->get_input('mode') == 'threaded') {
            $whereClauses[] = "pid='{$mybb->get_input('pid', 1)}'";
        } elseif (isset($GLOBALS['pids'])) {
            $whereClauses[] = $GLOBALS['pids'];
        } else {
            $whereClauses[] = "pid='{$postID}'";
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            'rid, pid, lid, uid',
            (implode(' AND ', $whereClauses))
        );

        while ($logData = $db->fetch_array($query)) {
            // > The ougc_customrep_log table seems to mostly query on the rid,pid columns - there really should be indexes on these; one would presume that pid,uid should be a unique key as you can't vote for more than once for each post.  The good thing about identifying these uniques is that it could help one simplify something like
            $customReputationCacheQuery[$logData['rid']][$logData['pid']][$logData['lid']][$logData['uid']] = 1; //TODO
            // > where the 'lid' key seems to be unnecessary
        }

        foreach ($forumsRateCache as $rateID => $rateData) {
            if ((int)$rateData['ignorepoints'] && isset($customReputationCacheQuery[$rateID])) {
                $ignoreRules[$rateID] = (int)$rateData['ignorepoints'];
            }
        }
    }

    if (!empty($ignoreRules)) {
        foreach ($ignoreRules as $rateID => $ignorePointsThreshold) {
            if (isset($customReputationCacheQuery[$rateID][$post['pid']]) && count(
                    $customReputationCacheQuery[$rateID][$post['pid']]
                ) >= $ignorePointsThreshold) {
                global $lang, $ignored_message, $ignore_bit, $post_visibility;

                loadLanguage();
                
                $ignored_message = $lang->sprintf($lang->ougc_customrep_postbit_ignoredbit, $post['username']);

                $post['customrep_ignorebit'] = eval($templates->render('postbit_ignored'));

                $post['customrep_post_visibility'] = 'display: none;';

                break;
            }
        }
    }

    postRatesParse($post, $postID);

    return $post;
}

function member_profile_end(): bool
{
    global $db, $mybb, $memprofile, $lang, $theme, $footer;

    $memprofile['customrep'] = '';

    if (empty($mybb->settings['ougc_customrep_stats_profile'])) {
        return false;
    }

    $fontAwesomeCode = '';

    if (!empty($mybb->settings['ougc_customrep_fontawesome'])) {
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
        isset($statsGiven[$rateID]) || $statsGiven[$rateID] = 0;

        ++$statsGiven[$rateID];
    }

    $whereClauses['user'] = "p.uid='{$memprofile['uid']}'";

    $imageTemplateName = 'rep_img';

    if (!empty($mybb->settings['ougc_customrep_fontawesome'])) {
        $imageTemplateName = 'rep_img_fa';
    }

    $query = $db->simple_select(
        'ougc_customrep_log l LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid=l.pid) LEFT JOIN ' . TABLE_PREFIX . 'threads t ON (t.tid=p.tid)',
        'l.rid',
        implode(' AND ', $whereClauses)
    );

    while ($rateID = (int)$db->fetch_field($query, 'rid')) {
        isset($statsReceived[$rateID]) || $statsReceived[$rateID] = 0;

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

            if (!($rateName = rateGetName($rateID))) {
                $rateName = htmlspecialchars_uni($rateData['name']);
            } else {
                $rateName = htmlspecialchars_uni($rateName);
            }

            $rateTitleText = $lang_val = $rateName;

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
        $firstPostOnly = !empty($mybb->settings['ougc_customrep_firstpost']) || !empty($rateData['firstpost']);

        if (
            !empty($rateData['requireattach']) &&
            is_member($rateData['groups']) &&
            is_member($rateData['forums'], ['usergroup' => $thread['fid'], 'additionalgroups' => '']) &&
            ($isFirstPost || !$firstPostOnly)
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