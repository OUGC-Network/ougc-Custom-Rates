<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/languages/english/admin/config_ougc_customrep.lang.php)
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

$l['ougc_customrep'] = 'ougc Custom Rates';
$l['ougc_customrep_d'] = 'Create custom rates for users to use in posts.';

// PluginLibrary
$l['ougc_customrep_plreq'] = 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later to be uploaded to your forum.';
$l['ougc_customrep_plold'] = 'This plugin requires PluginLibrary version {1} or later, whereas your current version is {2}. Please do update <a href="{3}">PluginLibrary</a>.';

// Messages
$l['ougc_customrep_message_invalidrep'] = 'The selected custom rate is invalid.';
$l['ougc_customrep_message_deleterep'] = 'The selected custom rate was successfully deleted.';
$l['ougc_customrep_message_empty'] = 'There are not currently existing custom rates.';
$l['ougc_customrep_message_addrep'] = 'The custom rate was successfully added.';
$l['ougc_customrep_message_editrep'] = 'The custom rate was successfully edited.';

// Header titles
$l['ougc_customrep_h_image'] = 'Image';
$l['ougc_customrep_h_image_d'] = 'Small image to identify this custom rate.<br/><span class="smalltext">&nbsp;&nbsp;{bburl} -> Forum URL<br />
&nbsp;&nbsp;{homeurl} -> Home URL<br />
&nbsp;&nbsp;{imgdir} -> Theme Directory URL
</span>';
$l['ougc_customrep_h_name'] = 'Name';
$l['ougc_customrep_h_name_d'] = 'Short key name to identify this custom rate (ie: Thank You).';
$l['ougc_customrep_h_order'] = 'Order';
$l['ougc_customrep_h_visible'] = 'Visible';

// Permissions page
$l['ougc_customrep_perm'] = 'Can manage custom rates?';

// Tabs
$l['ougc_customrep_tab_view'] = 'View All';
$l['ougc_customrep_tab_view_d'] = 'List of existing ratings.';
$l['ougc_customrep_tab_add'] = 'Add';
$l['ougc_customrep_tab_add_d'] = 'Add a new custom rate.';
$l['ougc_customrep_tab_edit'] = 'Edit';
$l['ougc_customrep_tab_edit_d'] = 'Edit an existing rating.';

// Buttons
$l['ougc_customrep_button_disponder'] = 'Update Order';
$l['ougc_customrep_button_submit'] = 'Submit';

// Form
$l['ougc_customrep_f_groups'] = 'Groups';
$l['ougc_customrep_f_groups_d'] = 'Select the groups that can use this custom rate.';
$l['ougc_customrep_f_forums'] = 'Forums';
$l['ougc_customrep_f_forums_d'] = 'Select the forums where this custom rate can used in.';
$l['ougc_customrep_f_disporder_d'] = 'Order on which this custom rate will be processed.';
$l['ougc_customrep_f_visible_d'] = 'Whether if to enable or disable this custom rate.';
$l['ougc_customrep_h_firstpost'] = 'First Post Only';
$l['ougc_customrep_h_firstpost_d'] = 'Whether if enable this rating only for the first post of a thread.';
$l['ougc_customrep_h_allowdeletion'] = 'Allow Deletion';
$l['ougc_customrep_h_allowdeletion_d'] = 'Allow users to undo their rate.';
$l['ougc_customrep_h_customvariable'] = 'Output in Custom Variable';
$l['ougc_customrep_h_customvariable_d'] = 'Disable this rate from the global variables. You will need to add <code>{$post[\'customrep_{1}\']}</code> in your <code>postbit</code>, and <code>postbit_classic</code> templates, <code>{$thread[\'customrep_{1}\']}</code> in your <code>forumdisplay_thread</code> template, and <code>{$announcement[\'customrep_{1}\']}</code> in your <code>portal_announcement</code> template.';
$l['ougc_customrep_h_requireattach'] = 'Require to Download Attachments';
$l['ougc_customrep_h_requireattach_d'] = 'Enable this feature to require users to rate a post before downloading attachments.';
$l['ougc_customrep_h_reptype'] = 'Reputation Level';
$l['ougc_customrep_h_reptype_d'] = 'How does this custom rate affect users\'s reputation. Empty to disable.';
$l['ougc_customrep_h_points'] = 'Newpoints Points Cost';
$l['ougc_customrep_h_points_d'] = 'Please note that the post author receives the points and points are reverted if the rating is deleted.';
$l['ougc_customrep_h_ignorepoints'] = 'Hide Post On Count';
$l['ougc_customrep_h_ignorepoints_d'] = 'Posts can be hidden by default if they reach an amount of rates. Please insert the amount of rates needed for posts to be hidden by default.';
$l['ougc_customrep_h_inmultiple'] = 'Allow in Multiple';
$l['ougc_customrep_h_inmultiple_d'] = 'If the <code>Multiple Rating Global Switch</code> setting and this setting are both active, rates will be split into two categories:<br />
Unique category: All rates with this setting off. Only one of these rates can be used per post.<br />
Multiple category: All rates with this setting on. Any and all rates can be used at the same time per post.';
$l['ougc_customrep_h_createCoreReputationType'] = 'Create On Core Reputation';
$l['ougc_customrep_h_createCoreReputationType_d'] = 'You can select to create a custom rate log when users use the core reputation system on posts.';
$l['ougc_customrep_h_createCoreReputationTypePositive'] = 'Positive';
$l['ougc_customrep_h_createCoreReputationTypeNeutral'] = 'Neutral';
$l['ougc_customrep_h_createCoreReputationTypeNegative'] = 'Negative';

