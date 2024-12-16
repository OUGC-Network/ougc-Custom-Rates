<?php

/***************************************************************************
 *
 *    OUGC Custom Reputation plugin (/inc/plugins/ougc_customrep.php)
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

use function ougc\CustomReputation\Admin\pluginActivate;
use function ougc\CustomReputation\Admin\pluginDeactivate;
use function ougc\CustomReputation\Admin\pluginInfo;
use function ougc\CustomReputation\Admin\pluginInstall;
use function ougc\CustomReputation\Admin\pluginIsInstalled;
use function ougc\CustomReputation\Admin\pluginUninstall;
use function ougc\CustomReputation\Core\addHooks;
use function ougc\CustomReputation\Core\cacheUpdate;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\logDelete;
use function ougc\CustomReputation\Core\logGet;
use function ougc\CustomReputation\Core\logInsert;
use function ougc\CustomReputation\Core\logUpdate;
use function ougc\CustomReputation\Core\rateGet;
use function ougc\CustomReputation\Core\rateGetImage;
use function ougc\CustomReputation\Core\rateGetName;
use function ougc\CustomReputation\Core\urlHandlerBuild;
use function ougc\CustomReputation\Core\urlHandlerSet;

use const ougc\CustomReputation\ROOT;

defined('IN_MYBB') or die('This file cannot be accessed directly.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('ougc\CustomReputation\Core\SETTINGS', [
    //'key' => 'value',
    'myAlertsVersion' => '2.1.0'
]);

define('ougc\CustomReputation\Core\DEBUG', false);

define('ougc\CustomReputation\ROOT', constant('MYBB_ROOT') . 'inc/plugins/ougc/CustomReputation');

require_once ROOT . '/core.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('ougc\CustomReputation\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    addHooks('ougc\CustomReputation\Hooks\Forum');
}

require_once ROOT . '/hooks/shared.php';

addHooks('ougc\CustomReputation\Hooks\Shared');

global $plugins;

// Add our hooks
if (!defined('IN_ADMINCP') && defined('THIS_SCRIPT')) {
    switch (THIS_SCRIPT) {
        case 'forumdisplay.php':
        case 'portal.php':
        case 'reputation.php':
        case 'showthread.php':
        case 'editpost.php':
        case 'member.php':
        case 'attachment.php':
            $plugins->add_hook('forumdisplay_thread', 'ougc_customrep_forumdisplay_thread');

            $plugins->add_hook('portal_announcement', 'ougc_customrep_portal_announcement');

            $plugins->add_hook('reputation_start', 'ougc_customrep_delete_reputation');

            $plugins->add_hook('showthread_start', 'ougc_customrep_request', -1);
            $plugins->add_hook('postbit', 'ougc_customrep_postbit');
            break;
    }
}

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

function ougc_customrep_info(): array
{
    return pluginInfo();
}

function ougc_customrep_activate(): bool
{
    return pluginActivate();
}

function ougc_customrep_deactivate(): bool
{
    return pluginDeactivate();
}

function ougc_customrep_install(): bool
{
    return pluginInstall();
}

function ougc_customrep_is_installed(): bool
{
    return pluginIsInstalled();
}

function ougc_customrep_uninstall(): bool
{
    return pluginUninstall();
}

function reload_ougc_customrep(): bool
{
    return cacheUpdate();
}

// Display ratings on forum display
function ougc_customrep_forumdisplay_thread()
{
    global $fid, $customrep, $mybb, $db, $plugins, $footer, $templates, $threadcache;

    $plugins->remove_hook('forumdisplay_thread', 'ougc_customrep_forumdisplay_thread');

    if (!$mybb->settings['ougc_customrep_threadlist'] || !is_member(
            $mybb->settings['ougc_customrep_threadlist'],
            ['usergroup' => $fid, 'additionalgroups' => '']
        )) {
        return;
    }

    $customrep->set_forum($fid);

    if (!$customrep->allowed_forum) {
        return;
    }

    $fontAwesomeCode = '';

    if ($mybb->settings['ougc_customrep_fontawesome']) {
        eval('$fontAwesomeCode .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
    }

    $font_awesome = &$fontAwesomeCode;

    eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');

    $pids = [];
    foreach ($threadcache as $thread) {
        $pids[(int)$thread['firstpost']] = (int)$thread['firstpost'];
    }

    if (empty($pids)) {
        return;
    }

    $pids = "pid IN ('" . implode("','", $pids) . "')";

    $query = $db->simple_select(
        'ougc_customrep_log',
        '*',
        $pids . ' AND rid IN (\'' . implode('\',\'', $customrep->rids) . '\')'
    );
    while ($rep = $db->fetch_array($query)) {
        $customrep->cache['query'][$rep['rid']][$rep['pid']][$rep['lid']][$rep['uid']] = 1;
    }

    global $plugins;

    $plugins->add_hook('forumdisplay_thread_end', 'ougc_customrep_forumdisplay_thread_end');
}

// Parse forum display
function ougc_customrep_forumdisplay_thread_end(&$args)
{
    global $thread, $customrep;

    if (substr($thread['closed'], 0, 6) == 'moved|') {
        return;
    }

    $customrep->set_post(
        ['tid' => $thread['tid'], 'pid' => $thread['firstpost'], 'uid' => $thread['uid'], 'fid' => $thread['fid']]
    );

    urlHandlerSet(get_thread_link($thread['tid']));

    ougc_customrep_parse_postbit($thread);
}

// Display ratings on portal
function ougc_customrep_portal_announcement()
{
    global $fid, $customrep, $mybb, $db, $plugins, $footer, $templates, $tids, $annfidswhere, $tunviewwhere, $numannouncements, $announcement;

    if (!$mybb->settings['ougc_customrep_portal']) {
        $plugins->remove_hook('portal_announcement', 'ougc_customrep_portal_announcement');
        return;
    }

    if ($mybb->settings['ougc_customrep_portal'] != -1 && !is_member(
            $mybb->settings['ougc_customrep_portal'],
            ['usergroup' => $announcement['fid'], 'additionalgroups' => '']
        )) {
        return;
    }

    static $portal_cache = null;
    if ($portal_cache === null) {
        $portal_cache = [];

        $query = $db->simple_select(
            'threads t',
            't.firstpost, t.fid',
            "t.tid IN (0{$tids}){$annfidswhere}{$tunviewwhere} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'"
        );

        $pids = [];
        while ($thread = $db->fetch_array($query)) {
            $fids[(int)$thread['fid']] = (int)$thread['fid'];
            $pids[(int)$thread['firstpost']] = (int)$thread['firstpost'];
        }

        if (empty($pids)) {
            return;
        }

        foreach ($fids as $fid) {
            $customrep->set_forum($fid);

            $portal_cache[$fid] = $customrep->allowed_forum;
        }

        if (empty($portal_cache)) {
            $plugins->remove_hook('portal_announcement', 'ougc_customrep_portal_announcement');
            return;
        }

        $pids = "pid IN ('" . implode("','", $pids) . "')";

        $query = $db->simple_select(
            'ougc_customrep_log',
            '*',
            $pids . ' AND rid IN (\'' . implode('\',\'', $customrep->rids) . '\')'
        );
        while ($rep = $db->fetch_array($query)) {
            $customrep->cache['query'][$rep['rid']][$rep['pid']][$rep['lid']][$rep['uid']] = 1;
        }

        $fontAwesomeCode = '';

        if ($mybb->settings['ougc_customrep_fontawesome']) {
            eval('$fontAwesomeCode .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
        }

        $font_awesome = &$fontAwesomeCode;

        eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');
    }

    if (empty($portal_cache[$announcement['fid']])) {
        return;
    }

    $customrep->set_post(
        [
            'tid' => $announcement['tid'],
            'pid' => $announcement['firstpost'],
            'uid' => $announcement['uid'],
            'fid' => $announcement['fid']
        ]
    );

    urlHandlerSet(get_thread_link($announcement['tid']));

    // Now we build the reputation bit
    ougc_customrep_parse_postbit($announcement);
}

// Postbit
function ougc_customrep_postbit(&$post)
{
    global $mybb;
    global $fid, $customrep, $tid, $templates, $thread;

    if (!empty($mybb->settings['ougc_customrep_firstpost']) && $post['pid'] != $thread['firstpost']) {
        return;
    }

    if (!empty($mybb->settings['ougc_customrep_firstpost'])) {
        global $plugins;

        $plugins->remove_hook('postbit', 'ougc_customrep_postbit');
    }

    static $ignore_rules = null;

    if (!isset($customrep->cache['query'])) {
        global $mybb;

        $customrep->set_forum($fid);

        if (!$customrep->allowed_forum) {
            global $plugins;

            $plugins->remove_hook('postbit', 'ougc_customrep_postbit');

            return;
        }

        global $footer;

        $fontAwesomeCode = '';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            eval('$fontAwesomeCode .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
        }

        $font_awesome = &$fontAwesomeCode;

        $customThreadFieldsVariables = '';

        if ($mybb->settings['ougc_customrep_xthreads_hide']) {
            $xt_fields = explode(',', str_replace('_require', '', $mybb->settings['ougc_customrep_xthreads_hide']));
            foreach ($xt_fields as $customThreadFieldKey) {
                $xt_field = &$customThreadFieldKey;

                $customThreadFieldsVariables .= eval(
                $templates->render(
                    'ougccustomrep_headerinclude_xthreads',
                    true,
                    false
                )
                );
            }
        }

        $xthreads_variables = &$customThreadFieldsVariables;

        eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');

        global $db, $thread;
        $customrep->cache['query'] = [];

        if (!empty($mybb->settings['ougc_customrep_firstpost'])) {
            $pids = "pid='{$thread['firstpost']}'";
        } // Bug: http://mybbhacks.zingaburga.com/showthread.php?tid=1587&pid=12762#pid12762
        elseif ($mybb->get_input('mode') == 'threaded') {
            $pids = "pid='{$mybb->get_input('pid', 1)}'";
        } elseif (isset($GLOBALS['pids'])) {
            $pids = $GLOBALS['pids'];
        } else {
            $pids = "pid='{$post['pid']}'";
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            '*',
            $pids . ' AND rid IN (\'' . implode('\',\'', $customrep->rids) . '\')'
        );
        while ($rep = $db->fetch_array($query)) {
            // > The ougc_customrep_log table seems to mostly query on the rid,pid columns - there really should be indexes on these; one would presume that pid,uid should be a unique key as you can't vote for more than once for each post.  The good thing about identifying these uniques is that it could help one simplify something like
            $customrep->cache['query'][$rep['rid']][$rep['pid']][$rep['lid']][$rep['uid']] = 1; //TODO
            // > where the 'lid' key seems to be unnecessary
        }

        $ignore_rules = [];
        foreach ($customrep->cache['_reps'] as $rid => $rep) {
            if ((int)$rep['ignorepoints'] && isset($customrep->cache['query'][$rid])) {
                $ignore_rules[$rid] = (int)$rep['ignorepoints'];
            }
        }
    }

    $customrep->set_post(
        [
            'tid' => $post['tid'],
            'pid' => $post['pid'],
            'uid' => $post['uid'],
            'fid' => $fid,
            'firstpost' => $thread['firstpost']
        ]
    );

    if (!empty($ignore_rules)) {
        foreach ($ignore_rules as $rid => $ignorepoints) {
            if (isset($customrep->cache['query'][$rid][$post['pid']]) && count(
                    $customrep->cache['query'][$rid][$post['pid']]
                ) >= $ignorepoints) {
                global $lang, $ignored_message, $ignore_bit, $post_visibility;

                $ignored_message = $lang->sprintf($lang->ougc_customrep_postbit_ignoredbit, $post['username']);
                eval("\$post['customrep_ignorebit'] = \"" . $templates->get('postbit_ignored') . "\";");
                $post['customrep_post_visibility'] = 'display: none;';
                break;
            }
        }
    }

    // Now we build the reputation bit
    ougc_customrep_parse_postbit($post);
}

// When deleting a reputation delete any log assigned to it.
// Partially MyBB's Code
function ougc_customrep_delete_reputation()
{
    global $mybb;

    if ($mybb->get_input('action') == 'delete') {
        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        global $db;

        // Fetch the existing reputation for this user given by our current user if there is one.
        $query = $db->simple_select(
            'reputation r LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid=r.adduid)',
            'r.adduid, r.lid, u.uid, u.username',
            'r.rid=\'' . $mybb->get_input('rid', 1) . '\''
        );
        $reputation = $db->fetch_array($query);

        // Only administrators, super moderators, as well as users who gave a specifc vote can delete one.
        if (!$mybb->usergroup['cancp'] && !$mybb->usergroup['issupermod'] && $reputation['adduid'] != $mybb->user['uid']) {
            error_no_permission();
        }

        global $customrep;

        // Delete the specified reputation log
        if ((int)$reputation['lid'] > 0) {
            // Delete the specified reputation & log
            logDelete((int)$reputation['lid']);

            global $uid, $user, $lang;

            // Create moderator log
            log_moderator_action(['uid' => $user['uid'], 'username' => $user['username']],
                $lang->sprintf($lang->delete_reputation_log, $reputation['username'], $reputation['adduid']));

            redirect('reputation.php?uid=' . $uid, $lang->vote_deleted_message);
        }
    }
}

// Parse posbit content output
function ougc_customrep_parse_postbit(&$var, $specific_rid = null)
{
    global $mybb, $customrep;

    $ratesListCode = '';

    // Has this current user voted for this custom reputation?
    $voted = false;

    $voted_rids = $firstpost_only = $unique_rids = [];

    foreach ($customrep->cache['_reps'] as $rid => $rate) {
        if (!empty($rate['firstpost'])) {
            $firstpost_only[$rid] = $rid;
        }

        if (!$rate['inmultiple']) {
            $unique_rids[$rid] = $rid;
        }

        if (isset($customrep->cache['query'][$rid][$customrep->post['pid']])) {
            //TODO
            foreach ($customrep->cache['query'][$rid][$customrep->post['pid']] as $votes) {
                if (isset($votes[$mybb->user['uid']])) {
                    $voted_rids[$rid] = $rid;

                    $voted = true;
                }
            }
        }
    }
    unset($rid, $rate);

    global $templates, $lang;

    loadLanguage();

    $post_url = get_post_link($customrep->post['pid'], $customrep->post['tid']);

    $input = [
        'pid' => $customrep->post['pid'],
        'my_post_key' => (isset($mybb->post_code) ? $mybb->post_code : generate_post_check()),
    ];

    $ajax_enabled = $mybb->settings['use_xmlhttprequest'] && $mybb->settings['ougc_customrep_enableajax'];

    if (!$ajax_enabled) {
        $lang->ougc_customrep_viewlatest = $lang->ougc_customrep_viewlatest_noajax;
    }

    foreach ($customrep->cache['_reps'] as $rateID => $reputation) {
        if (!is_member($reputation['forums'], ['usergroup' => $customrep->post['fid'], 'additionalgroups' => '']
        )) {
            continue;
        }

        // $firstpost_only[$rateID] stores the tid
        // $customrep->post['firstpost'] is only set on post bit, since portal and thread list posts are indeed the first post
        if (!empty($firstpost_only[$rateID]) && !empty($customrep->post['firstpost']) && $customrep->post['firstpost'] != $customrep->post['pid']) {
            continue;
        }

        $rateName = htmlspecialchars_uni($reputation['name']);

        $input['action'] = 'customrep';
        $input['rid'] = $rateID;
        $link = urlHandlerBuild($input);
        $input['action'] = 'customreppu';
        $ratePopUpUrl = $popupurl = urlHandlerBuild($input);

        $totalReceivedRates = 0;
        $classextra = '';
        if ($ajax_enabled) {
            $link = "javascript:OUGC_CustomReputation.Add('{$customrep->post['tid']}', '{$customrep->post['pid']}', '{$mybb->post_code}', '{$rateID}', '0');";
            if ($customrep->allow_delete && $reputation['allowdeletion']) {
                $link_delete = "javascript:OUGC_CustomReputation.Add('{$customrep->post['tid']}', '{$customrep->post['pid']}', '{$mybb->post_code}', '{$rateID}', '1');";
            }
        }

        // Count the votes for this reputation in this post
        if (isset($customrep->cache['query'][$rateID][$customrep->post['pid']])) {
            $totalReceivedRates = count($customrep->cache['query'][$rateID][$customrep->post['pid']]);
        }

        $totalReceivedRates = my_number_format($totalReceivedRates);

        $voted_this = false;

        if (isset($voted_rids[$rateID])) {
            $voted_this = true;
        }

        $userRatedThisClass = '';

        if ($voted_this) {
            $userRatedThisClass = 'voted';
        }

        $voted_class = &$userRatedThisClass;

        $postID = (int)$customrep->post['pid'];

        eval('$totalReceivedRates = "' . $templates->get('ougccustomrep_rep_number', 1, 0) . '";');

        $number = &$totalReceivedRates;

        $imageTemplateName = 'ougccustomrep_rep_img';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            $imageTemplateName = 'ougccustomrep_rep_img_fa';
        }

        $rateTitleText = '';

        $can_vote = is_member($reputation['groups']) && $customrep->post['uid'] != $mybb->user['uid'];

        if ($voted && $voted_this) {
            $can_vote = false;

            if ($customrep->allow_delete && $reputation['allowdeletion']) {
                $link = $ajax_enabled ? $link_delete : $link . '&amp;delete=1';

                $classextra = '_delete';
                $rateTitleText = $lang->sprintf($lang->ougc_customrep_delete, $rateName);
                eval('$rateImage = "' . $templates->get($imageTemplateName, 1, 0) . '";');
                eval('$rateImage = "' . $templates->get('ougccustomrep_rep_voted', 1, 0) . '";');
            } else {
                $rateTitleText = $lang->sprintf($lang->ougc_customrep_voted, $rateName);
                eval('$rateImage = "' . $templates->get($imageTemplateName, 1, 0) . '";');
            }
        } elseif ($can_vote && ($reputation['inmultiple'] || !(isset($unique_rids[$rateID]) && array_intersect(
                        $unique_rids,
                        $voted_rids
                    )))) {
            $rateTitleText = $lang->sprintf($lang->ougc_customrep_vote, $rateName);
            eval('$rateImage = "' . $templates->get($imageTemplateName, 1, 0) . '";');
            eval('$rateImage = "' . $templates->get('ougccustomrep_rep_voted', 1, 0) . '";');
        } else {
            $rateTitleText = $rateName;
            eval('$rateImage = "' . $templates->get($imageTemplateName, 1, 0) . '";');
        }
        /*
        {
            $rateTitleText = $lang->ougc_customrep_voted_undo;
            eval('$rateImage = "'.$templates->get($imageTemplateName, 1, 0).'";');
        }
        */

        $lang_val = &$rateTitleText;

        $rid = &$rateID;

        $image = &$rateImage;

        $rep = eval($templates->render('ougccustomrep_rep', 1, 0));

        if (!empty($reputation['customvariable']) || $specific_rid !== null && (int)$specific_rid === (int)$rateID) {
            $var['customrep_' . $rateID] = $rep;
            /*if($specific_rid !== null)
            {
                break;
            }*/
        }

        if (empty($reputation['customvariable'])) {
            $ratesListCode .= $rep;
        }
    }
    unset($rateID, $reputation);

    /*if($specific_rid !== null)
    {
        return false;
    }*/

    $ratesListCode = trim($ratesListCode);

    $reputations = &$ratesListCode;

    // if $ratesListCode is empty maybe return false?
    eval('$var[\'customrep\'] = "' . $templates->get('ougccustomrep') . '";');
}

