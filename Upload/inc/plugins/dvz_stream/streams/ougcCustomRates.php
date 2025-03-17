<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/dvz_stream/ougcCustomRates.php)
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

use dvzStream\Stream;
use dvzStream\StreamEvent;

use function dvzStream\addStream;
use function dvzStream\getCsvSettingValues;
use function dvzStream\getInaccessibleForumIds;
use function ougc\CustomRates\Core\cacheGet;
use function ougc\CustomRates\Core\getTemplate;
use function ougc\CustomRates\Core\loadLanguage;
use function ougc\CustomRates\Core\rateGetImage;
use function ougc\CustomRates\Core\rateGetName;

global $lang;

$stream = new Stream();

$stream->setName(explode('.', basename(__FILE__))[0]);

loadLanguage();

$stream->setTitle($lang->ougcCustomRatesDvzStream);

$stream->setEventTitle($lang->ougcCustomRatesDvzStreamEvent);

$stream->setFetchHandler(function (int $query_limit, int $last_log_id = 0) use ($stream) {
    global $db, $cache;

    $whereClauses = ["l.lid>'{$last_log_id}'", "t.visible='1'", "t.closed NOT LIKE 'moved|%'", "p.visible='1'"];

    $hiddenForums = array_merge(
        getInaccessibleForumIds(),
        getCsvSettingValues('hidden_forums')
    );

    if (in_array(-1, $hiddenForums)) {
        return [];
    }

    if ($hiddenForums) {
        $whereClauses[] = 't.fid NOT IN (' . implode(',', $hiddenForums) . ')';
    }

    $ratesCache = cacheGet();

    $ratesIDs = implode("','", array_keys($ratesCache));

    $whereClauses[] = "l.rid IN ('{$ratesIDs}')";

    $query = $db->simple_select(
        "ougc_customrep_log l LEFT JOIN {$db->table_prefix}posts p ON (p.pid=l.pid) LEFT JOIN {$db->table_prefix}threads t ON (t.tid=p.tid)",
        'l.lid AS logID, l.pid AS postID, l.uid AS userID, l.rid AS rateID, l.dateline AS logStamp, p.subject AS postSubject, t.tid AS threadID, t.firstpost AS firstPostID, t.subject AS threadSubject, t.fid AS forumID, t.prefix AS threadPrefix',
        implode(' AND ', $whereClauses),
        ['order_by' => 'l.dateline', 'order_dir' => 'desc', 'limit' => $query_limit]
    );

    $logsCache = [];

    while ($logData = $db->fetch_array($query)) {
        $logsCache[(int)$logData['logID']] = $logData;
    }

    $usersCache = [];

    $userIDs = implode("','", array_map('intval', array_column($logsCache, 'userID')));

    $query = $db->simple_select(
        'users',
        'uid AS userID, username AS userName, usergroup AS userGroup, displaygroup AS displayGroup, avatar AS userAvatar',
        "uid IN ('{$userIDs}')"
    );

    while ($user_data = $db->fetch_array($query)) {
        $usersCache[(int)$user_data['userID']] = $user_data;
    }

    $forumsCache = (array)$cache->read('forums');

    $prefixesCache = (array)$cache->read('threadprefixes');

    $streamEvents = [];

    foreach ($logsCache as $logID => $logData) {
        $rateID = (int)$logData['rateID'];

        $rateData = $ratesCache[$rateID] ?? [];

        if (!$rateData) {
            continue;
        }

        $streamEvent = new StreamEvent();

        $streamEvent->setStream($stream);

        $streamEvent->setId($logID);

        $streamEvent->setDate($logData['logStamp']);

        $streamEvent->setUser([
            'id' => $logData['userID'],
            'username' => $usersCache[$logData['userID']]['userName'],
            'usergroup' => $usersCache[$logData['userID']]['userGroup'],
            'displaygroup' => $usersCache[$logData['userID']]['displayGroup'],
            'avatar' => $usersCache[$logData['userID']]['userAvatar'],
        ]);

        $streamEvent->addData([
            'rateID' => $rateID,
            'rateName' => $ratesCache[$rateID]['name'],
            'rateImage' => $ratesCache[$rateID]['image'] ?? '',
            'firstPostOnly' => !empty($ratesCache[$rateID]['firstpost']),
            'isFirstPost' => (int)$logData['postID'] === (int)$logData['firstPostID'],
            'firstPostID' => (int)$logData['firstPostID'],
            'forumID' => (int)$logData['forumID'],
            'forumName' => $forumsCache[$logData['forumID']]['name'] ?? '',
            'threadID' => (int)$logData['threadID'],
            'threadPrefix' => $prefixesCache[$logData['threadPrefix']] ?? '',
            'postID' => (int)$logData['postID'],
            'postSubject' => $logData['postSubject'],
            'threadSubject' => $logData['threadSubject']
        ]);

        $streamEvents[] = $streamEvent;
    }

    return $streamEvents;
});

$stream->addProcessHandler(function (StreamEvent $streamEvent) {
    global $mybb, $lang;

    $streamData = $streamEvent->getData();

    $imageTemplateName = 'rep_img';

    if (!empty($mybb->settings['ougc_customrep_fontawesome'])) {
        $imageTemplateName = 'rep_img_fa';
    }

    if (!($rateName = rateGetName($streamData['rateID']))) {
        $rateName = htmlspecialchars_uni($streamData['rateName']);
    } else {
        $rateName = htmlspecialchars_uni($rateName);
    }

    $rateTitleText = $lang_val = $rateName;

    $rateImage = rateGetImage($streamData['rateImage'], $streamData['rateID']);

    $rateImage = eval(getTemplate($imageTemplateName, false));

    global $parser;

    if (!($parser instanceof postParser)) {
        require_once MYBB_ROOT . 'inc/class_parser.php';

        $parser = new postParser();
    }

    if ($streamData['isFirstPost']) {
        $streamText = $lang->sprintf(
            $lang->ougcCustomRatesDvzStreamTextThread,
            $parser->parse_badwords($streamData['threadSubject'])
        );

        $postLink = get_post_link(
                $streamData['firstPostID'],
                $streamData['threadID']
            ) . '#pid' . $streamData['firstPostID'];
    } else {
        $streamText = $lang->sprintf(
            $lang->ougcCustomRatesDvzStreamTextPost,
            $parser->parse_badwords($streamData['postSubject'])
        );

        $postLink = get_post_link($streamData['postID'], $streamData['threadID']) . '#pid' . $streamData['postID'];
    }

    $threadPrefix = '';

    if (!empty($streamData['threadPrefix']['displaystyle'])) {
        $threadPrefix = $streamData['threadPrefix']['displaystyle'];
    }

    $streamItem = eval(getTemplate('streamItem'));

    $streamEvent->setItem($streamItem);

    $forumLink = get_forum_link($streamData['forumID']);

    $forumName = htmlspecialchars_uni($streamData['forumName']);

    $streamLocation = eval(getTemplate('streamLocation'));

    $streamEvent->setLocation($streamLocation);
});

addStream($stream);
