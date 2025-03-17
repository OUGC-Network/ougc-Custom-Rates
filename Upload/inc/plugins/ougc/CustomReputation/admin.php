<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/plugins/ougc/CustomReputation/admin.php)
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

namespace ougc\CustomRates\Admin;

use DirectoryIterator;
use MybbStuff_MyAlerts_Entity_AlertType;
use stdClass;

use function ougc\CustomRates\Core\alertsIsInstalled;
use function ougc\CustomRates\Core\alertsObject;
use function ougc\CustomRates\Core\cacheUpdate;
use function ougc\CustomRates\Core\getSetting;
use function ougc\CustomRates\Core\loadLanguage;
use function ougc\CustomRates\Core\rateInsert;
use function ougc\CustomRates\Core\urlHandlerBuild;

use const PLUGINLIBRARY;
use const ougc\CustomRates\ROOT;

const TABLES_DATA = [
    'ougc_customrep' => [
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'image' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'allowedGroups' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'forums' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'disporder' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'visible' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'firstpost' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'allowdeletion' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'customvariable' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'requireattach' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'reptype' => [
            'type' => 'VARCHAR',
            'size' => 3,
            'default' => ''
        ],
        'ignorepoints' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'inmultiple' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'createCoreReputationType' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ]
    ],
    'ougc_customrep_log' => [
        'lid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'pid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'coreReputationID' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => [
            'piduidrid' => 'pid,uid,rid',
            //'pidrid' => 'pid,rid'
        ]
    ]
];

const FIELDS_DATA = [
    'reputation' => [
        'lid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougcCustomReputationCreatedOnLogID' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ]
    ]
];

function pluginInfo(): array
{
    global $lang;

    loadLanguage();

    return [
        'name' => 'ougc Custom Rates',
        'description' => $lang->ougc_customrep_d,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.31',
        'versioncode' => 1831,
        'compatibility' => '18*',
        'codename' => 'ougc_customrep',
        'myalerts' => getSetting('myAlertsVersion'),
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function pluginActivate(): bool
{
    global $PL, $cache, $lang;

    loadLanguage();

    loadPluginLibrary();

    $settingsContents = file_get_contents(ROOT . '/settings.json');

    $settingsData = json_decode($settingsContents, true);

    foreach ($settingsData as $settingKey => &$settingData) {
        if (empty($lang->{"setting_ougc_customrep_{$settingKey}"})) {
            continue;
        }

        if ($settingData['optionscode'] == 'select' || $settingData['optionscode'] == 'checkbox') {
            foreach ($settingData['options'] as $optionKey) {
                $settingData['optionscode'] .= "\n{$optionKey}={$lang->{"setting_ougc_customrep_{$settingKey}_{$optionKey}"}}";
            }
        }

        $settingData['title'] = $lang->{"setting_ougc_customrep_{$settingKey}"};

        $settingData['description'] = $lang->{"setting_ougc_customrep_{$settingKey}_desc"};
    }

    $PL->settings(
        'ougc_customrep',
        $lang->setting_group_ougcCustomReputation,
        $lang->setting_group_ougcCustomReputation_desc,
        $settingsData
    );

    $templates = [];

    if (file_exists($templateDirectory = ROOT . '/templates')) {
        $templatesDirIterator = new DirectoryIterator($templateDirectory);

        foreach ($templatesDirIterator as $template) {
            if (!$template->isFile()) {
                continue;
            }

            $pathName = $template->getPathname();

            $pathInfo = pathinfo($pathName);

            if ($pathInfo['extension'] === 'html') {
                $templates[$pathInfo['filename']] = file_get_contents($pathName);
            }
        }
    }

    if ($templates) {
        $PL->templates('ougccustomrep', 'ougc Custom Rates', $templates);
    }

    if ($styleSheetContents = file_get_contents(ROOT . '/stylesheet.css')) {
        $PL->stylesheet('ougc_customrep', $styleSheetContents, 'showthread.php|forumdisplay.php|portal.php|member.php');
    }

    if (alertsIsInstalled()) {
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();

        $alertType->setCode('ougc_customrep');

        $alertType->setEnabled();

        $alertType->setCanBeUserDisabled();

        alertsObject()->add($alertType);
    }

    $pluginInfo = pluginInfo();

    // Insert/update version into cache
    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    if (!isset($plugins['customrep'])) {
        $plugins['customrep'] = $pluginInfo['versioncode'];
    }

    dbVerifyTables();

    dbVerifyColumns();

    change_admin_permission('config', 'ougc_customrep');

    /*~*~* RUN UPDATES START *~*~*/
    global $db;

    if ($db->index_exists('ougc_customrep_log', 'piduid')) {
        $db->write_query('ALTER TABLE ' . TABLE_PREFIX . 'ougc_customrep_log DROP KEY piduid');
    }

    if ($db->field_exists('groups', 'ougc_customrep') &&
        !$db->field_exists('allowedGroups', 'ougc_customrep')) {
        $db->rename_column(
            'ougc_customrep',
            'groups',
            'allowedGroups',
            dbBuildFieldDefinition(TABLES_DATA['ougc_customrep']['allowedGroups'])
        );
    }

    /*~*~* RUN UPDATES END *~*~*/

    cacheUpdate();

    $plugins['customrep'] = $pluginInfo['versioncode'];

    $cache->update('ougc_plugins', $plugins);

    return true;
}

function pluginDeactivate(): bool
{
    global $PL;

    $PL || loadPluginLibrary();

    $PL->stylesheet_deactivate('ougc_customrep');

    change_admin_permission('config', 'ougc_customrep', 0);

    return true;
}

function pluginInstall(): bool
{
    dbVerifyTables();

    global $db;

    $query = $db->simple_select('reputation', 'rid', '', ['limit' => 1]);

    if (!$db->num_rows($query)) {
        rateInsert([
            'name' => 'Like',
            'image' => '{bburl}/images/ougc_customrep/default.png',
            'allowedGroups' => -1,
            'forums' => -1,
            'disporder' => 1,
        ]);
    }

    cacheUpdate();

    return true;
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (TABLES_DATA as $tableName => $tableColumns) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstall(): bool
{
    global $db, $PL, $cache;

    loadPluginLibrary();

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            $db->drop_table($tableName);
        }
    }

    foreach (FIELDS_DATA as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($db->field_exists($fieldName, $tableName)) {
                    $db->drop_column($tableName, $fieldName);
                }
            }
        }
    }

    $PL->stylesheet_delete('ougc_customrep');

    $PL->settings_delete('ougc_customrep');

    $PL->templates_delete('ougccustomrep');

    change_admin_permission('config', 'ougc_customrep', -1);

    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['customrep'])) {
        unset($plugins['customrep']);
    }

    $cache->delete('ougc_customrep');

    if (alertsIsInstalled()) {
        alertsObject()->deleteByCode('ougc_customrep');
    }

    if (!empty($plugins)) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $cache->delete('ougc_plugins');
    }

    return true;
}