// Plugin request
function ougc_customrep_request()
{
    global $customrep, $mybb, $tid, $templates, $thread, $fid, $lang;

    loadLanguage();

    $ajax_enabled = $mybb->settings['use_xmlhttprequest'] && $mybb->settings['ougc_customrep_enableajax'];

    urlHandlerSet(get_thread_link($tid)); //TODO

    // Save four queries here
    $templates->cache(
        'ougccustomrep_misc_row, ougccustomrep_misc_error, ougccustomrep_misc_multipage, ougccustomrep_misc, ougccustomrep_postbit_reputation, ougccustomrep_modal'
    );

    if (!$customrep->active || !in_array($mybb->get_input('action'), ['customrep', 'customreppu'])) {
        return;
    }

    $error = $mybb->get_input(
        'action'
    ) == 'customreppu' ? 'ougc_customrep_modal_error' : ($ajax_enabled ? 'ougc_customrep_ajax_error' : 'error');

    $rid = $mybb->get_input('rid', MyBB::INPUT_INT);

    if (!($reputation = rateGet((int)$rid))) {
        $error($lang->ougc_customrep_error_invalidrep);
    }

    $customrep->set_forum($fid);

    if (!$reputation['visible'] || !array_key_exists($rid, $customrep->cache['_reps'])) {
        $error($lang->ougc_customrep_error_invalidrep);
    }

    if ($mybb->get_input('action') == 'customreppu') {
        // Good bay guests :)
        if (!$mybb->user['uid'] && !$mybb->settings['ougc_customrep_guests_popup']) {
            ougc_customrep_modal_error($lang->ougc_customrep_error_nopermission_guests);
        }

        $customrep->allowed_forum or ougc_customrep_modal_error($lang->ougc_customrep_error_invalidforum);

        $post = get_post($mybb->get_input('pid', 1));
        $customrep->set_post(
            [
                'tid' => $post['tid'],
                'pid' => $post['pid'],
                'uid' => $post['uid'],
                'subject' => $post['subject'],
                'fid' => $fid
            ]
        );
        unset($post);

        !empty($customrep->post) or ougc_customrep_modal_error($lang->ougc_customrep_error_invlidadpost);

        if ((!empty($mybb->settings['ougc_customrep_firstpost']) || $reputation['firstpost']) && $customrep->post['pid'] != $thread['firstpost']) {
            ougc_customrep_modal_error($lang->ougc_customrep_error_invlidadpost); // somehow
        }

        global $db, $templates, $theme, $headerinclude, $parser;
        if (!is_object($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';
            $parser = new postParser();
        }

        $popupurl = urlHandlerBuild([
            'pid' => $customrep->post['pid'],
            'my_post_key' => (isset($mybb->post_code) ? $mybb->post_code : generate_post_check()),
            'action' => 'customreppu',
            'rid' => $reputation['rid']
        ]);

        // Build multipage
        $query = $db->simple_select(
            'ougc_customrep_log',
            'COUNT(lid) AS logs',
            "pid='{$customrep->post['pid']}' AND rid='{$reputation['rid']}'"
        );
        $count = (int)$db->fetch_field($query, 'logs');

        $page = $mybb->get_input('page', 1);

        $perpage = (int)$mybb->settings['ougc_customrep_perpage'];
        if ($page > 0) {
            $start = ($page - 1) * $perpage;
            $pages = ceil($count / $perpage);
            if ($page > $pages) {
                $start = 0;
                $page = 1;
            }
        } else {
            $start = 0;
            $page = 1;
        }

        urlHandlerSet(get_post_link($customrep->post['pid'], $customrep->post['tid']));

        $multipage_url = urlHandlerBuild();

        $multipage = multipage($count, $perpage, $page, "javascript:MyBB.popupWindow('/{$popupurl}&amp;page={page}');");

        if (!$multipage) {
            $multipage = '';
        }

        $query = $db->query(
            'SELECT r.*, u.username, u.usergroup, u.displaygroup, u.avatar, u.avatartype, u.avatardimensions
			FROM ' . TABLE_PREFIX . 'ougc_customrep_log r 
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid=r.uid)
			WHERE r.pid=\'' . $customrep->post['pid'] . '\' AND r.rid=\'' . $reputation['rid'] . '\'
			ORDER BY r.dateline DESC
			LIMIT ' . $start . ', ' . $perpage
        );

        $content = '';

        $trow = alt_trow(true);

        while ($log = $db->fetch_array($query)) {
            $log['username'] = htmlspecialchars_uni($log['username']);
            $log['username_f'] = format_name($log['username'], $log['usergroup'], $log['displaygroup']);
            $log['profilelink'] = build_profile_link($log['username'], $log['uid'], '_blank');
            $log['profilelink_f'] = build_profile_link($log['username_f'], $log['uid'], '_blank');

            $log['date'] = my_date($mybb->settings['dateformat'], $log['dateline']);
            $log['time'] = my_date($mybb->settings['timeformat'], $log['dateline']);
            $date = $lang->sprintf($lang->ougc_customrep_popup_date, $log['date'], $log['time']);

            $log['avatar'] = format_avatar($log['avatar'], $log['avatardimensions']);

            eval('$content .= "' . $templates->get('ougccustomrep_misc_row') . '";');

            $trow = alt_trow();
        }

        $reputation['name'] = htmlspecialchars_uni($reputation['name']);
        $customrep->post['subject'] = $parser->parse_badwords($customrep->post['subject']);

        if (!$content) {
            $error_message = $lang->ougc_customrep_popup_empty;
            $content = eval($templates->render('ougccustomrep_misc_error'));
        }

        $title = $lang->sprintf($lang->ougc_customrep_popuptitle, $reputation['name'], $customrep->post['subject']);

        $desc = $lang->sprintf($lang->ougc_customrep_popup_latest, my_number_format($count));

        if ($multipage) {
            eval('$multipage = "' . $templates->get('ougccustomrep_misc_multipage') . '";');
        }

        ougc_customrep_modal($content, $title, $desc, $multipage);
    }

    // Good bay guests :)
    if (!$mybb->user['uid']) {
        $error($lang->ougc_customrep_error_nopermission);
    }

    verify_post_check($mybb->get_input('my_post_key'));

    $post = get_post($mybb->get_input('pid', 1));
    $post['firstpost'] = $thread['firstpost'];

    $customrep->set_post($post);

    if (empty($customrep->post)) {
        $error($lang->ougc_customrep_error_invlidadpost);
    }

    if ($mybb->user['uid'] == $customrep->post['uid']) {
        $error($lang->ougc_customrep_error_selftrating);
    }

    if (!$customrep->allowed_forum) {
        $error($lang->ougc_customrep_error_invalidforum);
    }

    if (!$customrep->allowed_forum) {
        $error($lang->ougc_customrep_error_invalidforum);
    }

    if ($reputation['groups'] == '' || ($reputation['groups'] != -1 && !is_member($reputation['groups']))) {
        $error($lang->ougc_customrep_error_nopermission);
    }

    if ($customrep->post['tid'] != $thread['tid'] || (!empty($mybb->settings['ougc_customrep_firstpost']) || $reputation['firstpost']) && $customrep->post['pid'] != $thread['firstpost']) {
        $error($lang->ougc_customrep_error_invlidadpost); // somehow
    }

    global $db;

    if ($mybb->get_input('delete', 1) == 1) {
        if (!$customrep->allow_delete) {
            $error($lang->ougc_customrep_error_nopermission);
        }

        if (!$reputation['allowdeletion']) {
            $error($lang->ougc_customrep_error_nopermission_rate);
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            '*',
            'pid=\'' . $customrep->post['pid'] . '\' AND uid=\'' . $mybb->user['uid'] . '\' AND rid=\'' . $reputation['rid'] . '\''
        );

        if ($db->num_rows($query) < 1) {
            $error($lang->ougc_customrep_error_invalidrating);
        }

        while ($log = $db->fetch_array($query)) {
            $log['points'] = (float)$log['points'];

            if ($customrep->newpoints_installed && $log['points']) {
                $post_author = get_user($customrep->post['uid']);
                if ($log['points'] > $post_author['newpoints']) {
                    $error(
                        $lang->sprintf(
                            $lang->ougc_customrep_error_points_author,
                            htmlspecialchars_uni($post_author['username']),
                            newpoints_format_points($log['points'])
                        )
                    );
                }

                newpoints_addpoints($customrep->post['uid'], -$log['points']); // remove from post owner
                newpoints_addpoints($log['uid'], $log['points']); // Give back to rate author
            }

            logDelete((int)$log['lid']);
        }
    } else {
        $uid = $mybb->user['uid'];

        if ($mybb->settings['ougc_customrep_multiple']) {
            if ($reputation['inmultiple']) {
                $query = $db->simple_select(
                    'ougc_customrep_log',
                    'rid',
                    "pid='{$customrep->post['pid']}' AND uid='{$uid}' AND rid='{$rid}'",
                    ['limit' => 1]
                );

                $already_rated = (bool)$db->fetch_field($query, 'rid');

                if ($already_rated) {
                    $error($lang->ougc_customrep_error_multiple);
                }
            } else {
                $unique_rids = [];

                foreach ($customrep->cache['_reps'] as $key => $rate) {
                    if (!$rate['inmultiple']) {
                        $unique_rids[(int)$key] = (int)$key;
                    }
                }

                $unique_rids = implode("','", $unique_rids);

                $query = $db->simple_select(
                    'ougc_customrep_log',
                    'lid',
                    "pid='{$customrep->post['pid']}' AND uid='{$uid}' AND rid IN ('{$unique_rids}')",
                    ['limit' => 1]
                );

                $already_rated = (bool)$db->fetch_field($query, 'lid');

                if ($already_rated) {
                    $error($lang->ougc_customrep_error_multiple_single);
                }
            }

            $query = $db->simple_select(
                'ougc_customrep_log',
                'rid',
                "pid='{$customrep->post['pid']}' AND uid='{$uid}' AND rid='{$rid}'",
                ['limit' => 1]
            );

            $already_rated = (bool)$db->fetch_field($query, 'rid');

            if ($already_rated) {
                $error($lang->ougc_customrep_error_multiple);
            }
        } else {
            $query = $db->simple_select(
                'ougc_customrep_log',
                'lid',
                "pid='{$customrep->post['pid']}' AND uid='{$uid}'",
                ['limit' => 1]
            );

            $already_rated = (bool)$db->fetch_field($query, 'lid');

            if ($already_rated) {
                $error($lang->ougc_customrep_error_multiple);
            }
        }

        $reputation['points'] = (float)$reputation['points'];

        $points = 0;

        if ($customrep->newpoints_installed && $reputation['points']) {
            if (!($forumrules = newpoints_getrules('forum', $thread['fid']))) {
                $forumrules['rate'] = 1;
            }

            if (!($grouprules = newpoints_getrules('group', $mybb->user['usergroup']))) {
                $grouprules['rate'] = 1;
            }

            if ($forumrules['rate'] && $grouprules['rate']) {
                $points = floatval(
                    round(
                        $reputation['points'] * $forumrules['rate'] * $grouprules['rate'],
                        intval($mybb->settings['newpoints_main_decimal'])
                    )
                );

                if ($points > $mybb->user['newpoints']) {
                    $error($lang->sprintf($lang->ougc_customrep_error_points, newpoints_format_points($points)));
                } else {
                    newpoints_addpoints($customrep->post['uid'], $points); // Give points to post author
                    newpoints_addpoints($mybb->user['uid'], -$points); // Remove from
                }
            }
        }

        $insertData = [
            'pid' => $customrep->post['pid'],
            'rid' => $reputation['rid'],
            'points' => $points
        ];

        logInsert($insertData, (int)$reputation['reptype']);
    }

    $ajax_enabled || $customrep->redirect(
        get_post_link($customrep->post['pid'], $customrep->post['tid']) . '#' . $customrep->post['tid'],
        true
    );

    // > On postbit, the plugin loads ALL votes, and does a summation + check for current user voting on this.  This can potentially be problematic if there happens to be a large number of votes.
    $query = $db->simple_select(
        'ougc_customrep_log',
        '*',
        "pid='{$customrep->post['pid']}'"
    ); //  AND rid='{$reputation['rid']}'
    while ($rep = $db->fetch_array($query)) {
        $customrep->cache['query'][$rep['rid']][$rep['pid']][$rep['lid']][$rep['uid']] = 1;
    }

    $query = $db->simple_select('users', 'reputation', "uid='{$customrep->post['uid']}'");

    $user_reputation = (int)$db->fetch_field($query, 'reputation');

    $post = [
        'pid' => $customrep->post['pid'],
        'userreputation' => get_reputation($user_reputation, $customrep->post['uid']),
        'content' => ''
    ];

    ougc_customrep_parse_postbit($post, $reputation['rid']);

    eval('$post[\'userreputation\'] = "' . $templates->get('ougccustomrep_postbit_reputation') . '";');

    /*_dump(isset($post['customrep']), array(
        'success'			=> 1,
        'pid'				=> $customrep->post['pid'],
        'rid'				=> $reputation['rid'],
        'content'			=> $post['customrep'],
        'content_rep'		=> $post['customrep_'.$reputation['rid']],
        'userreputation'	=> $post['userreputation'],
    ));*/

    ougc_customrep_ajax([
        'success' => 1,
        'pid' => $customrep->post['pid'],
        'rid' => $reputation['rid'],
        'content' => $post['customrep'],
        'content_rep' => $post['customrep_' . $reputation['rid']],
        'userreputation' => $post['userreputation'],
    ]);
}

