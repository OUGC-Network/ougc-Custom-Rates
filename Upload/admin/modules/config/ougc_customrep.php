<?php

/***************************************************************************
 *
 *    ougc Custom Reputation plugin (/inc/plugins/ougc_customrep.php)
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

use function ougc\CustomReputation\Core\cacheUpdate;
use function ougc\CustomReputation\Core\loadLanguage;
use function ougc\CustomReputation\Core\logAdminAction;
use function ougc\CustomReputation\Core\rateDelete;
use function ougc\CustomReputation\Core\rateGet;
use function ougc\CustomReputation\Core\rateGetImage;
use function ougc\CustomReputation\Core\rateInsert;
use function ougc\CustomReputation\Core\rateUpdate;
use function ougc\CustomReputation\Core\urlHandlerBuild;
use function ougc\CustomReputation\Core\urlHandlerSet;

use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEGATIVE;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_NEUTRAL;
use const ougc\CustomReputation\Core\CORE_REPUTATION_TYPE_POSITIVE;

defined('IN_MYBB') || die('Direct initialization of this file is not allowed.');

global $mybb, $db, $lang;
global $page;

urlHandlerSet('index.php?module=config-ougc_customrep');

loadLanguage();

// Page tabs
$sub_tabs['ougc_customrep_view'] = [
    'title' => $lang->ougc_customrep_tab_view,
    'link' => urlHandlerBuild(),
    'description' => $lang->ougc_customrep_tab_view_d
];

$sub_tabs['ougc_customrep_add'] = [
    'title' => $lang->ougc_customrep_tab_add,
    'link' => urlHandlerBuild(['action' => 'add']),
    'description' => $lang->ougc_customrep_tab_add_d
];

if ($mybb->get_input('action') == 'edit') {
    $sub_tabs['ougc_customrep_edit'] = [
        'title' => $lang->ougc_customrep_tab_edit,
        'link' => urlHandlerBuild([
            'action' => 'edit',
            'rid' => $mybb->get_input('rid', 1)
        ]),
        'description' => $lang->ougc_customrep_tab_edit_d
    ];
}

$page->add_breadcrumb_item($lang->ougc_customrep, $sub_tabs['ougc_customrep_view']['link']);

if ($mybb->get_input('action') == 'add' || $mybb->get_input('action') == 'edit') {
    $isAddRatePage = $mybb->get_input('action') === 'add';

    $rateInputData = [
        'name' => '',
        'image' => '',
        'groups' => [],
        'forums' => [],
        'disporder' => 0,
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

    if ($isAddRatePage) {
        global $db;

        $query = $db->simple_select('ougc_customrep', 'MAX(disporder) as max_disporder');

        $rateInputData['disporder'] = (int)$db->fetch_field($query, 'max_disporder') + 1;

        $page->add_breadcrumb_item($sub_tabs['ougc_customrep_add']['title'], $sub_tabs['ougc_customrep_add']['link']);

        $page->output_header($lang->ougc_customrep_tab_add);

        $page->output_nav_tabs($sub_tabs, 'ougc_customrep_add');
    } else {
        if (!($rateData = rateGet($mybb->get_input('rid', 1)))) {
            \ougc\CustomReputation\Admin\admin_redirect($lang->ougc_customrep_message_invalidrep, true);
        }

        $rateInputData = array_merge($rateInputData, $rateData);

        if (!is_array($rateInputData['groups'])) {
            $rateInputData['groups'] = explode(',', $rateInputData['groups']);
        }

        if (!is_array($rateInputData['forums'])) {
            $rateInputData['forums'] = explode(',', $rateInputData['forums']);
        }

        $page->add_breadcrumb_item($sub_tabs['ougc_customrep_edit']['title'], $sub_tabs['ougc_customrep_edit']['link']);

        $page->output_header($lang->ougc_customrep_tab_edit);

        $page->output_nav_tabs($sub_tabs, 'ougc_customrep_edit');
    }

    if ($mybb->request_method == 'post') {
        foreach ($mybb->input as $key => $value) {
            if (isset($rateInputData[$key])) {
                $rateInputData[$key] = $value;

                if ($key == 'groups' || $key == 'forums') {
                    $rateInputData[$key] = implode(',', $rateInputData[$key]);
                }
            }
        }
    }

    foreach (['groups', 'forums'] as $key) {
        if (!isset($mybb->input[$key]) && isset($rateData[$key])) {
            $mybb->input[$key] = $rateData[$key];
        } else {
            $mybb->input[$key] = '';
        }
    }

    $group_checked = ['all' => '', 'custom' => '', 'none' => ''];
    if ($mybb->get_input('groups_type') == 'all' || !$mybb->get_input('groups_type') && (int)$mybb->get_input(
            'groups'
        ) === -1) {
        $mybb->input['groups_type'] = 'all';
        $mybb->input['groups'] = -1;
        $group_checked['all'] = 'checked="checked"';
    } elseif ($mybb->get_input('groups_type') == 'none' || !$mybb->get_input('groups_type') && $mybb->get_input(
            'groups'
        ) === '') {
        $mybb->input['groups_type'] = 'none';
        $mybb->input['groups'] = '';
        $group_checked['none'] = 'checked="checked"';
    } else {
        $mybb->input['groups_type'] = 'custom';
        $mybb->input['groups'] = array_unique(
            array_map(
                'intval',
                !is_array($mybb->input['groups']) ? explode(',', $mybb->input['groups']) : $mybb->input['groups']
            )
        );
        $group_checked['custom'] = 'checked="checked"';
    }

    $forum_checked = ['all' => '', 'custom' => '', 'none' => ''];
    if ($mybb->get_input('forums_type') == 'all' || !$mybb->get_input('forums_type') && (int)$mybb->get_input(
            'forums'
        ) === -1) {
        $mybb->input['forums_type'] = 'all';
        $mybb->input['forums'] = -1;
        $forum_checked['all'] = 'checked="checked"';
    } elseif ($mybb->get_input('forums_type') == 'none' || !$mybb->get_input('forums_type') && $mybb->get_input(
            'forums'
        ) === '') {
        $mybb->input['forums_type'] = 'none';
        $mybb->input['forums'] = '';
        $forum_checked['none'] = 'checked="checked"';
    } else {
        $mybb->input['forums_type'] = 'custom';
        $mybb->input['forums'] = array_unique(
            array_map(
                'intval',
                !is_array($mybb->input['forums']) ? explode(',', $mybb->input['forums']) : $mybb->input['forums']
            )
        );
        $forum_checked['custom'] = 'checked="checked"';
    }

    $errors = [];

    if ($mybb->request_method == 'post') {
        $name = strlen(trim($mybb->get_input('name')));

        if ($name < 1 || $name > 100) {
            $errors[] = $lang->ougc_customrep_error_invalidname;
        }

        $image = strlen(trim($mybb->get_input('image')));

        if ($image < 1 || $image > 255) {
            $errors[] = $lang->ougc_customrep_error_invalidimage;
        }

        if ($mybb->get_input('disporder', MyBB::INPUT_INT) < 0) {
            $errors[] = $lang->ougc_customrep_error_invaliddisporder;
        }

        if ($mybb->get_input('reptype', MyBB::INPUT_INT) && my_strlen(
                $mybb->get_input('reptype', MyBB::INPUT_INT)
            ) > 3) {
            $errors[] = $lang->ougc_customrep_error_invalidreptype;
        }

        if (empty($errors)) {
            $rateInputData['groups'] = $mybb->input['groups'];

            $rateInputData['forums'] = $mybb->input['forums'];

            if ($isAddRatePage) {
                rateInsert($rateInputData);

                $lang_var = 'ougc_customrep_message_addrep';
            } else {
                rateUpdate($rateInputData, (int)$rateData['rid']);

                $lang_var = 'ougc_customrep_message_editrep';
            }

            cacheUpdate();

            logAdminAction($mybb->get_input('rid', 1));

            \ougc\CustomReputation\Admin\admin_redirect($lang->$lang_var);
        }
    }

    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }

    if ($isAddRatePage) {
        $form = new Form(urlHandlerBuild(['action' => 'add']), 'post');
        $form_container = new FormContainer($sub_tabs['ougc_customrep_add']['title']);
    } else {
        $form = new Form(urlHandlerBuild(['action' => 'edit', 'rid' => $rateData['rid']]), 'post');
        $form_container = new FormContainer($sub_tabs['ougc_customrep_edit']['title']);
    }

    $form_container->output_row(
        $lang->ougc_customrep_h_name . ' <em>*</em>',
        $lang->ougc_customrep_h_name_d,
        $form->generate_text_box('name', $rateInputData['name'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_image,
        $lang->ougc_customrep_h_image_d,
        $form->generate_text_box('image', $rateInputData['image'])
    );

    // TODO: Allow multiple reputations (+2, -1, +n, -n)
    $form_container->output_row(
        $lang->ougc_customrep_h_reptype,
        $lang->ougc_customrep_h_reptype_d,
        $form->generate_text_box('reptype', $rateInputData['reptype'])
    );

    ougc_print_selection_javascript();

    $groups_select = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups_type\" value=\"all\" {$group_checked['all']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups_type\" value=\"custom\" {$group_checked['custom']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"groups_forums_groups_custom\" class=\"groups_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>" . $form->generate_group_select(
            'groups[]',
            $mybb->get_input('groups', 2),
            ['multiple' => true, 'size' => 5]
        ) . "</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups_type\" value=\"none\" {$group_checked['none']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('groups');
	</script>";

    $form_container->output_row(
        $lang->ougc_customrep_f_groups,
        $lang->ougc_customrep_f_groups_d,
        $groups_select,
        '',
        [],
        ['id' => 'row_groups']
    );

    $forums_select = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums_type\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums_type\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
					<td>" . $form->generate_forum_select(
            'forums[]',
            $mybb->get_input('forums', 2),
            ['multiple' => true, 'size' => 5]
        ) . "</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums_type\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('forums');
	</script>";

    $form_container->output_row(
        $lang->ougc_customrep_f_forums,
        $lang->ougc_customrep_f_forums_d,
        $forums_select,
        '',
        [],
        ['id' => 'row_forums']
    );

    $form_container->output_row(
        $lang->ougc_customrep_h_order,
        $lang->ougc_customrep_f_disporder_d,
        $form->generate_text_box(
            'disporder',
            $rateInputData['disporder'],
            ['style' => 'text-align: center; width: 30px;" maxlength="5']
        )
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_visible,
        $lang->ougc_customrep_f_visible_d,
        $form->generate_yes_no_radio('visible', $rateInputData['visible'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_firstpost,
        $lang->ougc_customrep_h_firstpost_d,
        $form->generate_yes_no_radio('firstpost', $rateInputData['firstpost'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_allowdeletion,
        $lang->ougc_customrep_h_allowdeletion_d,
        $form->generate_yes_no_radio('allowdeletion', $rateInputData['allowdeletion'])
    );
    $isAddRatePage || $form_container->output_row(
        $lang->ougc_customrep_h_customvariable,
        $lang->sprintf($lang->ougc_customrep_h_customvariable_d, (int)$rateData['rid']),
        $form->generate_yes_no_radio('customvariable', $rateInputData['customvariable'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_requireattach,
        $lang->ougc_customrep_h_requireattach_d,
        $form->generate_yes_no_radio('requireattach', $rateInputData['requireattach'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_points,
        $lang->ougc_customrep_h_points_d,
        $form->generate_text_box('points', $rateInputData['points'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_ignorepoints,
        $lang->ougc_customrep_h_ignorepoints_d,
        $form->generate_text_box('ignorepoints', $rateInputData['ignorepoints'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_inmultiple,
        $lang->ougc_customrep_h_inmultiple_d,
        $form->generate_yes_no_radio('inmultiple', $rateInputData['inmultiple'])
    );
    $form_container->output_row(
        $lang->ougc_customrep_h_createCoreReputationType,
        $lang->ougc_customrep_h_createCoreReputationType_d,
        $form->generate_select_box('createCoreReputationType', [
            0 => $lang->none,
            CORE_REPUTATION_TYPE_POSITIVE => $lang->ougc_customrep_h_createCoreReputationTypePositive,
            CORE_REPUTATION_TYPE_NEUTRAL => $lang->ougc_customrep_h_createCoreReputationTypeNeutral,
            CORE_REPUTATION_TYPE_NEGATIVE => $lang->ougc_customrep_h_createCoreReputationTypeNegative,
        ], $rateInputData['createCoreReputationType'])
    );

    $form_container->end();

    $form->output_submit_wrapper(
        [
            $form->generate_submit_button($lang->ougc_customrep_button_submit),
            $form->generate_reset_button($lang->reset)
        ]
    );

    $form->end();

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'delete') {
    if (!($rateData = rateGet($mybb->get_input('rid', 1)))) {
        \ougc\CustomReputation\Admin\admin_redirect($lang->ougc_customrep_message_invalidrep, true);
    }

    if ($mybb->request_method == 'post') {
        if (isset($mybb->input['no']) || $mybb->get_input('my_post_key') != $mybb->post_code) {
            \ougc\CustomReputation\Admin\admin_redirect();
        }

        rateDelete($mybb->get_input('rid', 1));

        logAdminAction($mybb->get_input('rid', 1));

        cacheUpdate();

        \ougc\CustomReputation\Admin\admin_redirect($lang->ougc_customrep_message_deleterep);
    }

    $page->add_breadcrumb_item($lang->delete);

    $page->output_confirm_action(urlHandlerBuild(['action' => 'delete', 'rid' => $mybb->get_input('rid', 1)])
    );
} else {
    $page->output_header($lang->ougc_customrep);
    $page->output_nav_tabs($sub_tabs, 'ougc_customrep_view');

    $table = new Table();
    $table->construct_header($lang->ougc_customrep_h_image, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->ougc_customrep_h_name, ['width' => '60%']);
    $table->construct_header($lang->ougc_customrep_h_order, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->ougc_customrep_h_visible, ['width' => '10%', 'class' => 'align_center']);
    $table->construct_header($lang->options, ['width' => '10%', 'class' => 'align_center']);

    // Multi-page support
    $perpage = (int)(isset($mybb->input['perpage']) ? $mybb->get_input('perpage', 1) : 10);
    if ($perpage < 1) {
        $perpage = 10;
    } elseif ($perpage > 100) {
        $perpage = 100;
    }

    if ($mybb->get_input('page', 1) > 0) {
        $start = ($mybb->get_input('page', 1) - 1) * $perpage;
    } else {
        $start = 0;
        $mybb->input['page'] = 1;
    }

    $query = $db->simple_select('ougc_customrep', 'COUNT(rid) AS reps');
    $repcount = (int)$db->fetch_field($query, 'reps');

    if ($repcount < 1) {
        $table->construct_cell(
            '<div align="center">' . $lang->ougc_customrep_message_empty . '</div>',
            ['colspan' => 5]
        );
        $table->construct_row();

        $table->output($sub_tabs['ougc_customrep_view']['title']);
    } else {
        $query = $db->simple_select(
            'ougc_customrep',
            '*',
            '',
            ['limit' => $perpage, 'limit_start' => $start, 'order_by' => 'disporder']
        );

        if ($mybb->request_method == 'post' && $mybb->get_input('action') == 'updatedisporder') {
            foreach ($mybb->input['disporder'] as $rid => $disporder) {
                rateUpdate(['disporder' => $disporder], (int)$rid);
            }

            cacheUpdate();

            \ougc\CustomReputation\Admin\admin_redirect();
        }

        $form = new Form(urlHandlerBuild(['action' => 'updatedisporder']), 'post');

        while ($rateData = $db->fetch_array($query)) {
            if ($mybb->settings['ougc_customrep_fontawesome']) {
                $image = '<i class="' . $rateData['image'] . '" aria-hidden="true"></i>';
            } else {
                $image = '<img src="' . rateGetImage(
                        $rateData['image'],
                        (int)$rateData['rid']
                    ) . '" />';
            }

            $link = urlHandlerBuild(['action' => 'edit', 'rid' => $rateData['rid']]);

            $table->construct_cell($image, ['class' => 'align_center']);
            $table->construct_cell("<a href='{$link}'>" . htmlspecialchars_uni($rateData['name']) . '</a>');
            $table->construct_cell(
                $form->generate_text_box(
                    'disporder[' . $rateData['rid'] . ']',
                    (int)$rateData['disporder'],
                    ['style' => 'text-align: center; width: 30px;']
                ),
                ['class' => 'align_center']
            );

            $table->construct_cell(($rateData['visible'] ? $lang->yes : $lang->no), ['class' => 'align_center']);

            $popup = new PopupMenu('rep_' . $rateData['rid'], $lang->options);
            $popup->add_item($lang->ougc_customrep_tab_edit, $link);
            $popup->add_item(
                $lang->delete,
                urlHandlerBuild(['action' => 'delete', 'rid' => $rateData['rid']])
            );
            $table->construct_cell($popup->fetch(), ['class' => 'align_center']);

            $table->construct_row();
        }

        // Set url to use
        urlHandlerSet('index.php');

        // Multipage
        if (($multipage = trim(
            draw_admin_pagination($mybb->get_input('page', 1), $perpage, $repcount, urlHandlerBuild())
        ))) {
            echo $multipage;
        }
        $limitstring = '<div style="float: right;">Perpage: ';
        for ($p = 10; $p < 51; $p = $p + 10) {
            $s = ' - ';
            if ($p == 50) {
                $s = '';
            }

            if ($mybb->get_input('page', 1) == $p / 10) {
                $limitstring .= $p . $s;
            } else {
                $limitstring .= '<a href="' . urlHandlerBuild(['perpage' => $p]) . '">' . $p . '</a>' . $s;
            }
        }
        $limitstring .= '</div>';
        $table->output($sub_tabs['ougc_customrep_view']['title'] . $limitstring);

        $form->output_submit_wrapper(
            [
                $form->generate_submit_button($lang->ougc_customrep_button_disponder),
                $form->generate_reset_button($lang->reset)
            ]
        );
        $form->end();

        if ($mybb->settings['ougc_customrep_fontawesome']) {
            echo $mybb->settings['ougc_customrep_fontawesome_acp'];
        }
    }

    $page->output_footer();
}
exit;
