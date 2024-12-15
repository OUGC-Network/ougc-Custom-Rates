<?php

/***************************************************************************
 *
 *    OUGC Custom Reputation plugin (/inc/plugins/ougc/CustomReputation/hooks/shared.php)
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

namespace ougc\CustomReputation\Hooks\Shared;

use userDataHandler;

use function ougc\CustomReputation\Core\logDelete;

function datahandler_user_delete_content(userDataHandler &$dataHandler): userDataHandler
{
    global $db;

    $query = $db->simple_select('ougc_customrep_log', 'lid', "uid IN({$dataHandler->delete_uids})");

    while ($log_id = $db->fetch_field($query, 'lid')) {
        logDelete((int)$log_id);
    }

    return $dataHandler;
}