function ougc_customrep_ajax_error($error)
{
    ougc_customrep_ajax([
        'errors' => $error
    ]);
}

function ougc_customrep_ajax($data)
{
    global $lang;

    header("Content-type: application/json; charset={$lang->settings['charset']}");

    echo json_encode($data);

    exit;
}

function ougc_customrep_modal_error($error_message = '', $title = '')
{
    global $customrep, $lang, $templates, $theme;
    loadLanguage();

    if (!$title) {
        $title = $lang->ougc_customrep_error;
    }

    $content = eval($templates->render('ougccustomrep_misc_error'));

    ougc_customrep_modal($content, $title);
}

function ougc_customrep_modal($content = '', $title = '', $desc = '', $multipage = '')
{
    global $customrep, $lang, $templates, $theme;
    loadLanguage();

    $page = eval($templates->render('ougccustomrep_misc', 1, 0));
    $modal = eval($templates->render('ougccustomrep_modal', 1, 0));

    echo $modal;

    exit;
}

// Helper function for xThreads feature
function ougc_customrep_xthreads_hide($value = '', $field = true)
{
    global $customrep, $threadfields, $cache, $thread, $mybb, $db, $lang, $templates, $fid;

    $fid = (int)$fid;

    loadLanguage();

    static $reps = null;

    if ($reps === null) {
        $reps = (array)$cache->read('ougc_customrep');

        if ($fid) {
            foreach ($reps as $rid => $rep) {
                if (!is_member($rep['forums'], ['usergroup' => $fid, 'additionalgroups' => ''])) {
                    unset($reps[$rid]);
                }
            }
        }
    }

    $require_any = $field === true;

    $rid = $threadfields[$field];

    // There are no ratings
    if (empty($reps) || (!$require_any && empty($reps[$rid]))) {
        return $value;
    }

    $thread['firstpost'] = (int)$thread['firstpost'];

    $mybb->user['uid'] = (int)$mybb->user['uid'];

    // thread author is the same as current user, do nothing
    if ((int)$thread['uid'] === $mybb->user['uid']) {
        return $value;
    }

    // such rating doesn't exists (maybe because none was selected), do nothing
    if (!$require_any && empty($rid)) {
        return $value;
    }

    // If thread field data is empty we assume author didn't select a value
    if (!$require_any && isset($rid) && empty($rid)) {
        return $value;
    }

    if (!empty($thread['firstpost']) && ($require_any || !empty($reps[$rid]))) {
        $where = "pid='{$thread['firstpost']}' AND uid='{$mybb->user['uid']}'";

        if (!$require_any) {
            $where .= " AND rid='{$rid}'";
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            'lid',
            $where,
            ['limit', 1]
        );

        if ((int)$db->num_rows($query) < 1) {
            if ($require_any) {
                $value = $lang->ougc_customrep_xthreads_error_user_any;
            } else {
                $value = $lang->sprintf(
                    $lang->ougc_customrep_xthreads_error_user,
                    htmlspecialchars_uni($reps[$rid]['name'])
                );
            }
        }

        $value .= eval($templates->render('ougccustomrep_xthreads_js'));

        return $value;
    }

    // Something is wrong, not sure what
    return $lang->ougc_customrep_xthreads_error;
}

