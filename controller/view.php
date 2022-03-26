<?php

// Simplified version of viewtopic.php

namespace nekstati\schedulePost\controller;

class view
{
	protected $config, $db, $request, $user, $root_path;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		$root_path
	)
	{
		$this->config			= $config;
		$this->db				= $db;
		$this->request			= $request;
		$this->user				= $user;
		$this->root_path		= $root_path;
	}

	public function view()
	{
		global $auth, $db, $cache, $config, $phpbb_container, $phpbb_root_path, $phpEx, $request, $table_prefix, $template, $user;
		$this->table_prefix = $table_prefix;
		$this->router = $phpbb_container->get('routing.helper');
		$pagination = $phpbb_container->get('pagination');
		$view = $request->variable('view', '');
		$ext_web_path = "{$user->page['root_script_path']}ext/nekstati/schedulePost/";

		include $phpbb_root_path . 'includes/functions_display.php';
		include $phpbb_root_path . 'includes/functions_user.php';

		$user->add_lang('viewtopic');
		$user->add_lang_ext('nekstati/schedulePost', 'common');

		$topic_id = request_var('t', 0);

		$sql = "SELECT * FROM {$this->table_prefix}topics_scheduled
			WHERE item_id = $topic_id";
		$result = $this->db->sql_query($sql);
		$sql_data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (empty($sql_data))
		{
			trigger_error('NO_TOPIC');
		}

		$json_data = json_decode($sql_data['item_data'], true);
		$topic_data = array_merge($json_data['post_data'], $json_data['data'], ['post_text' => $json_data['data']['message'], 'topic_poster' => $json_data['data']['poster_id']]);



		$forum_id = (int) $topic_data['forum_id'];

		// Refresh forum data that may have changed since scheduled topic was created
		$sql = "SELECT * FROM {$this->table_prefix}forums
			WHERE forum_id = $forum_id";
		$result = $this->db->sql_query($sql);
		$forum_data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (empty($forum_data))
		{
			$forum_id = request_var('f', 0);

			$sql = "SELECT * FROM {$this->table_prefix}forums
				WHERE forum_id = $forum_id";
			$result = $this->db->sql_query($sql);
			$forum_data = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (empty($forum_data))
			{
				trigger_error('NO_FORUM');
			}
		}

		$topic_data = array_merge($topic_data, $forum_data);



		$user->page['forum'] = $forum_id;
		$user->setup('viewtopic', $topic_data['forum_style']);

		if (!$auth->acl_get('f_read', $forum_id))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				send_status_line(403, 'Forbidden');
				trigger_error('SORRY_AUTH_READ');
			}

			login_box('', $user->lang['LOGIN_VIEWFORUM']);
		}

		if ($topic_data['forum_password'])
		{
			login_forum_box($topic_data);
		}

		$can_view_topic = $auth->acl_get('f_schedule_view', $forum_id);
		$can_view_topic_as_author = $topic_data['poster_id'] == $user->data['user_id'] && $user->data['is_registered'] && $auth->acl_get('f_schedule_post', $forum_id);

		if (!$can_view_topic && !$can_view_topic_as_author)
		{
			send_status_line(403, 'Forbidden');
			trigger_error('SORRY_AUTH_READ_TOPIC');
		}



		$viewtopic_url = $this->router->route('nekstati_schedulePost_view', ['f' => $forum_id, 't' => $topic_id]);
		$ranks = $cache->obtain_ranks();
		$icons = $cache->obtain_icons();
		$extensions = array();
		if (sizeof($topic_data['attachment_data']))
		{
			$extensions = $cache->obtain_attach_extensions($forum_id);
		}

		$s_forum_rules = '';
		generate_forum_nav($topic_data);
		generate_forum_rules($topic_data);

		$forum_moderators = array();
		if ($config['load_moderators'])
		{
			get_moderators($forum_moderators, $forum_id);
		}

		// @TODO
		/*
		$quickmod_array = array(
			'lock'					=> array('LOCK_TOPIC', ($topic_data['topic_status'] == ITEM_UNLOCKED) && ($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster']))),
			'unlock'				=> array('UNLOCK_TOPIC', ($topic_data['topic_status'] != ITEM_UNLOCKED) && ($auth->acl_get('m_lock', $forum_id))),
			'delete_topic'			=> array('DELETE_TOPIC', $auth->acl_get('m_delete', $forum_id)),
			'move'					=> array('MOVE_TOPIC', $auth->acl_get('m_move', $forum_id)),
		);
		foreach ($quickmod_array as $option => $qm_ary)
		{
			if (!empty($qm_ary[1]))
			{
				phpbb_add_quickmod_option($viewtopic_url . '&amp;quickmod=1', $option, $qm_ary[0]);
			}
		}
		*/

		$topic_data['topic_title'] = censor_text($topic_data['topic_title']);

		// This just fills PAGE_NUMBER variable ("Page 1 of 1"), but without this, we have hanging bullet after "1 message"
		$pagination->generate_template_pagination($viewtopic_url, 'pagination', 'start', 1, $config['posts_per_page'], 0);

		$title_icon = '<img src="' . $ext_web_path . 'images/clock.png" class="schedule-post-title-icon" title="' . $user->lang['SCHEDULE_POST_TOPIC'] . '" />';

		$template->assign_vars(array(
			'FORUM_DESC'	=> generate_text_for_display($topic_data['forum_desc'], $topic_data['forum_desc_uid'], $topic_data['forum_desc_bitfield'], $topic_data['forum_desc_options']),
			'TOPIC_TITLE' 	=> $title_icon . ' ' . $topic_data['topic_title'],
			'SCHEDULE_POST_TOPIC_DATE'		=> $user->format_date($sql_data['publish_time']),

			'TOTAL_POSTS'	=> $user->lang('VIEW_TOPIC_POSTS', 1),
			'U_MCP' 		=> ($auth->acl_get('m_', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=forum_view&amp;f=$forum_id", true, $user->session_id) : '',

			'MODERATORS'	=> (isset($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id])) ? implode($user->lang['COMMA_SEPARATOR'], $forum_moderators[$forum_id]) : '',

			'S_IS_LOCKED'			=> ($topic_data['topic_status'] == ITEM_UNLOCKED && $topic_data['forum_status'] == ITEM_UNLOCKED) ? false : true,
			'S_SINGLE_MODERATOR'	=> (!empty($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id]) > 1) ? false : true,

			'L_RETURN_TO_FORUM'		=> $user->lang('RETURN_TO', $topic_data['forum_name']),
			'S_VIEWTOPIC'			=> true,

			'S_DISPLAY_POST_INFO'	=> ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,
			'S_DISPLAY_REPLY_INFO'	=> ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_reply', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,

			'U_VIEW_TOPIC' 			=> $viewtopic_url,
			'U_VIEW_FORUM' 			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
			'U_PRINT_TOPIC'			=> ($auth->acl_get('f_print', $forum_id)) ? $viewtopic_url . '&amp;view=print' : '',

			'U_POST_NEW_TOPIC' 		=> ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=post&amp;f=$forum_id") : '',
			'U_POST_REPLY_TOPIC' 	=> '#',

			'SCHEDULE_POST_VIEW'	=> true,
		));

		if (!empty($json_data['poll']))
		{
			$parse_flags = ($topic_data['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
			$topic_data['poll_title'] = generate_text_for_display($topic_data['poll_title'], $topic_data['bbcode_uid'], $topic_data['bbcode_bitfield'], $parse_flags, true);
			$poll_template_data = $poll_options_template_data = array();

			foreach ($json_data['poll']['poll_options'] as $i => $poll_option)
			{
				$poll_options_template_data[] = array(
					'POLL_OPTION_ID' 			=> $i,
					'POLL_OPTION_CAPTION' 		=> generate_text_for_display($poll_option, $topic_data['bbcode_uid'], $topic_data['bbcode_bitfield'], $parse_flags, true),
					'POLL_OPTION_RESULT' 		=> 0,
					'POLL_OPTION_PERCENT' 		=> sprintf("%.1d%%", 0),
					'POLL_OPTION_PERCENT_REL' 	=> sprintf("%.1d%%", 0),
					'POLL_OPTION_PCT'			=> 0,
					'POLL_OPTION_WIDTH'     	=> 0,
					'POLL_OPTION_VOTED'			=> false,
					'POLL_OPTION_MOST_VOTES'	=> false,
				);
			}

			$poll_end = $topic_data['poll_length'] + $topic_data['poll_start'];

			$poll_template_data = array(
				'POLL_QUESTION'		=> $topic_data['poll_title'],
				'TOTAL_VOTES' 		=> 0,
				'POLL_LEFT_CAP_IMG'	=> $user->img('poll_left'),
				'POLL_RIGHT_CAP_IMG'=> $user->img('poll_right'),

				'L_MAX_VOTES'		=> $user->lang('MAX_OPTIONS_SELECT', (int) $topic_data['poll_max_options']),
				'L_POLL_LENGTH'		=> ($topic_data['poll_length']) ? sprintf($user->lang[($poll_end > time()) ? 'POLL_RUN_TILL' : 'POLL_ENDED_AT'], $user->format_date($poll_end)) : '',

				'S_HAS_POLL'		=> true,
				'S_CAN_VOTE'		=> false,
				'S_DISPLAY_RESULTS'	=> true,
				'S_IS_MULTI_CHOICE'	=> ($topic_data['poll_max_options'] > 1) ? true : false,
				'S_POLL_ACTION'		=> $viewtopic_url,

				'U_VIEW_RESULTS'	=> $viewtopic_url . '&amp;view=viewpoll',
			);

			$template->assign_block_vars_array('poll_option', $poll_options_template_data);
			$template->assign_vars($poll_template_data);

			unset($poll_end, $poll_options_template_data, $poll_template_data);
		}



		$poster_id = (int) $topic_data['poster_id'];

		$sql_ary = array(
			'SELECT'	=> 'u.*, z.friend, z.foe',
			'FROM'		=> array(
				USERS_TABLE		=> 'u',
			),
			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(ZEBRA_TABLE => 'z'),
					'ON'	=> 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = ' . $poster_id,
				),
			),
			'WHERE'		=> 'u.user_id = ' . $poster_id,
		);
		$sql = $db->sql_build_query('SELECT', $sql_ary);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$topic_data += array(
			'hide_post'			=> ($row['foe']) ? true : false,
			'user_id'			=> $row['user_id'],
			'username'			=> $row['username'],
			'user_colour'		=> $row['user_colour'],
			'icon_id'			=> (isset($icons[$topic_data['icon_id']]['img'], $icons[$topic_data['icon_id']]['height'], $icons[$topic_data['icon_id']]['width'])) ? $topic_data['icon_id'] : 0,
			'friend'			=> $row['friend'],
			'foe'				=> $row['foe'],
		);

		if ($poster_id == ANONYMOUS)
		{
			$user_cache_data = array(
				'user_type'		=> USER_IGNORE,
				'joined'		=> '',
				'posts'			=> '',

				'sig'					=> '',
				'sig_bbcode_uid'		=> '',
				'sig_bbcode_bitfield'	=> '',

				'online'			=> false,
				'avatar'			=> ($user->optionget('viewavatars')) ? phpbb_get_user_avatar($row) : '',
				'rank_title'		=> '',
				'rank_image'		=> '',
				'rank_image_src'	=> '',
				'pm'				=> '',
				'email'				=> '',
				'jabber'			=> '',
				'search'			=> '',

				'username'			=> $row['username'],
				'user_colour'		=> $row['user_colour'],
				'contact_user'		=> '',

				'warnings'			=> 0,
				'allow_pm'			=> 0,
			);

			$user_cache[$poster_id] = $user_cache_data;

			$user_rank_data = phpbb_get_user_rank($row, false);
			$user_cache[$poster_id]['rank_title'] = $user_rank_data['title'];
			$user_cache[$poster_id]['rank_image'] = $user_rank_data['img'];
			$user_cache[$poster_id]['rank_image_src'] = $user_rank_data['img_src'];
		}
		else
		{
			$user_sig = '';

			// We add the signature to every posters entry because enable_sig is post dependent
			if ($row['user_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
			{
				$user_sig = $row['user_sig'];
			}

			$id_cache[] = $poster_id;

			$user_cache_data = array(
				'user_type'					=> $row['user_type'],
				'user_inactive_reason'		=> $row['user_inactive_reason'],

				'joined'		=> $user->format_date($row['user_regdate']),
				'posts'			=> $row['user_posts'],
				'warnings'		=> (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,

				'sig'					=> $user_sig,
				'sig_bbcode_uid'		=> (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
				'sig_bbcode_bitfield'	=> (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',

				'viewonline'	=> $row['user_allow_viewonline'],
				'allow_pm'		=> $row['user_allow_pm'],

				'avatar'		=> ($user->optionget('viewavatars')) ? phpbb_get_user_avatar($row) : '',

				'rank_title'		=> '',
				'rank_image'		=> '',
				'rank_image_src'	=> '',

				'username'			=> $row['username'],
				'user_colour'		=> $row['user_colour'],
				'contact_user' 		=> $user->lang('CONTACT_USER', get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['username'])),

				'online'		=> false,
				'jabber'		=> ($config['jab_enable'] && $row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
				'search'		=> ($config['load_search'] && $auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',

				'author_full'		=> get_username_string('full', $poster_id, $row['username'], $row['user_colour']),
				'author_colour'		=> get_username_string('colour', $poster_id, $row['username'], $row['user_colour']),
				'author_username'	=> get_username_string('username', $poster_id, $row['username'], $row['user_colour']),
				'author_profile'	=> get_username_string('profile', $poster_id, $row['username'], $row['user_colour']),
			);

			$user_cache[$poster_id] = $user_cache_data;

			$user_rank_data = phpbb_get_user_rank($row, $row['user_posts']);
			$user_cache[$poster_id]['rank_title'] = $user_rank_data['title'];
			$user_cache[$poster_id]['rank_image'] = $user_rank_data['img'];
			$user_cache[$poster_id]['rank_image_src'] = $user_rank_data['img_src'];

			if ((!empty($row['user_allow_viewemail']) && $auth->acl_get('u_sendemail')) || $auth->acl_get('a_email'))
			{
				$user_cache[$poster_id]['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=$poster_id") : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $row['user_email']);
			}
			else
			{
				$user_cache[$poster_id]['email'] = '';
			}
		}
		unset($row);



		// Custom profile fields
		if ($config['load_cpf_viewtopic'] && !empty($id_cache))
		{
			$cp = $phpbb_container->get('profilefields.manager');
			$profile_fields_tmp = $cp->grab_profile_fields_data($id_cache);
			$profile_fields_cache = array();
			foreach ($profile_fields_tmp as $profile_user_id => $profile_fields)
			{
				$profile_fields_cache[$profile_user_id] = array();
				foreach ($profile_fields as $used_ident => $profile_field)
				{
					if ($profile_field['data']['field_show_on_vt'])
					{
						$profile_fields_cache[$profile_user_id][$used_ident] = $profile_field;
					}
				}
			}
			unset($profile_fields_tmp);
		}



		if ($config['load_onlinetrack'] && !empty($id_cache))
		{
			$sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
				FROM ' . SESSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('session_user_id', $id_cache) . '
				GROUP BY session_user_id';
			$result = $db->sql_query($sql);

			$update_time = $config['load_online_time'] * 60;
			while ($row = $db->sql_fetchrow($result))
			{
				$user_cache[$row['session_user_id']]['online'] = (time() - $update_time < $row['online_time'] && (($row['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
			}
			$db->sql_freeresult($result);
		}
		unset($id_cache);



		$topic_data['post_attachment'] = sizeof($topic_data['attachment_data']);
		$attachments = [];
		$display_notice = false;

		if ($topic_data['post_attachment'] && $config['allow_attachments'])
		{
			if ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id))
			{
				$sql = 'SELECT *
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('attach_id',  array_column($topic_data['attachment_data'], 'attach_id')) . '
					ORDER BY attach_id DESC';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[$topic_id][] = $row;
				}
				$db->sql_freeresult($result);

				// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
				if (!sizeof($attachments))
				{
					// @TODO
				}
			}
			else
			{
				$display_notice = true;
			}
		}

		$template->assign_vars(array(
			'S_HAS_ATTACHMENTS' => $topic_data['post_attachment'],
		));



		if ($config['enable_accurate_pm_button'])
		{
			$can_receive_pm_list = $auth->acl_get_list(array_keys($user_cache), 'u_readpm');
			$can_receive_pm_list = (empty($can_receive_pm_list) || !isset($can_receive_pm_list[0]['u_readpm'])) ? array() : $can_receive_pm_list[0]['u_readpm'];
			$permanently_banned_users = phpbb_get_banned_user_ids(array_keys($user_cache), false);
		}
		else
		{
			$can_receive_pm_list = array_keys($user_cache);
			$permanently_banned_users = [];
		}



		// Output the posts
		for ($i = 0; $i < 1; $i++)
		{
			$row = $topic_data;
			$row['post_id'] = $topic_id;
			$row['post_username'] = $row['username'];

			if ($user_cache[$poster_id]['sig'] && $row['enable_sig'] && empty($user_cache[$poster_id]['sig_parsed']))
			{
				$parse_flags = ($user_cache[$poster_id]['sig_bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
				$user_cache[$poster_id]['sig'] = generate_text_for_display($user_cache[$poster_id]['sig'], $user_cache[$poster_id]['sig_bbcode_uid'], $user_cache[$poster_id]['sig_bbcode_bitfield'],  $parse_flags, true);
				$user_cache[$poster_id]['sig_parsed'] = true;
			}

			$parse_flags = ($row['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
			$message = generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], $parse_flags, true);

			if (!empty($attachments[$row['post_id']]))
			{
				parse_attachments($forum_id, $message, $attachments[$row['post_id']], $update_count);
			}

			$row['post_subject'] = censor_text($row['post_subject']);

			$cp_row = array();
			if ($config['load_cpf_viewtopic'])
			{
				$cp_row = (isset($profile_fields_cache[$poster_id])) ? $cp->generate_profile_fields_template_data($profile_fields_cache[$poster_id]) : array();
			}

			$post_unread = false;
			$s_first_unread = false;

			$s_cannot_edit = !$auth->acl_get('f_edit', $forum_id) || $user->data['user_id'] != $poster_id;
			$s_cannot_edit_locked = ($topic_data['topic_status'] == ITEM_LOCKED && !$auth->acl_get('m_lock', $forum_id)) || $row['post_edit_locked'];

			$s_cannot_delete = $user->data['user_id'] != $poster_id || !$auth->acl_get('f_delete', $forum_id);
			$s_cannot_delete_locked = $topic_data['topic_status'] == ITEM_LOCKED || $row['post_edit_locked'];

			$edit_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) ||
				(!$s_cannot_edit && !$s_cannot_edit_locked)
			));

			$delete_allowed = ($user->data['is_registered'] && (
				($auth->acl_get('m_delete', $forum_id) || ($auth->acl_get('m_softdelete', $forum_id))) ||
				(!$s_cannot_delete && !$s_cannot_delete_locked)
			));

			$publish_allowed = (($user->data['user_id'] == $poster_id && $auth->acl_get('f_schedule_post', $forum_id)) || $auth->acl_get('m_approve', $forum_id));

			$can_receive_pm = (
				$user_cache[$poster_id]['user_type'] != USER_IGNORE &&
				($user_cache[$poster_id]['user_type'] != USER_INACTIVE || $user_cache[$poster_id]['user_inactive_reason'] != INACTIVE_MANUAL) &&
				in_array($poster_id, $can_receive_pm_list) &&
				!in_array($poster_id, $permanently_banned_users) &&
				(($auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_')) || $user_cache[$poster_id]['allow_pm'])
			);

			$u_pm = '';

			if ($config['allow_privmsg'] && $auth->acl_get('u_sendpm') && $can_receive_pm)
			{
				$u_pm = append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . $poster_id);
			}

			$post_row = array(
				'POST_AUTHOR_FULL'		=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_full'] : get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR_COLOUR'	=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_colour'] : get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_username'] : get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'U_POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_profile'] : get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),

				'RANK_TITLE'		=> $user_cache[$poster_id]['rank_title'],
				'RANK_IMG'			=> $user_cache[$poster_id]['rank_image'],
				'RANK_IMG_SRC'		=> $user_cache[$poster_id]['rank_image_src'],
				'POSTER_JOINED'		=> $user_cache[$poster_id]['joined'],
				'POSTER_POSTS'		=> $user_cache[$poster_id]['posts'],
				'POSTER_AVATAR'		=> $user_cache[$poster_id]['avatar'],
				'POSTER_WARNINGS'	=> $auth->acl_get('m_warn') ? $user_cache[$poster_id]['warnings'] : '',
				'CONTACT_USER'		=> $user_cache[$poster_id]['contact_user'],

				'POST_DATE'			=> ($view == 'print') ? $user->format_date($sql_data['publish_time'], false, true) : $user->lang('SCHEDULE_POST_DATE_LONG', mb_strtolower($user->format_date($row['creation_time'])), mb_strtolower($user->format_date($sql_data['publish_time']))),
				'POST_DATE_RFC3339'	=> gmdate(DATE_RFC3339, $sql_data['publish_time']),
				'POST_SUBJECT'		=> $row['post_subject'],
				'MESSAGE'			=> $message,
				'SIGNATURE'			=> ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
				'EDITED_MESSAGE'	=> '', // @TODO
				'EDIT_REASON'		=> $row['post_edit_reason'],

				'MINI_POST_IMG'			=> ($post_unread) ? $user->img('icon_post_target_unread', 'UNREAD_POST') : $user->img('icon_post_target', 'POST'),
				'POST_ICON_IMG'			=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['img'] : '',
				'POST_ICON_IMG_WIDTH'	=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['width'] : '',
				'POST_ICON_IMG_HEIGHT'	=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['height'] : '',
				'POST_ICON_IMG_ALT' 	=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['alt'] : '',
				'ONLINE_IMG'			=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($user_cache[$poster_id]['online']) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
				'S_ONLINE'				=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),

				'U_EDIT'			=> ($edit_allowed) ? append_sid("{$phpbb_root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_edit_item={$row['post_id']}") : '',
				'U_DELETE'			=> ($delete_allowed) ? append_sid("{$phpbb_root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_delete_item={$row['post_id']}") : '',
				'U_PUBLISH'			=> ($publish_allowed) ? append_sid("{$phpbb_root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_publish_item={$row['post_id']}") : '',

				'U_SEARCH'		=> $user_cache[$poster_id]['search'],
				'U_PM'			=> $u_pm,
				'U_EMAIL'		=> $user_cache[$poster_id]['email'],
				'U_JABBER'		=> $user_cache[$poster_id]['jabber'],

				'U_MINI_POST'		=> $viewtopic_url,

				'U_WARN'			=> ($auth->acl_get('m_warn') && $poster_id != $user->data['user_id'] && $poster_id != ANONYMOUS) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_user&amp;f=' . $forum_id . '&amp;u=' . $poster_id, true, $user->session_id) : '',

				'POST_ID'			=> $row['post_id'],
				'POSTER_ID'			=> $poster_id,
				'MINI_POST'			=> ($post_unread) ? $user->lang['UNREAD_POST'] : $user->lang['POST'],


				'S_HAS_ATTACHMENTS'	=> (!empty($attachments[$row['post_id']])) ? true : false,
				'S_MULTIPLE_ATTACHMENTS'	=> !empty($attachments[$row['post_id']]) && sizeof($attachments[$row['post_id']]) > 1,
				'S_DISPLAY_NOTICE'	=> $display_notice && $row['post_attachment'],
				'S_FRIEND'			=> ($row['friend']) ? true : false,
				'S_CUSTOM_FIELDS'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
				'S_TOPIC_POSTER'	=> ($topic_data['topic_poster'] == $poster_id) ? true : false,
				'S_FIRST_POST'		=> true,

				'S_IGNORE_POST'		=> ($row['foe']) ? true : false,
				'L_IGNORE_POST'		=> ($row['foe']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username'])) : '',
				'S_POST_HIDDEN'		=> $row['hide_post'],
				'L_POST_DISPLAY'	=> ($row['hide_post']) ? $user->lang('POST_DISPLAY', '<a class="display_post" data-post-id="' . $row['post_id'] . '" href="' . $viewtopic_url . "&amp;p={$row['post_id']}&amp;view=show#p{$row['post_id']}" . '">', '</a>') : '',
			);

			if (isset($cp_row['row']) && sizeof($cp_row['row']))
			{
				$post_row = array_merge($post_row, $cp_row['row']);
			}

			$template->assign_block_vars('postrow', $post_row);

			$contact_fields = array(
				array(
					'ID'		=> 'pm',
					'NAME' 		=> $user->lang['SEND_PRIVATE_MESSAGE'],
					'U_CONTACT'	=> $post_row['U_PM'],
				),
				array(
					'ID'		=> 'email',
					'NAME'		=> $user->lang['SEND_EMAIL'],
					'U_CONTACT'	=> $user_cache[$poster_id]['email'],
				),
				array(
					'ID'		=> 'jabber',
					'NAME'		=> $user->lang['JABBER'],
					'U_CONTACT'	=> $user_cache[$poster_id]['jabber'],
				),
			);

			foreach ($contact_fields as $field)
			{
				if ($field['U_CONTACT'])
				{
					$template->assign_block_vars('postrow.contact', $field);
				}
			}

			if (!empty($cp_row['blockrow']))
			{
				foreach ($cp_row['blockrow'] as $field_data)
				{
					$template->assign_block_vars('postrow.custom_fields', $field_data);

					if ($field_data['S_PROFILE_CONTACT'])
					{
						$template->assign_block_vars('postrow.contact', array(
							'ID'		=> $field_data['PROFILE_FIELD_IDENT'],
							'NAME'		=> $field_data['PROFILE_FIELD_NAME'],
							'U_CONTACT'	=> $field_data['PROFILE_FIELD_CONTACT'],
						));
					}
				}
			}

			// Display attachments not placed inline
			if (!empty($attachments[$row['post_id']]))
			{
				foreach ($attachments[$row['post_id']] as $attachment)
				{
					$template->assign_block_vars('postrow.attachment', array(
						'DISPLAY_ATTACHMENT'	=> $attachment)
					);
				}
			}

			unset($attachments[$row['post_id']]);
		}
		unset($user_cache);

		// We overwrite $_REQUEST['f'] if there is no forum specified
		// to be able to display the correct online list.
		// One downside is that the user currently viewing this topic/post is not taken into account.
		if (!$request->variable('f', 0))
		{
			$request->overwrite('f', $forum_id);
		}

		$page_title = $topic_data['topic_title'];

		// Output the page
		page_header($page_title, true, $forum_id);

		$template->set_filenames(array(
			'body' => ($view == 'print') ? 'viewtopic_print.html' : 'viewtopic_body.html',
		));
		make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"), $forum_id);

		page_footer();
	}
}
