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

use function ougc\CustomReputation\Core\addHooks;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\urlHandlerBuild;
use function ougc\CustomReputation\Core\urlHandlerSet;

use const ougc\CustomReputation\ROOT;

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('ougc\CustomReputation\Core\SETTINGS', [
    //'key' => 'value',
]);

define('ougc\CustomReputation\Core\DEBUG', false);

define('ougc\CustomReputation\ROOT', constant('MYBB_ROOT') . 'inc/plugins/ougc/CustomReputation');

require_once ROOT . '/core.php';

if (defined('IN_ADMINCP')) {
    //require_once \ougc\CustomReputation\ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('ougc\CustomReputation\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    addHooks('ougc\CustomReputation\Hooks\Forum');
}

global $plugins;

// Add our hooks
if (defined('IN_ADMINCP')) {
    // Users merge
    $plugins->add_hook('admin_user_users_merge_commit', 'ougc_customrep_users_merge');

    // xThreads setting
    $plugins->add_hook('admin_formcontainer_output_row', 'ougc_customrep_admin_formcontainer_output_row');
} else {
    if (defined('THIS_SCRIPT')) {
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

                $plugins->add_hook('member_profile_end', 'ougc_customrep_member_profile_end');

                $plugins->add_hook('editpost_end', 'ougc_customrep_editpost_end');

                $plugins->add_hook('attachment_start', 'ougc_customrep_attachment_start');

                // Moderation
                $plugins->add_hook('class_moderation_delete_thread_start', 'ougc_customrep_delete_thread');
                $plugins->add_hook('class_moderation_delete_post_start', 'ougc_customrep_delete_post');
                $plugins->add_hook('class_moderation_merge_posts', 'ougc_customrep_merge_posts');
                #$plugins->add_hook('class_moderation_merge_threads', 'ougc_customrep_merge_threads'); // seems like posts are updated instead of "re-created", good, less work
                #$plugins->add_hook('class_moderation_split_posts', 'ougc_customrep_merge_threads'); // no sure what happens here
                break;
        }
    }
}

$plugins->add_hook('datahandler_user_delete_content', 'ougc_customrep_user_delete_content');

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

