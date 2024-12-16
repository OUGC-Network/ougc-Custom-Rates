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
use function ougc\CustomReputation\Core\forumGetRates;
use function ougc\CustomReputation\Core\isAllowedForum;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\rateGet;
use function ougc\CustomReputation\Core\rateGetImage;
use function ougc\CustomReputation\Core\rateGetName;
use function ougc\CustomReputation\Core\urlHandlerBuild;

use const ougc\CustomReputation\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

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

if (!defined('IN_ADMINCP') && defined('THIS_SCRIPT')) {
    switch (THIS_SCRIPT) {
        case 'forumdisplay.php':
        case 'portal.php':
        case 'reputation.php':
        case 'showthread.php':
        case 'editpost.php':
        case 'member.php':
        case 'attachment.php':
            $plugins->add_hook('postbit', 'ougc_customrep_postbit');
            break;
    }
}

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

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

// Postbit
function ougc_customrep_postbit(&$post)
{
    global $mybb;
    global $fid, $tid, $templates, $thread;

    if (!empty($mybb->settings['ougc_customrep_firstpost']) && $post['pid'] != $thread['firstpost']) {
        return;
    }

    if (!empty($mybb->settings['ougc_customrep_firstpost'])) {
        global $plugins;

        $plugins->remove_hook('postbit', 'ougc_customrep_postbit');
    }

    static $ignore_rules = null;

    global $customReputationCacheQuery;

    $forumID = (int)$fid;

    if (!isset($customReputationCacheQuery)) {
        $customReputationCacheQuery = [];

        global $mybb;

        if (!isAllowedForum($forumID)) {
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

        $forumsRateCache = forumGetRates($forumID);

        $query = $db->simple_select(
            'ougc_customrep_log',
            '*',
            $pids . ' AND rid IN (\'' . implode('\',\'', array_keys($forumsRateCache)) . '\')'
        );

        while ($rep = $db->fetch_array($query)) {
            // > The ougc_customrep_log table seems to mostly query on the rid,pid columns - there really should be indexes on these; one would presume that pid,uid should be a unique key as you can't vote for more than once for each post.  The good thing about identifying these uniques is that it could help one simplify something like
            $customReputationCacheQuery[$rep['rid']][$rep['pid']][$rep['lid']][$rep['uid']] = 1; //TODO
            // > where the 'lid' key seems to be unnecessary
        }

        $ignore_rules = [];

        foreach ($forumsRateCache as $rid => $rep) {
            if ((int)$rep['ignorepoints'] && isset($customReputationCacheQuery[$rid])) {
                $ignore_rules[$rid] = (int)$rep['ignorepoints'];
            }
        }
    }

    if (!empty($ignore_rules)) {
        foreach ($ignore_rules as $rid => $ignorepoints) {
            if (isset($customReputationCacheQuery[$rid][$post['pid']]) && count(
                    $customReputationCacheQuery[$rid][$post['pid']]
                ) >= $ignorepoints) {
                global $lang, $ignored_message, $ignore_bit, $post_visibility;

                $ignored_message = $lang->sprintf($lang->ougc_customrep_postbit_ignoredbit, $post['username']);
                eval("\$post['customrep_ignorebit'] = \"" . $templates->get('postbit_ignored') . "\";");
                $post['customrep_post_visibility'] = 'display: none;';
                break;
            }
        }
    }

    ougc_customrep_parse_postbit($post, (int)$post['pid']);
}

// Parse posbit content output
function ougc_customrep_parse_postbit(array &$postThreadObject, int $postID, int $setRateID = 0): bool
{
    $postData = get_post($postID);

    if (empty($postData['pid'])) {
        return false;
    }

    $threadData = get_thread($postData['tid']);

    if (empty($threadData['tid'])) {
        return false;
    }

    $forumID = (int)$threadData['fid'];

    $forumRatesCache = forumGetRates($forumID);

    if (empty($forumRatesCache)) {
        return false;
    }

    global $mybb;

    $ratesListCode = '';

    // Has this current user voted for this custom reputation?
    $voted = false;

    $voted_rids = $firstpost_only = $unique_rids = [];

    global $customReputationCacheQuery;

    is_array($customReputationCacheQuery) || $customReputationCacheQuery = [];

    foreach ($forumRatesCache as $rateID => $rateData) {
        if (!empty($rateData['firstpost'])) {
            $firstpost_only[$rateID] = $rateID;
        }

        if (empty($rateData['inmultiple'])) {
            $unique_rids[$rateID] = $rateID;
        }

        if (isset($customReputationCacheQuery[$rateID][$postID])) {
            //TODO
            foreach ($customReputationCacheQuery[$rateID][$postID] as $votes) {
                if (isset($votes[$mybb->user['uid']])) {
                    $voted_rids[$rateID] = $rateID;

                    $voted = true;
                }
            }
        }
    }

    unset($rateID, $rateData);

    global $templates, $lang;

    loadLanguage();

    $post_url = get_post_link($postID, $postData['tid']);

    $input = [
        'pid' => $postID,
        'my_post_key' => (isset($mybb->post_code) ? $mybb->post_code : generate_post_check()),
    ];

    $ajaxIsEnabled = $mybb->settings['use_xmlhttprequest'] && $mybb->settings['ougc_customrep_enableajax'];

    if (!$ajaxIsEnabled) {
        $lang->ougc_customrep_viewlatest = $lang->ougc_customrep_viewlatest_noajax;
    }

    foreach ($forumRatesCache as $rateID => $reputation) {
        if (!is_member($reputation['forums'], ['usergroup' => $forumID, 'additionalgroups' => '']
        )) {
            continue;
        }

        // $firstpost_only[$rateID] stores the tid
        // $threadData['firstpost'] is only set on post bit, since portal and thread list posts are indeed the first post
        if (!empty($firstpost_only[$rateID]) && !empty($threadData['firstpost']) && (int)$threadData['firstpost'] !== $postID) {
            continue;
        }

        $rateName = htmlspecialchars_uni(rateGetName($rateID) ?? $reputation['name']);

        $input['action'] = 'customReputation';

        $input['rid'] = $rateID;

        $link = urlHandlerBuild($input);

        $input['action'] = 'customReputationPopUp';

        $ratePopUpUrl = $popupurl = urlHandlerBuild($input);

        $totalReceivedRates = 0;

        $classextra = '';

        if ($ajaxIsEnabled) {
            $link = "javascript:OUGC_CustomReputation.Add('{$postData['tid']}', '{$postID}', '{$mybb->post_code}', '{$rateID}', '0');";
            if (!empty($mybb->settings['ougc_customrep_delete']) && $reputation['allowdeletion']) {
                $link_delete = "javascript:OUGC_CustomReputation.Add('{$postData['tid']}', '{$postID}', '{$mybb->post_code}', '{$rateID}', '1');";
            }
        }

        // Count the votes for this reputation in this post
        if (isset($customReputationCacheQuery[$rateID][$postID])) {
            $totalReceivedRates = count($customReputationCacheQuery[$rateID][$postID]);
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

        eval('$totalReceivedRates = "' . $templates->get('ougccustomrep_rep_number', 1, 0) . '";');

        $number = &$totalReceivedRates;

        $imageTemplateName = 'ougccustomrep_rep_img';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            $imageTemplateName = 'ougccustomrep_rep_img_fa';
        }

        $rateTitleText = '';

        $can_vote = is_member($reputation['groups']) && (int)$postData['uid'] !== (int)$mybb->user['uid'];

        if ($voted && $voted_this) {
            $can_vote = false;

            if (!empty($mybb->settings['ougc_customrep_delete']) && $reputation['allowdeletion']) {
                $link = $ajaxIsEnabled ? $link_delete : $link . '&amp;delete=1';

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

        if (!empty($reputation['customvariable']) || $setRateID && $setRateID === (int)$rateID) {
            $postThreadObject['customrep_' . $rateID] = $rep;
            /*if($setRateID)
            {
                break;
            }*/
        }

        if (empty($reputation['customvariable'])) {
            $ratesListCode .= $rep;
        }
    }
    unset($rateID, $reputation);

    /*if($setRateID)
    {
        return false;
    }*/

    $ratesListCode = trim($ratesListCode);

    $reputations = &$ratesListCode;

    // if $ratesListCode is empty maybe return false?
    eval('$postThreadObject[\'customrep\'] = "' . $templates->get('ougccustomrep') . '";');

    return true;
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

    public $active = false;

    // Construct the data (?)
    public function __construct()
    {
        global $mybb;

        // Fix: PHP warning on MyBB installation/upgrade
        if (is_object($mybb->cache)) {
            $plugins = $mybb->cache->read('plugins');
        }

        $this->rids = [];
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

        $PL || require_once PLUGINLIBRARY;

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
        $PL || require_once PLUGINLIBRARY;

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