// Our awesome class
class OUGC_CustomRep
{
    // Define our ACP url
    public $url = 'index.php?module=config-plugins';

    // Set the cache
    public $cache = [
        'reps' => [],
        'logs' => [],
        'images' => [],
        '_reps' => [],
        'query' => null
    ];

    // RID which has just been updated/inserted/deleted
    public $rid = 0;

    // Is the current forum allowed?
    public $allowed_forum = false;

    // Set current handling post
    public $post = [];

    // Is the plugin active? Default is false
    public $active = false;

    // Is the plugin active? Default is false
    public $newpoints_installed = false;

    // Is the plugin active? Default is false
    public $myalerts_installed = false;

    // Construct the data (?)
    public function __construct()
    {
        global $mybb;

        // Fix: PHP warning on MyBB installation/upgrade
        if (is_object($mybb->cache)) {
            $plugins = $mybb->cache->read('plugins');

            // Is plugin active?
            $this->active = isset($plugins['active']['ougc_customrep']);
        }

        $this->rids = [];

        $this->allow_delete = (bool)$mybb->settings['ougc_customrep_delete'];

        $this->newpoints_installed = function_exists(
                'newpoints_addpoints'
            ) && !empty($mybb->settings['newpoints_main_enabled']);
    }

    // Check PL requirements
    public function meets_requirements()
    {
        global $PL;

        $info = ougc_customrep_info();

        if (!file_exists(PLUGINLIBRARY)) {
            global $lang;

            loadLanguage();

            $this->message = $lang->sprintf($lang->ougc_customrep_plreq, $info['pl']['url'], $info['pl']['version']);
            return false;
        }

        $PL or require_once PLUGINLIBRARY;

        if ($PL->version < $info['pl']['version']) {
            global $lang;

            loadLanguage();

            $this->message = $lang->sprintf(
                $lang->ougc_customrep_plold,
                $PL->version,
                $info['pl']['version'],
                $info['pl']['url']
            );
            return false;
        }

        return true;
    }

