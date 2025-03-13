<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/ougc_customrep.php)
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

use function ougc\CustomRates\Admin\pluginActivate;
use function ougc\CustomRates\Admin\pluginDeactivate;
use function ougc\CustomRates\Admin\pluginInfo;
use function ougc\CustomRates\Admin\pluginInstall;
use function ougc\CustomRates\Admin\pluginIsInstalled;
use function ougc\CustomRates\Admin\pluginUninstall;
use function ougc\CustomRates\Core\addHooks;
use function ougc\CustomRates\Core\cacheGet;
use function ougc\CustomRates\Core\cacheUpdate;
use function ougc\CustomRates\Core\getTemplate;
use function ougc\CustomRates\Core\loadLanguage;

use const ougc\CustomRates\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('ougc\CustomRates\Core\SETTINGS', [
    //'key' => 'value',
    'myAlertsVersion' => '2.1.0'
]);

define('ougc\CustomRates\Core\DEBUG', false);

define('ougc\CustomRates\ROOT', constant('MYBB_ROOT') . 'inc/plugins/ougc/CustomReputation');

require_once ROOT . '/core.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('ougc\CustomRates\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    addHooks('ougc\CustomRates\Hooks\Forum');
}

require_once ROOT . '/hooks/shared.php';

addHooks('ougc\CustomRates\Hooks\Shared');

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

// Helper function for xThreads feature
function ougc_customrep_xthreads_hide(
    string $hiddenContent = '',
    string $requiredRateSettingThreadFieldKey = ''
): string {
    global $threadfields, $cache, $thread, $mybb, $db, $lang, $fid;

    $forumID = (int)($fid ?? 0);

    loadLanguage();

    static $ratesCache = null;

    if ($ratesCache === null) {
        $ratesCache = cacheGet();

        if ($forumID) {
            foreach ($ratesCache as $rateID => $rateData) {
                if (!is_member($rateData['forums'], ['usergroup' => $forumID, 'additionalgroups' => ''])) {
                    unset($ratesCache[$rateID]);
                }
            }
        }
    }

    $requireAnyRate = $requiredRateSettingThreadFieldKey === '';

    $requiredRateID = $threadfields[$requiredRateSettingThreadFieldKey] ?? 0;

    if (empty($ratesCache) || (!$requireAnyRate && empty($ratesCache[$requiredRateID]))) {
        return $hiddenContent;
    }

    $firstPostID = (int)($thread['firstpost'] ?? 0);

    $currentUserID = (int)$mybb->user['uid'];

    if ((int)$thread['uid'] === $currentUserID) {
        return $hiddenContent;
    }

    // such rating doesn't exists (maybe because none was selected), do nothing
    if (!$requireAnyRate && empty($requiredRateID)) {
        return $hiddenContent;
    }

    // If thread field data is empty we assume author didn't select a value
    if (!$requireAnyRate && isset($requiredRateID) && empty($requiredRateID)) {
        return $hiddenContent;
    }

    if (!empty($firstPostID) && ($requireAnyRate || !empty($ratesCache[$requiredRateID]))) {
        $whereClauses = ["pid='{$firstPostID}'", "uid='{$currentUserID}'"];

        if (!$requireAnyRate) {
            $whereClauses[] = "rid='{$requiredRateID}'";
        }

        $query = $db->simple_select(
            'ougc_customrep_log',
            'lid',
            implode(' AND ', $whereClauses),
            ['limit', 1]
        );

        if (!$db->num_rows($query)) {
            if ($requireAnyRate) {
                $hiddenContent = $lang->ougc_customrep_xthreads_error_user_any;
            } else {
                $hiddenContent = $lang->sprintf(
                    $lang->ougc_customrep_xthreads_error_user,
                    htmlspecialchars_uni($ratesCache[$requiredRateID]['name'])
                );
            }
        }

        $hiddenContent .= eval(getTemplate('xthreads_js'));

        return $hiddenContent;
    }

    // Something is wrong, not sure what
    return $lang->ougc_customrep_xthreads_error;
}

class OUGC_CustomRep
{
    public $post = [];
}

$GLOBALS['customrep'] = new OUGC_CustomRep();

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