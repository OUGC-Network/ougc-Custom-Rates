<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/ougc/CustomReputation/class_alerts.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2012 Omar Gonzalez
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

namespace ougc\CustomRates\Core;

use MybbStuff_MyAlerts_Entity_Alert;
use MybbStuff_MyAlerts_Formatter_AbstractFormatter;

class MyAlertsFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    /**
     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     *
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
        global $cache;

        $ratesCache = cacheGet();

        $alertContent = $alert->getExtraDetails();

        $rateID = (int)$alertContent['rid'];

        if (empty($rateID)) {
            $logID = (int)$alert->getObjectId();

            $logData = logGet($logID);

            if (!empty($logData['rid'])) {
                $rateID = (int)$logData['rid'];
            }
        }

        if (!empty($ratesCache[$rateID]) && !empty($ratesCache[$rateID]['name'])) {
            return $this->lang->sprintf(
                $this->lang->ougc_customrep_myalerts_alert,
                $outputAlert['from_user'],
                htmlspecialchars_uni($ratesCache[$rateID]['name'])
            );
        }

        return $this->lang->sprintf($this->lang->ougc_customrep_myalerts_alert_simple, $outputAlert['from_user']);
    }

    /**
     * Init function called before running formatAlert(). Used to load language files and initialize other required
     * resources.
     *
     * @return void
     */
    public function init()
    {
        loadLanguage();
    }

    /**
     * Build a link to an alert's content so that the system can redirect to it.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
     *
     * @return string The built alert, preferably an absolute link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
        global $mybb;

        $alertContent = $alert->getExtraDetails();

        $postData = get_post($alertContent['pid']);

        if (!empty($postData['pid'])) {
            return $mybb->settings['bburl'] . '/' . get_post_link(
                    $postData['pid'],
                    $alertContent['tid']
                ) . '#pid' . $postData['pid'];
        }

        $threadData = get_thread($alertContent['tid']);

        if (!empty($threadData['tid'])) {
            return $mybb->settings['bburl'] . '/' . get_thread_link($threadData['tid']);
        }

        return get_profile_link($alert->getFromUserId());
    }
}