    // Redirect normal users
    public function redirect($url, $quick = false)
    {
        if ($quick) {
            global $settings;

            $settings['redirects'] = 0;
        }

        redirect($url);
        exit;
    }

    // Redirect admin help function
    public function admin_redirect($message = '', $error = false)
    {
        if ($message) {
            flash_message($message, ($error ? 'error' : 'success'));
        }

        admin_redirect(urlHandlerBuild());
        exit;
    }

    // Set reputation data
    public function set_rep_data($rid = null)
    {
        if (isset($rid) && ($reputation = rateGet((int)$rid))) {
            $this->rep_data = [
                'name' => $reputation['name'],
                'image' => $reputation['image'],
                'groups' => explode(',', $reputation['groups']),
                'forums' => explode(',', $reputation['forums']),
                'disporder' => $reputation['disporder'],
                'visible' => $reputation['visible'],
                'firstpost' => $reputation['firstpost'],
                'allowdeletion' => $reputation['allowdeletion'],
                'customvariable' => $reputation['customvariable'],
                'requireattach' => $reputation['requireattach'],
                'points' => $reputation['points'],
                'ignorepoints' => $reputation['ignorepoints'],
                'inmultiple' => $reputation['inmultiple'],
                'createCoreReputationType' => $reputation['createCoreReputationType'],
                'reptype' => $reputation['reptype'],
            ];
        } else {
            global $db;

            $query = $db->simple_select('ougc_customrep', 'MAX(disporder) as max_disporder');
            $disporder = (int)$db->fetch_field($query, 'max_disporder');

            $this->rep_data = [
                'name' => '',
                'image' => '',
                'groups' => [],
                'forums' => [],
                'disporder' => ++$disporder,
                'visible' => 1,
                'firstpost' => 1,
                'allowdeletion' => 1,
                'customvariable' => 0,
                'requireattach' => 0,
                'points' => 0,
                'ignorepoints' => 0,
                'inmultiple' => 0,
                'createCoreReputationType' => 0,
                'reptype' => '',
            ];
        }

        global $mybb;

        if ($mybb->request_method == 'post') {
            foreach ($mybb->input as $key => $value) {
                if (isset($this->rep_data[$key])) {
                    $this->rep_data[$key] = $value;

                    if ($key == 'groups' || $key == 'forums') {
                        $this->rep_data[$key] = implode(
                            ',',
                            array_unique(
                                array_map(
                                    'intval',
                                    !is_array($this->rep_data[$key]) ? explode(
                                        ',',
                                        $this->rep_data[$key]
                                    ) : $this->rep_data[$key]
                                )
                            )
                        );
                    }
                }
            }
        }
    }

