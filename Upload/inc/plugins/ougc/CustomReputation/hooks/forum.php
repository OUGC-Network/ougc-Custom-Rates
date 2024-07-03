<?php

/***************************************************************************
 *
 *	OUGC Custom Reputation plugin (/inc/plugins/ougc/CustomReputation/hooks/forum.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *	Website: https://ougc.network
 *
 *	Allow users rate posts with custom post reputations with rich features.
 *
 ***************************************************************************

 ****************************************************************************
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace ougc\CustomReputation\Hooks\Forum;

function global_start(): bool
{
    global $templatelist, $mybb, $lang;
    global $customrep;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    if (defined('THIS_SCRIPT')) {
        if (in_array(THIS_SCRIPT, ['forumdisplay.php', 'portal.php', 'reputation.php', 'showthread.php', 'editpost.php', 'member.php', 'attachment.php'])) {
            $templatelist .= 'ougccustomrep_headerinclude, ougccustomrep_headerinclude_fa, ougccustomrep_rep_number, ougccustomrep_rep_img, ougccustomrep_rep_img_fa, ougccustomrep_rep, ougccustomrep_rep_fa, ougccustomrep, ougccustomrep_rep_voted, ougccustomrep_xthreads_js, ougccustomrep_headerinclude_xthreads_editpost, ougccustomrep_headerinclude_xthreads';
        }

        if(THIS_SCRIPT === 'alerts.php')
        {
            \ougc\CustomReputation\Core\loadLanguage();
        }
    }

    if(!empty($customrep->myalerts_installed))
    {
        $formatterManager = \MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if(!$formatterManager)
        {
            $formatterManager = \MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new \OUGC_CustomRep_AlertFormmatter($mybb, $lang, 'ougc_customrep')
        );
    }

    return true;
}