// Validation
$l['ougc_customrep_error_invalidname'] = 'Invalid name.';
$l['ougc_customrep_error_invalidimage'] = 'Invalid image.';
$l['ougc_customrep_error_invaliddisporder'] = 'Invalid display order.';
$l['ougc_customrep_error_invalidreptype'] = 'Invalid reputation level.';

// Settings
$l['setting_group_ougcCustomReputation'] = 'Custom Rates';
$l['setting_group_ougcCustomReputation_desc'] = 'Create custom rates for users to use in posts.';
$l['setting_ougc_customrep_firstpost'] = 'First Post Only Global Switch';
$l['setting_ougc_customrep_firstpost_desc'] = 'Whether if enable this feature only for the first post of a thread. Turn off to manage on a per rate basis.';
$l['setting_ougc_customrep_delete'] = 'Allow Deletion Global Switch';
$l['setting_ougc_customrep_delete_desc'] = 'Allow deletion of ratings. Turn on to manage on a per rate basis.';
$l['setting_ougc_customrep_perpage'] = 'Multipage Per Page';
$l['setting_ougc_customrep_perpage_desc'] = 'Maximum number of options to show per page.';
$l['setting_ougc_customrep_fontawesome'] = 'Use Font Awesome Icons';
$l['setting_ougc_customrep_fontawesome_desc'] = 'Activate this setting if you want to use font awesome icons instead of images.';
$l['setting_ougc_customrep_fontawesome_acp'] = 'Font Awesome ACP Code';
$l['setting_ougc_customrep_fontawesome_acp_desc'] = 'Insert the ACP code to load if using Font Awesome icons.';
$l['setting_ougc_customrep_threadlist'] = 'Display On Thread Listing';
$l['setting_ougc_customrep_threadlist_desc'] = 'Select the forums where you want to display ratings within the forum thread list.';
$l['setting_ougc_customrep_portal'] = 'Display On Portal Announcements';
$l['setting_ougc_customrep_portal_desc'] = 'Select the forums where threads need to be from to display its custom rates box within the portal announcements listing.';
$l['setting_ougc_customrep_xthreads_hide'] = 'Active xThreads Hide Feature';
$l['setting_ougc_customrep_xthreads_hide_desc'] = 'Select which xThreads fields this feature should hijack to control display status. <a href="https://ougc.network/module?faqs&filtertf_plugins_code=ougc_customrep">Please read the documentation for this feature.<a/>';
$l['setting_ougc_customrep_stats_profile'] = 'Display Users Stats in Profiles';
$l['setting_ougc_customrep_stats_profile_desc'] = 'Enable this setting to display user stats within profiles.';
$l['setting_ougc_customrep_enableajax'] = 'Enable Ajax Features';
$l['setting_ougc_customrep_enableajax_desc'] = 'Enable Ajax features. Please note that the "Enable XMLHttp request features?" setting under the "Server and Optimization Options" settings group needs to be turned on ("Yes") for Ajax featues to work.';
$l['setting_ougc_customrep_guests_popup'] = 'Allow Guests to View Popup';
$l['setting_ougc_customrep_guests_popup_desc'] = 'Enable this setting if you want to allow guests viewing rate detail modals.';
$l['setting_ougc_customrep_multiple'] = 'Multiple Rating Global Switch';
$l['setting_ougc_customrep_multiple_desc'] = 'Enable this setting to allow users to rate post multiple times (using different ratings).';

$l['setting_ougc_xthreads_information'] = '<span style="color: gray;">To be able to use this feature you need to install <a href="http://mybbhacks.zingaburga.com/showthread.php?tid=288">xThreads</a> and create some fields according to the documentation.</span>';