    // Validate a reputation data to insert into the DB
    public function validate_rep_data()
    {
        global $lang;
        $valid = true;

        $this->validate_errors = [];
        $name = strlen(trim($this->rep_data['name']));
        if ($name < 1 || $name > 100) {
            $this->validate_errors[] = $lang->ougc_customrep_error_invalidname;
            $valid = false;
        }

        $image = strlen(trim($this->rep_data['image']));
        if ($image < 1 || $image > 255) {
            $this->validate_errors[] = $lang->ougc_customrep_error_invalidimage;
            $valid = false;
        }

        if (my_strlen((string)$this->rep_data['disporder']) > 5) {
            $this->validate_errors[] = $lang->ougc_customrep_error_invaliddisporder;
            $valid = false;
        }

        if ($this->rep_data['reptype'] !== '' && !is_numeric(
                $this->rep_data['reptype']
            )/* || $this->rep_data['reptype']{3}*/) {
            $this->validate_errors[] = $lang->ougc_customrep_error_invalidreptype;
            $valid = false;
        }

        return $valid;
    }

    // Ge espesific forum to affect
    public function set_forum($fid)
    {
        global $settings;

        global $PL;
        $PL or require_once PLUGINLIBRARY;

        $reps = (array)$PL->cache_read('ougc_customrep');

        foreach ($reps as $rid => &$rep) {
            if ($rep['forums'] == '' || ($rep['forums'] != -1 && !in_array(
                        $fid,
                        array_unique(
                            array_map(
                                'intval',
                                !is_array($rep['forums']) ? explode(',', $rep['forums']) : $rep['forums']
                            )
                        )
                    ))) {
                unset($reps[$rid]);
                continue;
            }

            if (($name = rateGetName((int)$rid))) {
                $rep['name'] = $name;
            }

            $rep['name'] = htmlspecialchars_uni($rep['name']);
            $rep['image'] = rateGetImage($rep['image'], (int)$rid);
            $rep['groups'] = implode(
                ',',
                array_unique(
                    array_map(
                        'intval',
                        !is_array($rep['groups']) ? explode(',', $rep['groups']) : $rep['groups']
                    )
                )
            );

            $this->cache['_reps'][$rid] = $rep;
        }

        if (!is_array($this->rids)) {
            $this->rids = [];
        }

        $this->rids = array_merge($this->rids, array_keys($reps));

        $this->allowed_forum = (bool)$reps;
    }

