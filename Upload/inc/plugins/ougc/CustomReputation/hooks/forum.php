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
use OUGC_CustomRep_AlertFormmatter;

use function ougc\CustomReputation\Core\alertsIsInstalled;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\logDelete;
use function ougc\CustomReputation\Core\logGet;
use function ougc\CustomReputation\Core\logInsert;

use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEGATIVE;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEUTRAL;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_POSITIVE;

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

        $formatterManager->registerFormatter(
            new OUGC_CustomRep_AlertFormmatter($mybb, $lang, 'ougc_customrep')
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
    global $customrep;

    $customrep->set_post($postData);

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
    global $uid;
    global $customReputationObjects, $existingCustomReputationLogs;
    global $customrep;

    $isExistingReputation = !empty($existing_reputation['uid']);

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

// todo, maybe allowdeletion should be checked when deleting a reputation to disable ?