// Plugin API
function ougc_customrep_info()
{
    global $lang, $customrep;
    $customrep->lang_load();

    return [
        'name' => 'OUGC Custom Reputation',
        'description' => $lang->ougc_customrep_d,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.22',
        'versioncode' => 1822,
        'compatibility' => '18*',
        'codename' => 'ougc_customrep',
        'newpoints' => '2.1.1',
        'myalerts' => '2.0.4',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

// _activate function
function ougc_customrep_activate()
{
    global $lang, $customrep, $PL;
    $customrep->lang_load();
    $customrep->meets_requirements() or $customrep->admin_redirect($customrep->message, true);

    $PL->stylesheet(
        'ougc_customrep',
        '/***************************************************************************
 *
 *	OUGC Custom Reputation plugin (CSS FILE)
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

.customrep .number {
	border: 1px solid #ccc;
	background: #eee;
	position: absolute;
	z-index: 2;
	top: -0.7em;
	right: -1em;
	min-width: 1.2em;
	min-height: 1.2em;
	padding: .2em;
    line-height: 1;
	text-align: center;
	-moz-border-radius: 500rem;
	-webkit-border-radius: 500rem;
	border-radius: 500rem;
	text-decoration: none !important;
	font-weight: bolder;
}

.customrep .number.voted {
	/*background: #fdd;*/
}

.customrep img {
	vertical-align: middle;
}

.customrep img, .customrep i {
	cursor: pointer;
    line-height: 1;
}

.customrep > span {
	display: inline-block;
	padding: 2px 5px;
	margin: 2px;
	background: #eee url(images/buttons_bg.png) repeat-x;
	border: 1px solid #ccc;
	-moz-border-radius: 6px;
	-webkit-border-radius: 6px;
	border-radius: 6px;
	position: relative;
	margin-right: 1em;
}

.customrep, .customrep * {
	font-size: 11px;
}',
        'showthread.php|forumdisplay.php|portal.php|member.php'
    );

    // Modify some templates.
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'postbit',
        '#' . preg_quote('{$post[\'button_rep\']}') . '#i',
        '{$post[\'button_rep\']}{$post[\'customrep\']}'
    );
    find_replace_templatesets(
        'postbit',
        '#' . preg_quote('{$deleted_bit}') . '#i',
        '{$deleted_bit}{$post[\'customrep_ignorebit\']}'
    );
    find_replace_templatesets(
        'postbit',
        '#' . preg_quote('{$post_visibility}') . '#i',
        '{$post_visibility}{$post[\'customrep_post_visibility\']}'
    );
    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$post[\'button_rep\']}') . '#i',
        '{$post[\'button_rep\']}{$post[\'customrep\']}'
    );
    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$deleted_bit}') . '#i',
        '{$deleted_bit}{$post[\'customrep_ignorebit\']}'
    );
    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$post_visibility}') . '#i',
        '{$post_visibility}{$post[\'customrep_post_visibility\']}'
    );
    find_replace_templatesets(
        'postbit_reputation',
        '#' . preg_quote('{$post[\'userreputation\']}') . '#i',
        '<span id="customrep_rep_{$post[\'pid\']}">{$post[\'userreputation\']}</span>',
        0
    );
    find_replace_templatesets(
        'forumdisplay_thread',
        '#' . preg_quote('{$attachment_count}') . '#i',
        '{$attachment_count}{$thread[\'customrep\']}'
    );
    find_replace_templatesets(
        'portal_announcement',
        '#' . preg_quote('{$senditem}') . '#i',
        '{$senditem}{$announcement[\'customrep\']}'
    );
    find_replace_templatesets(
        'member_profile',
        '#' . preg_quote('{$modoptions}') . '#i',
        '{$modoptions}{$memprofile[\'customrep\']}'
    );

    // Add our settings
    $PL->settings('ougc_customrep', $lang->ougc_customrep, $lang->ougc_customrep_d, [
        'firstpost' => [
            'title' => $lang->setting_ougc_customrep_firstpost,
            'description' => $lang->setting_ougc_customrep_firstpost_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'delete' => [
            'title' => $lang->setting_ougc_customrep_delete,
            'description' => $lang->setting_ougc_customrep_delete_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'perpage' => [
            'title' => $lang->setting_ougc_customrep_perpage,
            'description' => $lang->setting_ougc_customrep_perpage_desc,
            'optionscode' => 'text',
            'value' => 10,
        ],
        'fontawesome' => [
            'title' => $lang->setting_ougc_customrep_fontawesome,
            'description' => $lang->setting_ougc_customrep_fontawesome_desc,
            'optionscode' => 'yesno',
            'value' => 0,
        ],
        'fontawesome_acp' => [
            'title' => $lang->setting_ougc_customrep_fontawesome_acp,
            'description' => $lang->setting_ougc_customrep_fontawesome_acp_desc,
            'optionscode' => 'text',
            'value' => '<link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">',
        ],
        'threadlist' => [
            'title' => $lang->setting_ougc_customrep_threadlist,
            'description' => $lang->setting_ougc_customrep_threadlist_desc,
            'optionscode' => 'forumselect',
            'value' => -1,
        ],
        'portal' => [
            'title' => $lang->setting_ougc_customrep_portal,
            'description' => $lang->setting_ougc_customrep_portal_desc,
            'optionscode' => 'forumselect',
            'value' => -1,
        ],
        'xthreads_hide' => [
            'title' => $lang->setting_ougc_xthreads_hide,
            'description' => $lang->setting_ougc_xthreads_hide_desc,
            'optionscode' => 'text',
            'value' => '',
        ],
        'stats_profile' => [
            'title' => $lang->setting_ougc_stats_profile,
            'description' => $lang->setting_ougc_stats_profile_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'enableajax' => [
            'title' => $lang->setting_ougc_enableajax,
            'description' => $lang->setting_ougc_enableajax_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'guests_popup' => [
            'title' => $lang->setting_ougc_guests_popup,
            'description' => $lang->setting_ougc_guests_popup_desc,
            'optionscode' => 'yesno',
            'value' => 0,
        ],
        'myalerts' => [
            'title' => $lang->setting_ougc_myalerts,
            'description' => $lang->setting_ougc_myalerts_desc,
            'optionscode' => 'yesno',
            'value' => 0,
        ],
        'multiple' => [
            'title' => $lang->setting_ougc_multiple,
            'description' => $lang->setting_ougc_multiple_desc,
            'optionscode' => 'yesno',
            'value' => 0,
        ],
    ]);

    // Fill cache
    $customrep->update_cache();

    // Insert template/group
    $PL->templates('ougccustomrep', $lang->ougc_customrep, [
        '' => '<span id="customrep_{$customrep->post[\'pid\']}">{$reputations}</span>',
        'headerinclude' => <<<EOF
		<script>
		/***************************************************************************
		 *
		 *	OUGC Custom Reputation plugin (JAVASCRIPT FILE)
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
		
		var OUGC_CustomReputation = {
			Add: function(tid, pid, postcode, rid, del)
			{
				var deleteit = '';
				if(del == 1)
				{
					deleteit = '&delete=1';
				}
		
				$.ajax(
				{
					url: 'showthread.php?tid=' + tid + '&action=customrep&pid=' + pid + '&my_post_key=' + postcode + '&rid=' + rid + deleteit,
					type: 'post',
					dataType: 'json',
					success: function (request)
					{
						if(request.errors)
						{
							alert(request.errors);
							return false;
						}
						if(request.success == 1)
						{
							$('#customrep_' + parseInt(request.pid)).replaceWith(request.content);
							$('#customrep_' + parseInt(request.pid) + '_' + parseInt(request.rid)).replaceWith(request.content_rep);
							$('#customrep_rep_' + parseInt(request.pid)).replaceWith(request.userreputation);
		
							if(typeof ougccustomrep_xthreads_activate !== 'undefined')
							{
								$.get('showthread.php?tid=' + tid + '&pid=' + pid, function( data ) {
									{\$xthreads_variables}
								});
							}
		
							return true;
						}
					}
				});
			},
		
			xThreads: function(value, field)
			{
				var input = parseInt(value);
		
				{\$xthreads_hideskip}
		
				if(value > 0)
				{
					$('#xt_' + field).show();
				}
				else
				{
					$('#xt_' + field).hide();
				}
			},
		
			xThreadsHideSet: function(field)
			{
				if(typeof window.hide_fields === 'undefined')
				{
					window.hide_fields = new Array;
				}
		
				window.hide_fields = $.merge([field], window.hide_fields);
			},
		}
		
		$( document ).ready(function() {
			{\$xthreads_variables_editpost}
		});
		</script>
		{\$font_awesome}
EOF
        ,
        'headerinclude_fa' => '<link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">',
        'headerinclude_xthreads' => '$(\'#xt_{$xt_field}\').html( $(\'#xt_{$xt_field}\', $(data)).html() );',
        'headerinclude_xthreads_editpost' => '$(\'[name^="xthreads_{$xt_field}"]\').on(\'change\', function() {
	if($(\'input[name^="xthreads_{$xt_field}"]\').is(":checkbox") || $(\'input[name^="xthreads_{$xt_field}"]\').is(":radio"))
	{
		OUGC_CustomReputation.xThreads(+this.value, \'{$xt_field}\');
		return true;
	}
	OUGC_CustomReputation.xThreads(+this.value, \'{$xt_field}\');
});
OUGC_CustomReputation.xThreads(\'{$default_value}\', \'{$xt_field}\');',
        'headerinclude_xthreads_editpost_hidecode' => 'var fields = window.hide_fields;
if(typeof fields !== \'undefined\')
{
	var arrayLength = window.hide_fields.length;
	for (var i = 0; i < arrayLength; i++) {
		if(window.hide_fields[i] == field)
		{
			return false;
		}
	}
}',
        'misc' => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="text-align: left;">
	<tr><td class="thead" colspan="2"><strong>{$title}</strong></td></tr>
	<tr><td class="tcat" colspan="2"><strong>{$desc}</strong></td></tr>
	{$content}
	{$multipage}
</table>',
        'misc_multipage' => '<tr><td class="tfoot" colspan="2">{$multipage}</td></tr>',
        'misc_error' => '<tr><td class="trow1" colspan="2">{$error_message}</td></tr>',
        'misc_row' => '<tr>
<td class="{$trow}" width="60%">{$log[\'profilelink_f\']}</td>
<td class="{$trow}" width="40%" align="center">{$date}</td>
</tr>',
        'rep' => '<span title="{$lang_val}" class="customrep float_right" id="customrep_{$customrep->post[\'pid\']}_{$rid}">{$image} {$reputation[\'name\']} {$number}</span>',
        'rep_img' => '<img src="{$reputation[\'image\']}" title="{$lang_val}" />',
        'rep_img_fa' => '<i class="{$reputation[\'image\']}" aria-hidden="true"></i>',
        'rep_number' => '&nbsp;<a href="javascript:MyBB.popupWindow(\'/{$popupurl}\');" rel="nofollow" title="{$lang->ougc_customrep_viewall}" class="number {$voted_class}" title="{$lang->ougc_customrep_viewlatest}" id="ougccustomrep_view_{$customrep->post[\'pid\']}">{$number}</a>',
        'rep_voted' => '<a href="{$link}" class="voted {$classextra}">{$image}</a>',
        'postbit_reputation' => '<span id="customrep_rep_{$post[\'pid\']}">{$post[\'userreputation\']}</span>',
        'profile' => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" width="100%" class="tborder">
	<tr>
		<td colspan="2" class="thead"><strong>{$lang->ougc_customrep_profile_stats}</strong></td>
	</tr>
	<tr>
		<td colspan="2" class="tcat"><strong>{$lang->ougc_customrep_profile_stats_received}</strong></td>
	</tr>
	{$rates_received}
	<tr>
		<td colspan="2" class="tcat"><strong>{$lang->ougc_customrep_profile_stats_given}</strong></td>
	</tr>
	{$rates_given}
</table>
<br />',
        'profile_empty' => '<tr>
	<td class="{$trow}" colspan="2">
		{$lang->ougc_customrep_profile_stats_empty}
	</td>
</tr>',
        'profile_number' => '&nbsp;<span class="number">{$number}</span>',
        'profile_row' => '<tr>
	<td class="trow1">
		<span class="customrep">{$reputations}</span>
	</td>
</tr>',
        'modal' => '<div class="modal"><div style="overflow-y: auto; max-height: 400px;">{$page}</div></div>',
        'xthreads_js' => '<script type="text/javascript">
<!--
	var ougccustomrep_xthreads_activate = 1;
// -->
</script>'
    ]);

    change_admin_permission('config', 'ougc_customrep', 1);

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            global $db, $cache;

            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('ougc_customrep');
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);
    }

    global $cache;

    // Insert version code into cache
    $plugins = $cache->read('ougc_plugins');
    if (!$plugins) {
        $plugins = [];
    }

    $info = ougc_customrep_info();
    if (isset($plugins['customrep'])) {
        global $customrep;

        $customrep->_db_verify_tables();
        $customrep->_db_verify_columns();
        $customrep->_db_verify_indexes();
    }
    $plugins['customrep'] = $info['versioncode'];
    $cache->update('ougc_plugins', $plugins);
}

// _deactivate function
function ougc_customrep_deactivate()
{
    global $customrep, $PL;
    $customrep->meets_requirements() or $customrep->admin_redirect($customrep->message, true);

    // Remove stylesheet
    $PL->stylesheet_deactivate('ougc_customrep');

    // Revert template edits
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets('postbit', '#' . preg_quote('{$post[\'customrep\']}') . '#i', '', 0);
    find_replace_templatesets('postbit', '#' . preg_quote('{$post[\'customrep_ignorebit\']}') . '#i', '', 0);
    find_replace_templatesets('postbit', '#' . preg_quote('{$post[\'customrep_post_visibility\']}') . '#i', '', 0);
    find_replace_templatesets('postbit_classic', '#' . preg_quote('{$post[\'customrep\']}') . '#i', '', 0);
    find_replace_templatesets('postbit_classic', '#' . preg_quote('{$post[\'customrep_ignorebit\']}') . '#i', '', 0);
    find_replace_templatesets(
        'postbit_classic',
        '#' . preg_quote('{$post[\'customrep_post_visibility\']}') . '#i',
        '',
        0
    );
    find_replace_templatesets(
        'postbit_reputation',
        '#' . preg_quote('<span id="customrep_rep_{$post[\'pid\']}">{$post[\'userreputation\']}</span>') . '#i',
        '{$post[\'userreputation\']}',
        0
    );
    find_replace_templatesets('forumdisplay_thread', '#' . preg_quote('{$thread[\'customrep\']}') . '#i', '', 0);
    find_replace_templatesets('portal_announcement', '#' . preg_quote('{$announcement[\'customrep\']}') . '#i', '', 0);
    find_replace_templatesets('member_profile', '#' . preg_quote('{$memprofile[\'customrep\']}') . '#i', '', 0);

    change_admin_permission('config', 'ougc_customrep', 0);
}

// _install function
function ougc_customrep_install()
{
    global $customrep;
    ougc_customrep_uninstall();

    $customrep->_db_verify_tables();
    $customrep->_db_verify_columns();
    $customrep->_db_verify_indexes();

    // Add a default reputation type
    $customrep->insert_rep([
        'name' => 'Like',
        'image' => '{bburl}/images/ougc_customrep/default.png',
        'groups' => -1,
        'forums' => -1,
        'disporder' => 1,
        'visible' => 1,
        'firstpost' => 1,
        'allowdeletion' => 1,
        'customvariable' => 0,
        'requireattach' => 0,
        'points' => 0,
        'ignorepoints' => 0,
        'inmultiple' => 0,
        'createCoreReputationType' => 0,
    ]);
}

// _is_installed function
function ougc_customrep_is_installed()
{
    global $db, $customrep;

    foreach ($customrep->_db_tables() as $name => $table) {
        $installed = $db->table_exists($name);
        break;
    }

    return $installed;
}

// _uninstall function
function ougc_customrep_uninstall()
{
    global $customrep, $db, $PL;
    $customrep->meets_requirements() or $customrep->admin_redirect($customrep->message, true);

    // Drop DB entries
    foreach ($customrep->_db_tables() as $name => $table) {
        $db->drop_table($name);
    }
    foreach ($customrep->_db_columns() as $table => $columns) {
        foreach ($columns as $name => $definition) {
            !$db->field_exists($name, $table) or $db->drop_column($table, $name);
        }
    }

    // Delete the cache.
    $PL->cache_delete('ougc_customrep');

    // Delete stylesheet
    $PL->stylesheet_delete('ougc_customrep');

    // Delete settings
    $PL->settings_delete('ougc_customrep');

    // Delete template/group
    $PL->templates_delete('ougccustomrep');

    change_admin_permission('config', 'ougc_customrep', -1);

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            global $cache;

            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertTypeManager->deleteByCode('ougc_customrep');
    }

    global $cache;

    // Remove version code from cache
    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['customrep'])) {
        unset($plugins['customrep']);
    }

    if ($plugins) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $PL->cache_delete('ougc_plugins');
    }
}

// _is_installed function
function reload_ougc_customrep(): bool
{
    global $customrep;

    return $customrep->update_cache();
}

//Merging two accounts, update data propertly
function ougc_customrep_users_merge()
{
    global $db, $destination_user, $source_user;

    $fromuid = (int)$source_user['uid'];
    $touid = (int)$destination_user['uid'];

    // Query all logs that belong to the $fromuid user and update them
    $query = $db->simple_select('ougc_customrep_log', 'lid', 'uid=\'' . $fromuid . '\'');
    while ($lid = $db->fetch_field($query, 'lid')) {
        global $customrep;

        $customrep->update_log($lid, ['uid' => $touid]);
    }
}

// Fetch a list of xThreads fields to build the setting
function ougc_customrep_admin_formcontainer_output_row(&$args)
{
    global $lang, $cache, $form, $mybb;

    if (empty($args['title']) || $args['title'] != $lang->setting_ougc_xthreads_hide) {
        return;
    }

    $threadfields = $cache->read('threadfields');

    if (!($xthreads = function_exists('xthreads_gettfcache') && !empty($threadfields))) {
        $args['content'] = $lang->setting_ougc_xthreads_information;
        return;
    }

    $selected_list = $mybb->settings['ougc_customrep_xthreads_hide'];
    if (isset($mybb->input['upsetting']['ougc_customrep_xthreads_hide'])) {
        $selected_list = $mybb->input['upsetting']['ougc_customrep_xthreads_hide'];
    }

    $saved_fields = explode(',', $selected_list);

    $option_list = $selected_list = [];
    foreach ($threadfields as $tf) {
        if (array_intersect([$tf['field']], $saved_fields)) {
            $selected_list[] = $tf['field'];
        }
        $option_list[$tf['field']] = $tf['field'];
    }

    $args['content'] = $form->generate_select_box(
        'upsetting[ougc_customrep_xthreads_hide][]',
        $option_list,
        $selected_list,
        ['id' => 'row_setting_ougc_customrep_xthreads_hide', 'size' => 5, 'multiple' => true]
    );
}

// Delete logs from users which are being deleted
function ougc_customrep_user_delete_content(&$dh)
{
    global $db, $customrep;

    $query = $db->simple_select('ougc_customrep_log', 'lid', 'uid IN(' . $dh->delete_uids . ')');
    while ($lid = $db->fetch_field($query, 'lid')) {
        $customrep->delete_log($lid);
    }
}

// Required for xThreads hack
function ougc_customrep_editpost_end()
{
    global $mybb, $footer, $templates, $post, $thread, $threadfields;

    $font_awesome = '';
    if ($mybb->settings['ougc_customrep_fontawesome']) {
        eval('$font_awesome .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
    }

    $xthreads_variables_editpost = $xthreads_hideskip = '';
    if ($mybb->settings['ougc_customrep_xthreads_hide']) {
        $xt_fields = explode(',', str_replace('_require', '', $mybb->settings['ougc_customrep_xthreads_hide']));
        foreach ($xt_fields as $xt_field) {
            if (!isset($threadfields[$xt_field])) {
                continue;
            }

            eval(
                '$xthreads_hideskip .= "' . $templates->get(
                    'ougccustomrep_headerinclude_xthreads_editpost_hidecode'
                ) . '";'
            );

            $default_value = (int)$threadfields[$xt_field . '_require'];
            if (isset($mybb->input['xthreads_ougc_customrep'])) {
                $default_value = $mybb->get_input('xthreads_ougc_customrep', MyBB::INPUT_INT);
            }

            $xthreads_variables_editpost .= eval(
            $templates->render(
                'ougccustomrep_headerinclude_xthreads_editpost',
                true,
                false
            )
            );
        }
    }

    eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');
}

// Required for attachments hack
function ougc_customrep_attachment_start()
{
    global $attachment, $mybb, $cache, $customrep, $db, $lang;

    if (!$attachment || isset($mybb->input['thumbnail'])) {
        return;
    }

    if ($attachment['thumbnail'] == '' && isset($mybb->input['thumbnail'])) {
        return;
    }

    $attachtypes = (array)$cache->read('attachtypes');
    $ext = get_extension($attachment['filename']);

    if (empty($attachtypes[$ext])) {
        return;
    }

    $pid = (int)$attachment['pid'];
    $attachment['uid'] = (int)$attachment['uid'];
    $mybb->user['uid'] = (int)$mybb->user['uid'];

    if (!$pid || $attachment['uid'] == $mybb->user['uid']) {
        // this is a preview or the downloader is the author
        return;
    }

    $post = get_post($pid);

    if (!$post['pid'] || $post['visible'] == -2) {
        // the post doesn't exists or the post is a draft
        return;
    }

    $thread = get_thread($post['tid']);

    if (!$thread) {
        // the thread doesn't exists
        return;
    }

    // It appears everything is OK, lets check if this forum has any reps
    $reps = (array)$cache->read('ougc_customrep');

    $is_first_post = ($thread['firstpost'] == $post['pid']);

    if ($customrep->firstpost_only && !$is_first_post) {
        return;
    }

    $required_rates = [];
    foreach ($reps as $rid => $reputation) {
        if ($reputation['requireattach'] && is_member($reputation['groups']) && is_member(
                $reputation['forums'],
                ['usergroup' => $thread['fid'], 'additionalgroups' => '']
            )) {
            if ($reputation['firstpost'] && !$is_first_post) {
                continue;
            }

            $required_rates[(int)$rid] = (int)$rid;
        }
    }

    if (empty($required_rates)) {
        // user can't rate anything that is required
        return;
    }

    $rids = implode("','", array_keys($required_rates));

    $query = $db->simple_select(
        'ougc_customrep_log',
        'lid',
        "pid='{$pid}' AND rid IN ('{$rids}') AND uid='{$mybb->user['uid']}'"
    );

    if ($db->num_rows($query)) {
        return;
    }

    $customrep->lang_load();

    error($lang->ougc_customrep_error_nopermission_attachment);
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

    $font_awesome = '';
    if ($mybb->settings['ougc_customrep_fontawesome']) {
        eval('$font_awesome .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
    }

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

    $customrep->set_url(get_thread_link($thread['tid']));

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

        $font_awesome = '';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            eval('$font_awesome .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
        }

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

    $customrep->set_url(get_thread_link($announcement['tid']));

    // Now we build the reputation bit
    ougc_customrep_parse_postbit($announcement);
}

// Postbit
function ougc_customrep_postbit(&$post)
{
    global $fid, $customrep, $tid, $templates, $thread;

    if ($customrep->firstpost_only && $post['pid'] != $thread['firstpost']) {
        return;
    }

    if ($customrep->firstpost_only) {
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

        $font_awesome = '';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            eval('$font_awesome .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
        }

        $xthreads_variables = '';
        if ($mybb->settings['ougc_customrep_xthreads_hide']) {
            $xt_fields = explode(',', str_replace('_require', '', $mybb->settings['ougc_customrep_xthreads_hide']));
            foreach ($xt_fields as $xt_field) {
                $xthreads_variables .= eval($templates->render('ougccustomrep_headerinclude_xthreads', true, false));
            }
        }

        eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');

        global $db, $thread;
        $customrep->cache['query'] = [];

        if ($customrep->firstpost_only) {
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

// Display user stats in profiles.
function ougc_customrep_member_profile_end()
{
    global $db, $customrep, $mybb, $templates, $memprofile, $lang, $theme, $footer;

    if (!$mybb->settings['ougc_customrep_stats_profile']) {
        return;
    }

    $font_awesome = '';
    if ($mybb->settings['ougc_customrep_fontawesome']) {
        eval('$font_awesome .= "' . $templates->get('ougccustomrep_headerinclude_fa') . '";');
    }

    eval('$footer .= "' . $templates->get('ougccustomrep_headerinclude') . '";');

    $customrep->lang_load();

    $where = [];

    // get forums user cannot view
    $unviewable = get_unviewable_forums(true);
    if ($unviewable) {
        $where[] = "t.fid NOT IN ($unviewable)";
        $where[] = "t.fid NOT IN ($unviewable)";
    }

    // get inactive forums
    $inactive = get_inactive_forums();
    if ($inactive) {
        $where[] .= "t.fid NOT IN ($inactive)";
        $where[] .= "t.fid NOT IN ($inactive)";
    }

    $where[] = "t.visible='1' AND t.closed NOT LIKE 'moved|%' AND p.visible='1'";

    $reps = (array)$mybb->cache->read('ougc_customrep');

    $where[] = "l.rid IN ('" . implode("','", array_keys($reps)) . "')";

    $memprofile['uid'] = (int)$memprofile['uid'];

    $where['q'] = "l.uid='{$memprofile['uid']}'";

    $stats_given = $stats_given ?? [];

    $stats_received = $stats_received ?? [];

    $query = $db->simple_select(
        'ougc_customrep_log l LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid=l.pid) LEFT JOIN ' . TABLE_PREFIX . 'threads t ON (t.tid=p.tid)',
        'l.rid',
        implode(' AND ', $where)
    );
    while ($rid = $db->fetch_field($query, 'rid')) {
        ++$stats_given[$rid];
    }

    $rates_received = $rates_given = $reputations = '';

    $where['q'] = "p.uid='{$memprofile['uid']}'";

    $tmplt_img = 'ougccustomrep_rep_img';
    if ($mybb->settings['ougc_customrep_fontawesome']) {
        $tmplt_img = 'ougccustomrep_rep_img_fa';
    }

    $query = $db->simple_select(
        'ougc_customrep_log l LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid=l.pid) LEFT JOIN ' . TABLE_PREFIX . 'threads t ON (t.tid=p.tid)',
        'l.rid',
        implode(' AND ', $where)
    );
    while ($rid = $db->fetch_field($query, 'rid')) {
        ++$stats_received[$rid];
    }
    foreach ($reps as $rid => &$reputation) {
        if (!isset($stats_received[$rid]) || empty($stats_received[$rid])) {
            continue;
        }

        $trow = alt_trow();

        $reputation['name'] = $lang_val = htmlspecialchars_uni($reputation['name']);

        $number = my_number_format($stats_received[$rid]);
        eval('$number = "' . $templates->get('ougccustomrep_profile_number') . '";');

        $reputation['image'] = $customrep->get_image($reputation['image'], $rid);

        eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');

        eval('$reputations .= "' . $templates->get('ougccustomrep_rep') . '";');
    }

    if (!$reputations) {
        eval('$rates_received = "' . $templates->get('ougccustomrep_profile_empty') . '";');
    } else {
        eval('$rates_received = "' . $templates->get('ougccustomrep_profile_row') . '";');
    }

    $reputations = '';

    foreach ($reps as $rid => &$reputation) {
        if (!isset($stats_given[$rid]) || empty($stats_given[$rid])) {
            continue;
        }

        $trow = alt_trow();

        $reputation['name'] = $lang_val = htmlspecialchars_uni($reputation['name']);

        $number = my_number_format($stats_given[$rid]);
        eval('$number = "' . $templates->get('ougccustomrep_profile_number') . '";');

        $reputation['image'] = $customrep->get_image($reputation['image'], $rid);

        eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');

        eval('$reputations .= "' . $templates->get('ougccustomrep_rep') . '";');
    }

    if (!$reputations) {
        eval('$rates_given = "' . $templates->get('ougccustomrep_profile_empty') . '";');
    } else {
        eval('$rates_given = "' . $templates->get('ougccustomrep_profile_row') . '";');
    }

    $lang->ougc_customrep_profile_stats = $lang->sprintf($lang->ougc_customrep_profile_stats, $memprofile['username']);

    eval('$memprofile[\'customrep\'] = "' . $templates->get('ougccustomrep_profile') . '";');
}

// Delete logs when deleting a thread
function ougc_customrep_delete_thread(&$tid)
{
    global $db;

    $pids = [];

    // First we get a list of all posts in this thread, we need this to later get a list of logs
    $query = $db->simple_select('posts', 'pid', 'tid=\'' . (int)$tid . '\'');
    while ($pid = $db->fetch_field($query, 'pid')) {
        $pids[] = (int)$pid;
    }

    if ($pids) {
        global $customrep;

        // get log ids and delete them all, this may take some time
        $query = $db->simple_select('ougc_customrep_log', 'lid', 'pid IN (\'' . implode('\',\'', $pids) . '\')');
        while ($lid = $db->fetch_field($query, 'lid')) {
            $customrep->delete_log($lid);
        }
    }
}

// Delete logs upon post deletion
function ougc_customrep_delete_post(&$pid)
{
    global $db;
    global $customrep;

    // get log ids and delete them all, this may take some time
    $query = $db->simple_select('ougc_customrep_log', 'lid', 'pid=\'' . (int)$pid . '\'');
    while ($lid = $db->fetch_field($query, 'lid')) {
        $customrep->delete_log($lid);
    }
}

// Merging post, what a pain!
function ougc_customrep_merge_posts(&$args)
{
    global $db, $customrep;
    $where = 'pid IN (\'' . implode('\',\'', array_map('intval', $args['pids'])) . '\')';

    // Now get the master PID, which MyBB doesn't offer..
    $masterpid = (int)$db->fetch_field(
        $db->simple_select('posts', 'pid', $where, ['limit' => 1, 'order_by' => 'dateline', 'order_dir' => 'asc']),
        'pid'
    );

    // First get all the logs attached to these posts
    $query = $db->simple_select('ougc_customrep_log', 'lid', $where);
    while ($lid = $db->fetch_field($query, 'lid')) {
        // Update this log
        $customrep->update_log($lid, ['pid' => $masterpid]);
    }
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
            $customrep->delete_log($reputation['lid']);

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

    $reputations = '';

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

    $customrep->lang_load();

    $post_url = get_post_link($customrep->post['pid'], $customrep->post['tid']);

    $input = [
        'pid' => $customrep->post['pid'],
        'my_post_key' => (isset($mybb->post_code) ? $mybb->post_code : generate_post_check()),
    ];

    $ajax_enabled = $mybb->settings['use_xmlhttprequest'] && $mybb->settings['ougc_customrep_enableajax'];

    if (!$ajax_enabled) {
        $lang->ougc_customrep_viewlatest = $lang->ougc_customrep_viewlatest_noajax;
    }

    foreach ($customrep->cache['_reps'] as $rid => $reputation) {
        if (!is_member($reputation['forums'], ['usergroup' => $customrep->post['fid'], 'additionalgroups' => '']
        )) {
            continue;
        }

        // $firstpost_only[$rid] stores the tid
        // $customrep->post['firstpost'] is only set on post bit, since portal and thread list posts are indeed the first post
        if (!empty($firstpost_only[$rid]) && !empty($customrep->post['firstpost']) && $customrep->post['firstpost'] != $customrep->post['pid']) {
            continue;
        }

        $reputation['name'] = htmlspecialchars_uni($reputation['name']);
        $input['action'] = 'customrep';
        $input['rid'] = $rid;
        $link = $customrep->build_url($input);
        $input['action'] = 'customreppu';
        $popupurl = $customrep->build_url($input);

        $number = 0;
        $classextra = '';
        if ($ajax_enabled) {
            $link = "javascript:OUGC_CustomReputation.Add('{$customrep->post['tid']}', '{$customrep->post['pid']}', '{$mybb->post_code}', '{$rid}', '0');";
            if ($customrep->allow_delete && $reputation['allowdeletion']) {
                $link_delete = "javascript:OUGC_CustomReputation.Add('{$customrep->post['tid']}', '{$customrep->post['pid']}', '{$mybb->post_code}', '{$rid}', '1');";
            }
        }

        // Count the votes for this reputation in this post
        if (isset($customrep->cache['query'][$rid][$customrep->post['pid']])) {
            $number = count($customrep->cache['query'][$rid][$customrep->post['pid']]);
        }

        $number = my_number_format($number);

        $voted_this = false;

        if (isset($voted_rids[$rid])) {
            $voted_this = true;
        }

        $voted_class = '';

        if ($voted_this) {
            $voted_class = 'voted';
        }

        eval('$number = "' . $templates->get('ougccustomrep_rep_number', 1, 0) . '";');

        $tmplt_img = 'ougccustomrep_rep_img';
        if ($mybb->settings['ougc_customrep_fontawesome']) {
            $tmplt_img = 'ougccustomrep_rep_img_fa';
        }

        $lang_val = '';

        $can_vote = is_member($reputation['groups']) && $customrep->post['uid'] != $mybb->user['uid'];

        if ($voted && $voted_this) {
            $can_vote = false;

            if ($customrep->allow_delete && $reputation['allowdeletion']) {
                $link = $ajax_enabled ? $link_delete : $link . '&amp;delete=1';

                $classextra = '_delete';
                $lang_val = $lang->sprintf($lang->ougc_customrep_delete, $reputation['name']);
                eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');
                eval('$image = "' . $templates->get('ougccustomrep_rep_voted', 1, 0) . '";');
            } else {
                $lang_val = $lang->sprintf($lang->ougc_customrep_voted, $reputation['name']);
                eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');
            }
        } elseif ($can_vote && ($reputation['inmultiple'] || !(isset($unique_rids[$rid]) && array_intersect(
                        $unique_rids,
                        $voted_rids
                    )))) {
            $lang_val = $lang->sprintf($lang->ougc_customrep_vote, $reputation['name']);
            eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');
            eval('$image = "' . $templates->get('ougccustomrep_rep_voted', 1, 0) . '";');
        } else {
            $lang_val = $reputation['name'];
            eval('$image = "' . $templates->get($tmplt_img, 1, 0) . '";');
        }
        /*
        {
            $lang_val = $lang->ougc_customrep_voted_undo;
            eval('$image = "'.$templates->get($tmplt_img, 1, 0).'";');
        }
        */

        $rep = eval($templates->render('ougccustomrep_rep', 1, 0));

        if (!empty($reputation['customvariable']) || $specific_rid !== null && (int)$specific_rid === (int)$rid) {
            $var['customrep_' . $rid] = $rep;
            /*if($specific_rid !== null)
            {
                break;
            }*/
        }

        if (empty($reputation['customvariable'])) {
            $reputations .= $rep;
        }
    }
    unset($rid, $reputation);

    /*if($specific_rid !== null)
    {
        return false;
    }*/

    $reputations = trim($reputations);

    // if $reputations is empty maybe return false?
    eval('$var[\'customrep\'] = "' . $templates->get('ougccustomrep') . '";');
}

// Plugin request
function ougc_customrep_request()
{
    global $customrep, $mybb, $tid, $templates, $thread, $fid, $lang;

    $customrep->lang_load();

    $ajax_enabled = $mybb->settings['use_xmlhttprequest'] && $mybb->settings['ougc_customrep_enableajax'];

    $customrep->set_url(get_thread_link($tid)); //TODO

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

    if (!($reputation = $customrep->get_rep($rid))) {
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

        if (($customrep->firstpost_only || $reputation['firstpost']) && $customrep->post['pid'] != $thread['firstpost']) {
            ougc_customrep_modal_error($lang->ougc_customrep_error_invlidadpost); // somehow
        }

        global $db, $templates, $theme, $headerinclude, $parser;
        if (!is_object($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';
            $parser = new postParser();
        }

        $popupurl = $customrep->build_url([
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

        $customrep->set_url(get_post_link($customrep->post['pid'], $customrep->post['tid']));
        $multipage_url = $customrep->build_url(false, ['page', 'pid', 'tid']);
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
        while ($log = $db->fetch_array($query)) {
            $trow = alt_trow();

            $log['username'] = htmlspecialchars_uni($log['username']);
            $log['username_f'] = format_name($log['username'], $log['usergroup'], $log['displaygroup']);
            $log['profilelink'] = build_profile_link($log['username'], $log['uid'], '_blank');
            $log['profilelink_f'] = build_profile_link($log['username_f'], $log['uid'], '_blank');

            $log['date'] = my_date($mybb->settings['dateformat'], $log['dateline']);
            $log['time'] = my_date($mybb->settings['timeformat'], $log['dateline']);
            $date = $lang->sprintf($lang->ougc_customrep_popup_date, $log['date'], $log['time']);

            $log['avatar'] = format_avatar($log['avatar'], $log['avatardimensions']);

            eval('$content .= "' . $templates->get('ougccustomrep_misc_row') . '";');
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

    if ($reputation['groups'] == '' || ($reputation['groups'] != -1 && !$customrep->is_member($reputation['groups']))) {
        $error($lang->ougc_customrep_error_nopermission);
    }

    if ($customrep->post['tid'] != $thread['tid'] || ($customrep->firstpost_only || $reputation['firstpost']) && $customrep->post['pid'] != $thread['firstpost']) {
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

            $customrep->delete_log($log['lid']);
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

        $customrep->insert_log($reputation['rid'], $reputation['reptype'], !empty($points) ? $points : 0);
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
    $customrep->lang_load();

    if (!$title) {
        $title = $lang->ougc_customrep_error;
    }

    $content = eval($templates->render('ougccustomrep_misc_error'));

    ougc_customrep_modal($content, $title);
}

function ougc_customrep_modal($content = '', $title = '', $desc = '', $multipage = '')
{
    global $customrep, $lang, $templates, $theme;
    $customrep->lang_load();

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

    $customrep->lang_load();

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

        $this->firstpost_only = (bool)$mybb->settings['ougc_customrep_firstpost'];

        $this->allow_delete = (bool)$mybb->settings['ougc_customrep_delete'];

        $this->newpoints_installed = function_exists(
                'newpoints_addpoints'
            ) && $mybb->settings['newpoints_main_enabled'];

        $this->myalerts_installed = $mybb->settings['ougc_customrep_myalerts'] && class_exists(
                'MybbStuff_MyAlerts_AlertFormatterManager'
            );
    }

    // List of tables
    public function _db_tables()
    {
        $tables = [
            'ougc_customrep' => [
                'rid' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
                'name' => "varchar(100) NOT NULL DEFAULT ''",
                'image' => "varchar(255) NOT NULL DEFAULT ''",
                'groups' => 'text NOT NULL',
                'forums' => 'text NOT NULL',
                'disporder' => "smallint(5) NOT NULL DEFAULT '0'",
                'visible' => "smallint(1) NOT NULL DEFAULT '1'",
                'firstpost' => "smallint(1) NOT NULL DEFAULT '1'",
                'allowdeletion' => "smallint(1) NOT NULL DEFAULT '1'",
                'customvariable' => "smallint(1) NOT NULL DEFAULT '1'",
                'requireattach' => "smallint(1) NOT NULL DEFAULT '1'",
                'reptype' => "varchar(3) NOT NULL DEFAULT ''",
                'points' => "DECIMAL(16,2) NOT NULL default '0'",
                'ignorepoints' => "smallint(5) NOT NULL DEFAULT '0'",
                'inmultiple' => "smallint(5) NOT NULL DEFAULT '0'",
                'createCoreReputationType' => "smallint(5) NOT NULL DEFAULT '0'",
                'prymary_key' => 'rid'
            ],
            'ougc_customrep_log' => [
                'lid' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
                'pid' => "int NOT NULL DEFAULT '0'",
                'uid' => "int NOT NULL DEFAULT '0'",
                'rid' => "int NOT NULL DEFAULT '0'",
                'points' => "DECIMAL(16,2) NOT NULL default '0'",
                'coreReputationID' => "int NOT NULL DEFAULT '0'",
                'dateline' => "int(10) NOT NULL DEFAULT '0'",
                'prymary_key' => 'lid'
            ]
        ];

        return $tables;
    }

    // List of columns
    public function _db_columns()
    {
        $tables = [
            'reputation' => [
                'lid' => "int NOT NULL DEFAULT '0'",
                'ougcCustomReputationCreatedOnLogID' => "int NOT NULL DEFAULT '0'"
            ],
        ];

        return $tables;
    }

    // Verify DB tables
    public function _db_verify_tables()
    {
        global $db;

        $collation = $db->build_create_table_collation();
        foreach ($this->_db_tables() as $table => $fields) {
            if ($db->table_exists($table)) {
                foreach ($fields as $field => $definition) {
                    if ($field == 'prymary_key') {
                        continue;
                    }

                    if ($db->field_exists($field, $table)) {
                        $db->modify_column($table, "`{$field}`", $definition);
                    } else {
                        $db->add_column($table, $field, $definition);
                    }
                }
            } else {
                $query = 'CREATE TABLE IF NOT EXISTS `' . TABLE_PREFIX . "{$table}` (";
                foreach ($fields as $field => $definition) {
                    if ($field == 'prymary_key') {
                        $query .= "PRIMARY KEY (`{$definition}`)";
                    } else {
                        $query .= "`{$field}` {$definition},";
                    }
                }
                $query .= ") ENGINE=MyISAM{$collation};";
                $db->write_query($query);
            }
        }
    }

    // Verify DB columns
    public function _db_verify_columns()
    {
        global $db;

        foreach ($this->_db_columns() as $table => $columns) {
            foreach ($columns as $field => $definition) {
                if ($db->field_exists($field, $table)) {
                    $db->modify_column($table, "`{$field}`", $definition);
                } else {
                    $db->add_column($table, $field, $definition);
                }
            }
        }
    }

    // Verify DB indexes
    public function _db_verify_indexes()
    {
        global $db;

        if ($db->index_exists('ougc_customrep_log', 'piduid')) {
            $db->write_query('ALTER TABLE ' . TABLE_PREFIX . 'ougc_customrep_log DROP KEY piduid');
        }

        if (!$db->index_exists('ougc_customrep_log', 'piduidrid')) {
            $db->write_query(
                'ALTER TABLE ' . TABLE_PREFIX . 'ougc_customrep_log ADD UNIQUE KEY piduidrid (pid,rid,uid)'
            );
        }

        if (!$db->index_exists('ougc_customrep_log', 'pidrid')) {
            $db->write_query('CREATE INDEX pidrid ON ' . TABLE_PREFIX . 'ougc_customrep_log (pid,rid)');
        }
    }

    // Load our language file if neccessary
    public function lang_load()
    {
        loadLanguage();
    }

    // Set url
    public function set_url($url)
    {
        $this->url = urlHandlerSet($url);
    }

    // Check PL requirements
    public function meets_requirements()
    {
        global $PL;

        $info = ougc_customrep_info();

        if (!file_exists(PLUGINLIBRARY)) {
            global $lang;
            $this->lang_load();

            $this->message = $lang->sprintf($lang->ougc_customrep_plreq, $info['pl']['url'], $info['pl']['version']);
            return false;
        }

        $PL or require_once PLUGINLIBRARY;

        if ($PL->version < $info['pl']['version']) {
            global $lang;
            $this->lang_load();

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

        admin_redirect($this->build_url());
        exit;
    }

    // Build an url parameter
    public function build_url($urlappend = [], $fetch_input_url = false)
    {
        return urlHandlerBuild($urlappend, $fetch_input_url);
    }

    // Fetch current url inputs, for multipage mostly
    public function fetch_input_url($ignore = false)
    {
        $location = parse_url(get_current_location());
        while (my_strpos($location['query'], '&amp;')) {
            $location['query'] = html_entity_decode($location['query']);
        }
        $location = explode('&', $location['query']);

        if ($ignore !== false) {
            if (!is_array($ignore)) {
                $ignore = [$ignore];
            }
            foreach ($location as $key => $input) {
                $input = explode('=', $input);
                if (in_array($input[0], $ignore)) {
                    unset($location[$key]);
                }
            }
        }

        $url = [];
        foreach ($location as $input) {
            $input = explode('=', $input);
            $url[$input[0]] = $input[1];
        }

        return $url;
    }

    // Get the reputation icon
    public function get_image($image, $rid)
    {
        if (!isset($this->cache['images'][$rid])) {
            global $settings, $theme;
            $this->cache['images'][$rid] = false;

            $replaces = [
                '{bburl}' => $settings['bburl'],
                '{homeurl}' => $settings['homeurl'],
                '{imgdir}' => $theme['imgdir']
            ];

            $this->cache['images'][$rid] = str_replace(array_keys($replaces), array_values($replaces), $image);
        }

        return $this->cache['images'][$rid];
    }

    // Log admin action
    public function log_action()
    {
        if ($this->rid) {
            log_admin_action($this->rid);
        } else {
            log_admin_action();
        }
    }

    // Update the cache
    public function update_cache()
    {
        global $db, $cache;

        $cacheData = [];

        $dbQuery = $db->simple_select(
            'ougc_customrep',
            'rid, name, image, groups, forums, firstpost, allowdeletion, customvariable, requireattach, reptype, points, ignorepoints, inmultiple, createCoreReputationType',
            "visible='1'",
            ['order_by' => 'disporder']
        );

        while ($customReputationData = $db->fetch_array($dbQuery)) {
            $cacheData[(int)$customReputationData['rid']] = [
                'name' => $customReputationData['name'],
                'image' => $customReputationData['image'],
                'groups' => $customReputationData['groups'],
                'forums' => $customReputationData['forums'],
                'firstpost' => (int)$customReputationData['firstpost'],
                'allowdeletion' => (int)$customReputationData['allowdeletion'],
                'customvariable' => (int)$customReputationData['customvariable'],
                'requireattach' => (int)$customReputationData['requireattach'],
                'reptype' => (int)$customReputationData['reptype'],
                'points' => (float)$customReputationData['points'],
                'ignorepoints' => (int)$customReputationData['ignorepoints'],
                'inmultiple' => (int)$customReputationData['inmultiple'],
                'createCoreReputationType' => (int)$customReputationData['createCoreReputationType'],
            ];
        }

        $cache->update('ougc_customrep', $cacheData);

        return true;
    }

    // Clean a string/array and return it
    public function clean_array($array, $implode = true)
    {
        if (!is_array($array)) {
            $array = explode(',', $array);
        }

        $array = array_unique(array_map('intval', $array));

        if ($implode) {
            return implode(',', $array);
        }

        return $array;
    }

    // Insert a new custom reputation to the DB
    public function insert_rep($data = [], $update = false, $rid = 0)
    {
        global $db;

        $insert_data = [];

        if (isset($data['name'])) {
            $insert_data['name'] = $db->escape_string($data['name']);
        }

        if (isset($data['image'])) {
            $insert_data['image'] = $db->escape_string($data['image']);
        }

        if (isset($data['groups'])) {
            if (is_array($data['groups'])) {
                $data['groups'] = $this->clean_array($data['groups']);
            }

            $insert_data['groups'] = $db->escape_string($data['groups']);
        }

        if (isset($data['forums'])) {
            if (is_array($data['forums'])) {
                $data['forums'] = $this->clean_array($data['forums']);
            }

            $insert_data['forums'] = $db->escape_string($data['forums']);
        }

        if (isset($data['disporder'])) {
            $insert_data['disporder'] = (int)$data['disporder'];
        }

        if (isset($data['visible'])) {
            $insert_data['visible'] = (int)$data['visible'];
        }

        if (isset($data['firstpost'])) {
            $insert_data['firstpost'] = (int)$data['firstpost'];
        }

        if (isset($data['allowdeletion'])) {
            $insert_data['allowdeletion'] = (int)$data['allowdeletion'];
        }

        if (isset($data['customvariable'])) {
            $insert_data['customvariable'] = (int)$data['customvariable'];
        }

        if (isset($data['requireattach'])) {
            $insert_data['requireattach'] = (int)$data['requireattach'];
        }

        if (isset($data['points'])) {
            $insert_data['points'] = (int)$data['points'];
        }

        if (isset($data['ignorepoints'])) {
            $insert_data['ignorepoints'] = (int)$data['ignorepoints'];
        }

        if (isset($data['inmultiple'])) {
            $insert_data['inmultiple'] = (int)$data['inmultiple'];
        }

        if (isset($data['createCoreReputationType'])) {
            $insert_data['createCoreReputationType'] = (int)$data['createCoreReputationType'];
        }

        $insert_data['reptype'] = '';
        if ($data['reptype'] != '') {
            $insert_data['reptype'] = (int)$data['reptype'];
        }

        if ($insert_data) {
            global $plugins;

            if ($update) {
                $this->rid = (int)$rid;
                $db->update_query('ougc_customrep', $insert_data, 'rid=\'' . $this->rid . '\'');

                $plugins->run_hooks('ouc_customrep_update_rep', $this);
            } else {
                $this->rid = (int)$db->insert_query('ougc_customrep', $insert_data);

                $plugins->run_hooks('ouc_customrep_insert_rep', $this);
            }
        }
    }

    // Update espesific custom reputation
    public function update_rep($data = [], $rid = 0)
    {
        $this->insert_rep($data, true, $rid);
    }

    // Set reputation data
    public function set_rep_data($rid = null)
    {
        if (isset($rid) && ($reputation = $this->get_rep($rid))) {
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
            foreach ((array)$mybb->input as $key => $value) {
                if (isset($this->rep_data[$key])) {
                    $this->rep_data[$key] = $value;
                    if ($key == 'groups' || $key == 'forums') {
                        $this->rep_data[$key] = $this->clean_array($this->rep_data[$key]);
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

    // Get a custom reputation from the DB
    public function get_rep($rid = 0)
    {
        $rid = (int)$rid;
        if (!isset($this->cache['reps'][$rid])) {
            $this->cache['reps'][$rid] = false;

            global $db;

            $query = $db->simple_select('ougc_customrep', '*', 'rid=\'' . $rid . '\'');
            $reputation = $db->fetch_array($query);

            if (isset($reputation['rid'])) {
                $this->cache['reps'][$rid] = $reputation;
            }
        }

        return $this->cache['reps'][$rid];
    }

    // Get a log from the DB
    public function get_log($lid = 0)
    {
        $lid = (int)$lid;
        if (!isset($this->cache['logs'][$lid])) {
            $this->cache['logs'][$lid] = false;

            global $db;

            $query = $db->simple_select('ougc_customrep_log', '*', 'lid=\'' . $lid . '\'');
            $log = $db->fetch_array($query);

            if (isset($log['lid'])) {
                $this->cache['logs'][$lid] = $log;
            }
        }

        return $this->cache['logs'][$lid];
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
                        $this->clean_array($rep['forums'], false)
                    ))) {
                unset($reps[$rid]);
                continue;
            }

            if (($name = $this->get_name($rid))) {
                $rep['name'] = $name;
            }

            $rep['name'] = htmlspecialchars_uni($rep['name']);
            $rep['image'] = $this->get_image($rep['image'], $rid);
            $rep['groups'] = $this->clean_array($rep['groups']);

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

    // We want multi-lang support (this doesn't work for ACP, to avoud confussions)
    public function get_name($rid)
    {
        global $lang;
        $this->lang_load();

        $lang_val = 'ougc_customrep_name_' . (int)$rid;

        if (!empty($lang->$lang_val)) {
            return $lang->$lang_val;
        }

        return false;
    }

    // is_member custom method..
    public function is_member($groups, $empty = true)
    {
        if (!$groups && $empty) {
            return true;
        }

        global $PL;
        $PL or require_once PLUGINLIBRARY;

        return (bool)$PL->is_member($groups);
    }

    // Delete a complete custom reputation and any possible data related to it
    public function delete_rep($rid)
    {
        global $db, $plugins;

        $args = [
            'this' => &$this,
            'rid' => $rid,
            'logs' => []
        ];

        // Delete all logs.
        $query = $db->simple_select('ougc_customrep_log', 'lid', 'rid=\'' . (int)$rid . '\'');
        while ($lid = $db->fetch_field($query, 'lid')) {
            $args['logs'][$lid] = 1;
            $this->delete_log($lid);
        }

        // Now delete this custom reputation.
        $db->delete_query('ougc_customrep', 'rid=\'' . (int)$rid . '\'');

        $plugins->run_hooks('ouc_customrep_delete_rep', $args);

        return true;
    }

    // Delete a reputation log. This may take up some time.
    public function delete_log($lid)
    {
        global $mybb;
        global $db, $plugins;

        $args = [
            'this' => &$this,
            'lid' => $lid,
            'uids' => [],
            'rids' => []
        ];

        $query = $db->simple_select('reputation', 'rid, uid, pid', 'lid=\'' . (int)$lid . '\'');
        while ($rep = $db->fetch_array($query)) {
            $args['uids'][(int)$rep['uid']] = 1;
            $args['rids'][(int)$rep['rid']] = 1;

            // Delete reputation
            $db->delete_query('reputation', 'rid=\'' . (int)$rep['rid'] . '\'');

            // Recount the reputation of this user - keep it in sync.
            $this->sync_reputation($rep['uid']);

            // MyAlerts compatibility
            if ($rep['rid'] && isset($Alerts) && is_object($Alerts) && method_exists($Alerts, 'addAlert')) {
                $db->delete_query(
                    'alerts',
                    'uid =\'' . (int)$rep['uid'] . '\' AND from_id=\'' . (int)$mybb->user['uid'] . '\' AND alert_type=\'rep\' AND from_id=\'' . (int)$rep['pid'] . '\''
                );
            }
        }

        $plugins->run_hooks('ouc_customrep_delete_log', $args);

        // Now delete this log.
        /*$query = $db->simple_select('ougc_customrep_log', 'pid', 'lid=\''.(int)$lid.'\'');
        while($pid = $db->fetch_array($query, 'pid'))
        {
            $db->delete_query('ougc_customrep_log', 'pid=\''.(int)$pid.'\' AND uid=\''.(int)$mybb->user['uid'].'\'');
        }*/
        $db->delete_query('ougc_customrep_log', 'lid=\'' . (int)$lid . '\'');
    }

    // Insert a log into the DB
    public function insert_log($rid, $reptype = '', $points = 0, int $coreReputationID = 0) // default = disabled
    {
        if (!isset($rid) || !isset($this->post['pid'])) {
            die('Invalid log insertion attempt.');
        }

        global $db, $mybb, $plugins;

        $lid = (int)$db->insert_query('ougc_customrep_log', [
            'pid' => (int)$this->post['pid'],
            'uid' => (int)$mybb->user['uid'],
            'rid' => (int)$rid,
            'points' => (float)$points,
            'dateline' => TIME_NOW,
            'coreReputationID' => $coreReputationID,
        ]);

        $args = [
            'this' => &$this,
            'reptype' => &$reptype,
            'lid' => $lid
        ];

        // MyAlerts compatibility
        !$this->myalerts_installed || $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        $plugins->run_hooks('ouc_customrep_insert_log', $args);

        $reptype = (int)$reptype;

        if ($reptype !== 0) {
            global $Alerts;

            $rip = (int)$db->insert_query('reputation', [
                'pid' => (int)$this->post['pid'],
                'uid' => (int)$this->post['uid'],
                'adduid' => (int)$mybb->user['uid'],
                'reputation' => $reptype,
                'comments' => '',
                'lid' => $lid,
                'dateline' => TIME_NOW
            ]);

            if ($rip && $this->myalerts_installed) {
                $alertType = $alertTypeManager->getByCode('rep');

                if ($alertType != null && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert($this->post['uid'], $alertType, 0);

                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            if ($reptype != 0) // we don't add neutral reputations, so don't sync
            {
                // Recount the reputation of this user - keep it in sync.
                $this->sync_reputation($this->post['uid']);
            }
        }

        if ($lid && $this->myalerts_installed) {
            $alertType = $alertTypeManager->getByCode('ougc_customrep');

            if ($alertType != null && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert($this->post['uid'], $alertType, $lid);
                $alert->setExtraDetails([
                    'pid' => $this->post['pid'],
                    'tid' => $this->post['tid'],
                    'fid' => $this->post['fid'],
                    'rid' => $rid,
                ]);

                $alsert = MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
        }

        return $lid;
    }

    // Update a log
    public function update_log($lid, $data = [])
    {
        global $db;
        $lid = (int)$lid;

        $update_data = [];
        if (isset($data['pid'])) {
            $update_data['pid'] = (int)$data['pid'];
        }
        if (isset($data['uid'])) {
            $update_data['uid'] = (int)$data['uid'];
        }
        if (isset($data['rid'])) {
            $update_data['rid'] = (int)$data['rid'];
        }
        if (isset($data['dateline'])) {
            $update_data['dateline'] = (int)$data['dateline'];
        }

        if ($update_data) {
            $db->update_query('ougc_customrep_log', $update_data, 'lid=\'' . $lid . '\'');

            // Since we are updating the pid, we need to update any user reputation as well
            if (isset($update_data['pid'])) {
                $query = $db->simple_select('reputation', 'rid, uid', 'lid=\'' . (int)$lid . '\'');
                while ($rep = $db->fetch_array($query)) {
                    // Actually update reputation
                    $db->update_query('reputation', [
                        'pid' => $update_data['pid'],
                    ], 'rid=\'' . (int)$rep['rid'] . '\'');

                    // Recount the reputation of this user - keep it in sync.
                    $this->sync_reputation($rep['uid']);
                }
            }
            return true;
        }
        return false;
    }

    // Recount the reputation of this user - keep it in sync.
    public function sync_reputation($uid)
    {
        global $db;
        $uid = (int)$uid;

        $query = $db->simple_select('reputation', 'SUM(reputation) AS reputation_count', 'uid=\'' . $uid . '\'');
        $reputation_count = (int)$db->fetch_field($query, 'reputation_count');

        $db->update_query('users', ['reputation' => $reputation_count], 'uid=\'' . $uid . '\'');
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

if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
    /**
     * Alert formatter for my custom alert type.
     */
    class OUGC_CustomRep_AlertFormmatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
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
            global $cache, $customrep;

            $reps = (array)$cache->read('ougc_customrep');

            $alertContent = $alert->getExtraDetails();

            $rid = (int)$alertContent['rid'];

            if (empty($rid)) {
                $log = $customrep->get_log($alert->getObjectId());

                if (!empty($log['rid'])) {
                    $rid = (int)$log['rid'];
                }
            }

            if (!empty($reps[$rid]) && !empty($reps[$rid]['name'])) {
                return $this->lang->sprintf(
                    $this->lang->ougc_customrep_myalerts_alert,
                    $outputAlert['from_user'],
                    htmlspecialchars_uni($reps[$rid]['name'])
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
            global $customrep;

            $customrep->lang_load();
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
            global $settings;

            $alertContent = $alert->getExtraDetails();

            $post = get_post($alertContent['pid']);

            if (!empty($post['pid'])) {
                return $settings['bburl'] . '/' . get_post_link(
                        $post['pid'],
                        (int)$alertContent['tid']
                    ) . '#pid' . $post['pid'];
            }

            $thread = get_thread($alertContent['tid']);

            if (!empty($thread['tid'])) {
                return $settings['bburl'] . '/' . get_thread_link($thread['tid']);
            }

            return get_profile_link($alert->getFromUserId());
        }
    }
}