    // Set post data
    public function set_post(array $post = [])
    {
        $this->post = $post;
    }
}

$GLOBALS['customrep'] = new OUGC_CustomRep();

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if (!function_exists('control_object')) {
    function control_object(&$obj, $code)
    {
        static $cnt = 0;
        $newname = '_objcont_' . (++$cnt);
        $objserial = serialize($obj);
        $classname = get_class($obj);
        $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
        $checkstr_len = strlen($checkstr);
        if (substr($objserial, 0, $checkstr_len) == $checkstr) {
            $vars = [];
            // grab resources/object etc, stripping scope info from keys
            foreach ((array)$obj as $k => $v) {
                if ($p = strrpos($k, "\0")) {
                    $k = substr($k, $p + 1);
                }
                $vars[$k] = $v;
            }
            if (!empty($vars)) {
                $code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
            }
            eval('class ' . $newname . ' extends ' . $classname . ' {' . $code . '}');
            $obj = unserialize('O:' . strlen($newname) . ':"' . $newname . '":' . substr($objserial, $checkstr_len));
            if (!empty($vars)) {
                $obj->___setvars($vars);
            }
        }
        // else not a valid object or PHP serialize has changed
    }
}

if (!function_exists('ougc_print_selection_javascript')) {
    function ougc_print_selection_javascript()
    {
        static $already_printed = false;

        if ($already_printed) {
            return;
        }

        $already_printed = true;

        echo "<script type=\"text/javascript\">
		function checkAction(id)
		{
			var checked = '';

			$('.'+id+'_forums_groups_check').each(function(e, val)
			{
				if($(this).prop('checked') == true)
				{
					checked = $(this).val();
				}
			});

			$('.'+id+'_forums_groups').each(function(e)
			{
				$(this).hide();
			});

			if($('#'+id+'_forums_groups_'+checked))
			{
				$('#'+id+'_forums_groups_'+checked).show();
			}
		}
	</script>";
    }
}