function dbTables(): array
{
    $tables_data = [];

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tables_data[$tableName][$fieldName] = dbBuildFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tables_data[$tableName]['primary_key'] = $fieldName;
            }

            if ($fieldName === 'unique_key') {
                $tables_data[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tables_data;
}

function dbVerifyTables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$tableName}` (";

            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName != 'unique_key') {
                    $query_string .= "`{$fieldName}` {$fieldData},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(): bool
{
    global $db;

    foreach (dbTables() as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableColumns['unique_key'])) {
            foreach ($tableColumns['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($tableName, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$tableName} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }

    return true;
}

function dbVerifyColumns(): bool
{
    global $db;

    foreach (FIELDS_DATA as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", dbBuildFieldDefinition($fieldData));
            } else {
                $db->add_column($tableName, $fieldName, dbBuildFieldDefinition($fieldData));
            }
        }
    }

    return true;
}

function dbBuildFieldDefinition(array $fieldData): string
{
    $field_definition = '';

    $field_definition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $field_definition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $field_definition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $field_definition;
}

function pluginLibraryRequirements(): stdClass
{
    return (object)pluginInfo()['pl'];
}

function loadPluginLibrary(): bool
{
    global $PL, $lang;

    loadLanguage();

    $fileExists = file_exists(PLUGINLIBRARY);

    if ($fileExists && !($PL instanceof PluginLibrary)) {
        require_once PLUGINLIBRARY;
    }

    if (!$fileExists || $PL->version < pluginLibraryRequirements()->version) {
        flash_message(
            $lang->sprintf(
                $lang->ougcCustomReputationPluginLibrary,
                pluginLibraryRequirements()->url,
                pluginLibraryRequirements()->version
            ),
            'error'
        );

        \admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_redirect(string $redirectMessage = '', bool $isError = false)
{
    if ($redirectMessage) {
        flash_message($redirectMessage, ($isError ? 'error' : 'success'));
    }

    \admin_redirect(urlHandlerBuild());

    exit;
}