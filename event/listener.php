<?php /*/

██████████████████████████████████████████████████████████████████████████
██─█▀█─█─▄▄─█─▄▄▀█─█─▄█▀▄▄─█─▄▄▀█─▄▄─█─██─█─▀█─█─▄▄▀█─▄▄▄███─█─▀█─█─▄▄─███
██─█─█─█─██─█─▄─▄█─▄▀██─▀▀─█─▄─▄█─██─█─██─█─█▄─█─██─█▄▄▄─███─█─█▄─█─██▀███
▀▀▄▄▀▄▄▀▄▄▄▄▀▄▀▄▄▀▄▀▄▄▀▄▀▀▄▀▄▀▄▄▀▄▄▄▄▀▄▄▄▄▀▄▀▀▄▀▄▄▄▀▀▄▄▄▄▀▀▀▄▀▄▀▀▄▀▄▄▄▄▀▄▀

══════════════════════ 𝗘𝗫𝗧𝗘𝗡𝗗𝗜𝗡𝗚 𝗧𝗛𝗘 𝗨𝗡𝗘𝗫𝗧𝗘𝗡𝗗𝗔𝗕𝗟𝗘 ══════════════════════

COPYRIGHT (C) WORKAROUNDS INC., 2021. ALL RIGHTS RESERVED. PHPBB MUST DIE.


/*_*/
namespace nekstati\schedulePost\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	// Configuration
	protected $alternate_icons = 1;		// 0 for standard topic icons with a small overlaid clock icon, 1 for alternate clock-like icons
	protected $alternate_colors = 1;	// 0 for standard topiclist colors, 1 for lighter "shadowy" colors (tested on Prosilver only)
	protected $use_plupload = 1;		// Set to 0 if you have problems with attachments (e.g. while editing someone else's scheduled topics)

	static public function getSubscribedEvents()
	{
		return [
			'core.posting_modify_post_data'					=> 'posting_modify_form_and_handle_actions',
			'core.posting_modify_message_text'				=> 'posting_check_form_data',
			'core.posting_modify_submit_post_before'		=> 'posting_save_topic',
			'core.viewforum_modify_page_title'				=> 'viewforum_display_scheduled_topics',
			'core.page_footer_after'						=> 'common_overwrite_vars',

			'core.parse_attachments_modify_template_data'	=> 'replace_attach_urls_in_post',
			'core.modify_inline_attachments_template_vars'	=> 'replace_attach_urls_in_attach_panel',

			'core.permissions'								=> 'acp_add_permission',
		];
	}

	protected $auth, $config, $db, $request, $router, $template, $user, $root_path, $table_prefix;
	protected $ext_path, $ext_web_path, $mode, $publish_time, $stored_post;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request_interface $request, \phpbb\routing\helper $router, \phpbb\template\template $template, \phpbb\user $user, $root_path, $table_prefix)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->router = $router;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->table_prefix = $table_prefix;

		$this->ext_path = "{$root_path}ext/nekstati/schedulePost/";
		$this->ext_web_path = "{$user->page['root_script_path']}ext/nekstati/schedulePost/";

		if ($user->page['page_name'] == 'app.php/schedulepost/view')
		{
			$this->mode = 'viewtopic';
		}
	}

	public function posting_modify_form_and_handle_actions($event)
	{
		if (!$this->auth->acl_gets('f_schedule_post', 'f_schedule_view', $event['forum_id']))
		{
			return;
		}

		// We always use 'posting.php?mode=post', even when editind a post. This requires lots of workarounds, but 'mode=edit' leads to unsolvable problems
		if ($event['mode'] != 'post')
		{
			return;
		}

		$this->user->add_lang_ext('nekstati/schedulePost', 'common');

		$publish_id = request_var('schedule_post_publish_item', 0);
		$delete_id = request_var('schedule_post_delete_item', 0);
		$edit_id = request_var('schedule_post_edit_item', 0);
		$this->mode = ($publish_id) ? 'publish' : ($delete_id ? 'delete' : ($edit_id ? 'edit' : 'add'));
		$item_id = $publish_id ?: $delete_id ?: $edit_id;

		$stored_data = [];
		$submitted = isset($_POST['post']) || isset($_POST['preview']) || isset($_POST['add_file']) || isset($_POST['delete_file']);

		// set_error_handler(__CLASS__ . '::attach_error_handler');

		if ($this->mode != 'add')
		{
			if ($item_id <= 0)
			{
				trigger_error('NO_TOPIC');
			}

			$sql = "SELECT * FROM {$this->table_prefix}topics_scheduled
				WHERE item_id = $item_id";
			$result = $this->db->sql_query($sql);
			$stored_data = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (empty($stored_data))
			{
				trigger_error('NO_TOPIC');
			}

			$m_ = ($this->mode == 'publish') ? 'm_approve' : ($this->mode == 'delete' ? 'm_delete' : 'm_edit');

			if ($stored_data['poster_id'] != $this->user->data['user_id'] && !($this->auth->acl_get($m_, $event['forum_id']) && $this->auth->acl_get('f_schedule_view', $event['forum_id'])))
			{
				trigger_error('NO_AUTH_OPERATION');
			}
		}

		if ($this->mode == 'add' && !$this->auth->acl_get('f_schedule_post', $event['forum_id']))
		{
			return; // Just disable extra form fields, no trigger_error()
		}

		if ($this->mode == 'edit' && !$submitted)
		{
			$post = $this->stored_post = json_decode($stored_data['item_data'], true);
			$event['post_data'] = array_merge($post['post_data'], $post['data'], ['post_text' => $post['data']['message']]);

			if (($post['data']['topic_status'] == ITEM_LOCKED || $post['data']['post_edit_locked'] == ITEM_LOCKED) && !$this->auth->acl_get('m_lock', $event['forum_id']))
			{
				trigger_error('NO_AUTH_OPERATION');
			}

			// Prevent error when opening a post that was not saved after deleting an attachment
			if (sizeof($post['data']['attachment_data']))
			{
				$sql = 'SELECT attach_id
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE ' . $this->db->sql_in_set('attach_id', array_column($post['data']['attachment_data'], 'attach_id'));
				$result = $this->db->sql_query($sql);
				$stored_files = array_column($this->db->sql_fetchrowset($result), null, 'attach_id'); // Reindexed by attach_id
				$this->db->sql_freeresult($result);
			}
			foreach ($post['data']['attachment_data'] as $i => &$a)
			{
				if (!isset($stored_files[$a['attach_id']]))
				{
					unset($post['data']['attachment_data'][$i]);
				}
			}
			$post['data']['attachment_data'] = array_values($post['data']['attachment_data']); // Reindex

			// Workaround to display attachments associated with this post.
			// We cannot inject them directly into $message_parser as there is no phpBB event for this.
			// So let the get_submitted_attachment_data() retrieve them from $_POST.
			$this->request->overwrite('attachment_data', $post['data']['attachment_data'], \phpbb\request\request_interface::POST);

			$time = date_create('now', $this->user->timezone)->setTimestamp((int) $stored_data['publish_time']);
			$this->publish_time = ['d' => $time->format('Y-m-d'), 't' => $time->format('H:i'), 'timestamp' => $stored_data['publish_time']];
		}
		else
		{
			$time = request_var('schedule_post_time', ['' => '']);
			$d = (!empty($time['d'])) ? $time['d'] : '';
			$t = ($d) ? (!empty($time['t']) ? $time['t'] : '00:00') : (!empty($time['t']) && $time['t'] != '00:00' ? $time['t'] : '');
			$this->publish_time = ['d' => $d, 't' => $t];
		}

		if (empty($event['post_data']['poster_id']))
		{
			$event['post_data'] += ['poster_id' => request_var('schedule_post_poster_id', $this->user->data['user_id'])];
		}
		if (empty($event['post_data']['creation_time']))
		{
			$event['post_data'] += ['creation_time' => request_var('schedule_post_creation_time', time())];
		}

		$this->template->assign_vars([
			'SCHEDULE_POST_ALLOWED'			=> true,
			'SCHEDULE_POST_TIME_D'			=> $this->publish_time['d'],
			'SCHEDULE_POST_TIME_T'			=> $this->publish_time['t'],
			'SCHEDULE_POST_DATE_MIN'		=> date_create('today midnight', $this->user->timezone)->format('Y-m-d'),
			'SCHEDULE_POST_DATE_MAX'		=> date_create('today + 1 year', $this->user->timezone)->format('Y-m-d'),
			'SCHEDULE_POST_EDIT_ITEM'		=> $item_id,
			'SCHEDULE_POST_POSTER_ID'		=> $event['post_data']['poster_id'],
			'SCHEDULE_POST_CREATION_TIME'	=> $event['post_data']['creation_time'],
		]);

		// User cleared the date field while editing scheduled topic - or pressed "Publish" button
		if (($this->mode == 'edit' && isset($_POST['post']) && empty($this->publish_time['d']))
		or ($this->mode == 'publish'))
		{
			$this->db->sql_query("DELETE FROM {$this->table_prefix}topics_scheduled WHERE item_id = $item_id");
			$new_topic_url = $this->publish(json_decode($stored_data['item_data'], true));
			redirect($new_topic_url);
		}

		if ($this->mode == 'delete')
		{
			$this->delete_item($item_id, json_decode($stored_data['item_data'], true));
		}
	}

	public function posting_check_form_data($event)
	{
		if (empty($this->publish_time) || empty($this->publish_time['d']))
		{
			return;
		}

		$errors = [];

		if (!$this->check_date($this->publish_time['d'], 'Y-m-d'))
		{
			$errors[] = $this->user->lang['SCHEDULE_POST_TIME_D_ERROR'];
		}
		if (!$this->check_date($this->publish_time['t'], 'H:i'))
		{
			$errors[] = $this->user->lang['SCHEDULE_POST_TIME_T_ERROR'];
		}

		if (empty($this->publish_time['timestamp']) && empty($errors))
		{
			$this->publish_time['timestamp'] = date_create("{$this->publish_time['d']} {$this->publish_time['t']}", $this->user->timezone)->getTimestamp();

			// Workaround for an ancient phpBB bug with relative date: tomorrow 0:00:00 shown as today 0:00:00
			$this->publish_time['timestamp']++;
		}

		if ($this->publish_time['timestamp'] < time() + 60)
		{
			$errors[] = $this->user->lang['SCHEDULE_POST_TIME_ERROR'];
		}

		$event['error'] = array_merge($event['error'], $errors);
	}

	public function posting_save_topic($event)
	{
		if (empty($this->publish_time) || empty($this->publish_time['d']))
		{
			return;
		}

		$files = $event['data']['attachment_data'];

		if (sizeof($files))
		{
			$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
				SET is_orphan = 0, poster_id = ' . $event['data']['poster_id'] . '
				WHERE ' . $this->db->sql_in_set('attach_id', array_column($files, 'attach_id'));
			$this->db->sql_query($sql);

			foreach ($files as &$f)
			{
				$f['is_orphan'] = 0;
			}

			$event['data'] = array_replace($event['data'], ['attachment_data' => $files]);
		}

		// Handle vars not handled by posting.php in 'mode=post'
		$event['data'] = array_replace($event['data'], [
			'post_edit_locked'	=> ($this->mode == 'edit' && $this->auth->acl_get('m_edit', $event['forum_id'])) ? isset($_POST['lock_post']) : 0,
			'post_edit_reason'	=> ($this->mode == 'edit' && $this->auth->acl_get('m_edit', $event['forum_id'])) ? request_var('edit_reason', '', true) : '',
			'post_edit_user'	=> ($this->mode == 'edit') ? $this->user->data['user_id'] : 0,
		]);

		$sql_ary = [
			'forum_id'		=> $event['forum_id'],
			'poster_id'		=> $event['data']['poster_id'],
			'publish_time'	=> $this->publish_time['timestamp'],
			'item_data'		=> json_encode($event->get_data(), JSON_UNESCAPED_UNICODE),
		];

		if ($this->mode == 'edit')
		{
			$item_id = request_var('schedule_post_edit_item', 0);

			$sql = "UPDATE {$this->table_prefix}topics_scheduled
				SET " . $this->db->sql_build_array('UPDATE', $sql_ary) . "
				WHERE item_id = " . $item_id;
			$this->db->sql_query($sql);
		}
		else
		{
			$sql = "INSERT INTO {$this->table_prefix}topics_scheduled " . $this->db->sql_build_array('INSERT', $sql_ary);
			$this->db->sql_query($sql);

			$item_id = $this->db->sql_nextid();
		}

		$topic_url = $this->router->route('nekstati_schedulePost_view', ['f' => $event['forum_id'], 't' => $item_id]);
		$forum_url = append_sid("{$this->root_path}viewforum.php", "f={$event['forum_id']}");
		meta_refresh(3, $topic_url);
		trigger_error($this->user->lang[($this->mode == 'edit') ? 'SCHEDULE_POST_EDITED' : 'SCHEDULE_POST_ADDED']
			. '<br /><br />' . $this->user->lang('RETURN_TOPIC', "<a href=\"$topic_url\">", '</a>')
			. '<br /><br />' . $this->user->lang('RETURN_FORUM', "<a href=\"$forum_url\">", '</a>'));
	}

	public function viewforum_display_scheduled_topics($event)
	{
		// Work on first page only
		if ($event['start'] > 0)
		{
			return;
		}

		$this->mode = 'viewforum';

		$forum_id = $event['forum_id'];
		$can_view_all = $this->auth->acl_get('f_schedule_view', $forum_id);
		$can_view_own = $this->auth->acl_get('f_schedule_post', $forum_id) && $this->user->data['is_registered'];

		if (!$can_view_all && !$can_view_own)
		{
			return;
		}

		$and = ($can_view_all) ? '' : ' AND poster_id = ' . $this->user->data['user_id'];
		$sql = "SELECT * FROM {$this->table_prefix}topics_scheduled
			WHERE forum_id = $forum_id
			$and
			ORDER BY publish_time";
		$result = $this->db->sql_query($sql);
		$topics = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		if (empty($topics))
		{
			return;
		}

		$this->user->add_lang_ext('nekstati/schedulePost', 'common');
		$posters = [];
		$icons = ($event['forum_data']['enable_icons'] && $this->alternate_icons) ? $GLOBALS['cache']->obtain_icons() : null;
		$topic_icon = 'clock.png';
		$published = false;

		foreach ($topics as $row)
		{
			$posters[$row['poster_id']] = $row['poster_id'];
		}

		if (sizeof($posters))
		{
			$sql = 'SELECT user_id, username, user_colour
				FROM ' . USERS_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', $posters);
			$result = $this->db->sql_query($sql);
			$rowset = array_column($this->db->sql_fetchrowset($result), null, 'user_id'); // Reindexed by user_id
			$this->db->sql_freeresult($result);

			foreach ($posters as $id => &$p)
			{
				$p = (isset($rowset[$id])) ? get_username_string('full', $rowset[$id]['user_id'], $rowset[$id]['username'], $rowset[$id]['user_colour']) : $this->user->lang['GUEST'];
			}
		}

		foreach ($topics as $row)
		{
			$post = json_decode($row['item_data'], true);

			if ($row['publish_time'] <= time()) // Do not wait for cron, publish immediately
			{
				$this->publish($post);
				$this->db->sql_query("DELETE FROM {$this->table_prefix}topics_scheduled WHERE item_id = {$row['item_id']}");
				$published = true;
			}

			if ($this->alternate_icons)
			{
				$folder_img = 'topic_scheduled' . ($post['data']['topic_status'] == ITEM_LOCKED ? '_locked' : '') . ($row['poster_id'] == $this->user->data['user_id'] ? '_mine' : '');
				$topic_icon = $icons[$post['data']['icon_id']]['img'] ?? '';
			}
			else
			{
				$post['post_data']['topic_posted'] = ($row['poster_id'] == $this->user->data['user_id']);
				topic_status($post['post_data'], 0, 0, $folder_img, $folder_alt, $topic_type);
			}

			$can_publish = ($this->user->data['user_id'] == $row['poster_id'] && $this->auth->acl_get('f_schedule_post', $forum_id)) || $this->auth->acl_get('m_approve', $forum_id);

			$this->template->assign_block_vars('scheduled_topics', [
				'TITLE'			=> $post['data']['topic_title'],
				'TIME'			=> $this->user->format_date($row['publish_time']),
				'CREATION_TIME'	=> $this->user->lang('SCHEDULE_POST_DATE_SHORT', mb_strtolower($this->user->format_date($post['post_data']['creation_time']))),
				'AUTHOR'		=> $posters[$row['poster_id']],
				'HAS_ATTACH'	=> !empty($post['data']['attachment_data']),
				'HAS_POLL'		=> !empty($post['poll']),
				// 'URL_DELETE'	=> append_sid("{$this->root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_delete_item={$row['item_id']}" . ($event['start'] == 0 ? '' : "&amp;start={$event['start']}")),
				// 'URL_EDIT'		=> append_sid("{$this->root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_edit_item={$row['item_id']}"),
				'URL_PUBLISH'	=> $can_publish ? append_sid("{$this->root_path}posting.php", "f=$forum_id&amp;mode=post&amp;schedule_post_publish_item={$row['item_id']}") : '',
				'URL_VIEW'		=> $this->router->route('nekstati_schedulePost_view', ['f' => $forum_id, 't' => $row['item_id']]),
				'IMG'			=> $folder_img,
				'ICON'			=> $topic_icon,
			]);
		}

		if ($published)
		{
			redirect(append_sid("{$this->root_path}viewforum.php", "f=$forum_id"));
		}
	}

	public function replace_attach_urls_in_post($event)
	{
		if (empty($this->mode)) return;

		$id = $event['attachment']['attach_id'];
		$arr['U_DOWNLOAD_LINK'] = $this->router->route('nekstati_schedulePost_file', ['id' => $id]);

		if (isset($event['block_array']['U_INLINE_LINK']))
			$arr['U_INLINE_LINK'] = $arr['U_DOWNLOAD_LINK'];
		if (isset($event['block_array']['THUMB_IMAGE']))
			$arr['THUMB_IMAGE'] = $this->router->route('nekstati_schedulePost_file', ['id' => $id, 't' => 1]);

		$event['block_array'] = array_replace($event['block_array'], $arr);
	}

	public function replace_attach_urls_in_attach_panel($event)
	{
		if (empty($this->mode)) return;

		// @OPTION 2
		$q = $event['attachrow_template_vars'];
		foreach ($q as $i => &$a)
			$a['U_VIEW_ATTACHMENT'] = $this->router->route('nekstati_schedulePost_file', ['id' => $a['ATTACH_ID']]);
		$event['attachrow_template_vars'] = $q;

		// Needed for "Place attach inline" buttons when Plupload is disabled, as they didn't work w/o Plupload
		// @TODO Remove if no Plupload issues
		$this->template->assign_vars([
			'SCHEDULE_POST_UPL_FILES'		=> json_encode(array_combine(array_column($q, 'ASSOC_INDEX'), array_column($q, 'A_FILENAME')), JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
			'SCHEDULE_POST_UPL_FILES_ORDER'	=> (int) $this->config['display_order'],
		]);
	}

	public function common_overwrite_vars($event)
	{
		if (empty($this->mode)) return;

		if ($this->mode == 'edit')
		{
			$poster_id = ($this->stored_post['data']['poster_id'] ?? request_var('schedule_post_poster_id', 0));

			if ($poster_id != $this->user->data['user_id'])
			{
				$this->template->assign_var('S_NOTIFY_ALLOWED', false);

				// (Only for moderation) modify Plupload URL to avoid attachment errors due to user_id mismatch.
				// If there are still problems - disable Plupload script entirely, but enable Plupload CSS back to avoid layout breaking.
				// Do not move this to latest posting.php event as the following vars cannot be overwritten there.
				if ($this->use_plupload)
				{
					$this->template->append_var('S_PLUPLOAD_URL', '&schedule_post_edit_item=' . request_var('schedule_post_edit_item', 0) . '&schedule_post_poster_id=' . $poster_id);
				}
				else
				{
					// @TODO Remove if no Plupload issues
					$this->template->assign_vars([
						'S_PLUPLOAD'						=> false,
						'SCHEDULE_POST_PLUPLOAD_DISABLED'	=> true,
					]);
				}
			}
		}

		if ($this->mode == 'edit')
		{
			$title_icon = '<img src="' . $this->ext_web_path . 'images/clock.png" class="schedule-post-title-icon" title="' . $this->user->lang['SCHEDULE_POST_TOPIC'] . '" />';
			global $forum_id;

			$this->template->assign_vars([
				'S_SAVE_ALLOWED'		=> false,
				'S_HAS_DRAFTS'			=> false,
				'S_EDIT_REASON'			=> $this->auth->acl_get('m_edit', $forum_id),
				'EDIT_REASON'			=> $this->request->variable('edit_reason', ($this->stored_post['data']['post_edit_reason'] ?? ''), true),
				'TOPIC_TITLE'			=> $title_icon . ' ' . $this->request->variable('subject', ($this->stored_post['data']['topic_title'] ?? ''), true),
				'U_VIEW_TOPIC'			=> $this->router->route('nekstati_schedulePost_view', ['f' => $forum_id, 't' => request_var('schedule_post_edit_item', 0)]),
				'L_POST_A'				=> $this->user->lang['SCHEDULE_POST_TOPIC'],
				'S_LOCK_POST_ALLOWED'	=> $this->auth->acl_get('m_edit', $forum_id),
			]);
		}

		// We must initialize these options when opening topic for edit, as we always use 'posting.php?mode=post', while normally they are initialized in 'mode=edit' only
		if ($this->mode == 'edit' && !empty($this->stored_post))
		{
			$this->template->assign_vars([
				'S_BBCODE_CHECKED'			=> (!$this->stored_post['data']['enable_bbcode'])	? ' checked="checked"' : '',
				'S_SMILIES_CHECKED'			=> (!$this->stored_post['data']['enable_smilies'])	? ' checked="checked"' : '',
				'S_MAGIC_URL_CHECKED'		=> (!$this->stored_post['data']['enable_urls'])	? ' checked="checked"' : '',
				'S_SIGNATURE_CHECKED'		=> ($this->stored_post['data']['enable_sig'])		? ' checked="checked"' : '',
				'S_NOTIFY_CHECKED'			=> ($this->stored_post['data']['notify'])			? ' checked="checked"' : '',
				'S_LOCK_TOPIC_CHECKED'		=> ($this->stored_post['data']['topic_status'] == ITEM_LOCKED) ? ' checked="checked"' : '',
				'S_LOCK_POST_CHECKED'		=> ($this->stored_post['data']['post_edit_locked'] == ITEM_LOCKED) ? ' checked="checked"' : '',
			]);
		}

		$style = ($this->user->style['style_path'] == 'prosilver' || !file_exists("{$this->ext_path}styles/{$this->user->style['style_path']}")) ? 'prosilver' : $this->user->style['style_path'];

		$this->template->assign_vars([
			'SCHEDULE_POST_IMG_PATH'	=> "{$this->ext_web_path}styles/$style/theme/images/",
			'SCHEDULE_POST_ICO_PATH'	=> "{$this->ext_web_path}images/",
			'SCHEDULE_POST_ALT_COLORS'	=> $this->alternate_colors,
			'SCHEDULE_POST_ALT_ICONS'	=> $this->alternate_icons,
		]);

		// @OPTION 1 Replace URLs in attachment list under the posting form
		// $tpldata = &$GLOBALS['phpbb_container']->get('template_context')->get_data_ref();
		// foreach ($tpldata['attach_row'] as $i => &$a)
			// $a['U_VIEW_ATTACHMENT'] = $this->router->route('nekstati_schedulePost_file', ['id' => $a['ATTACH_ID']]);
	}

	public function acp_add_permission($event)
	{
		$this->user->add_lang_ext('nekstati/schedulePost', 'common');
		$event['categories'] += ['schedule_post' => 'ACL_F_SCHEDULE_CAT'];
		$event['permissions'] += [
			'f_schedule_post' => ['lang' => 'ACL_F_SCHEDULE_POST', 'cat' => 'schedule_post'],
			'f_schedule_view' => ['lang' => 'ACL_F_SCHEDULE_VIEW', 'cat' => 'schedule_post'],
		];
	}

	private function delete_item($id, $data)
	{
		if (confirm_box(true))
		{
			$this->db->sql_query("DELETE FROM {$this->table_prefix}topics_scheduled WHERE item_id = $id");

			if (sizeof($data['data']['attachment_data']))
			{
				$GLOBALS['phpbb_container']->get('attachment.manager')->delete('attach', array_column($data['data']['attachment_data'], 'attach_id'), false);
			}

			$forum_url = append_sid("{$this->root_path}viewforum.php", "f={$data['forum_id']}");
			meta_refresh(3, $forum_url);
			trigger_error($this->user->lang['SCHEDULE_POST_DELETED'] . '<br /><br />' . $this->user->lang('RETURN_FORUM', "<a href=\"$forum_url\">", '</a>'));
		}
		else
		{
			confirm_box(false, $this->user->lang['SCHEDULE_POST_DELETE_CONFIRM'], build_hidden_fields([
				'schedule_post_delete_item'		=> $id,
			]));
		}
	}

	private function publish($post)
	{
		if (!function_exists('submit_post'))
		{
			include_once $this->root_path . 'includes/functions_posting.php';
		}

		// And the greatest workaround, ladies and gentlemen!
		// We cannot pass custom user data to submit_post() function, which depends entirely on current user data & auth, so let's try to substitute current user with the topic author

		$vice_user_data = [];

		if ($post['post_data']['poster_id'] != $this->user->data['user_id'])
		{
			// Get topic author data; fallback to guest -> any founder -> lastly to the current user
			foreach (['user_id = ' . $post['post_data']['poster_id'], 'user_id = ' . ANONYMOUS, 'user_type = ' . USER_FOUNDER] as $where)
			{
				$sql = 'SELECT * FROM ' . USERS_TABLE . " WHERE $where";
				$result = $this->db->sql_query_limit($sql, 1);
				$vice_user_data = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				if ($vice_user_data) break;
			}
		}

		if ($vice_user_data)
		{
			$real_user_data = $this->user->data;
			$this->user->data = array_replace($this->user->data, $vice_user_data);
			$this->user->data['is_registered'] = ($this->user->data['user_id'] != ANONYMOUS && ($this->user->data['user_type'] == USER_NORMAL || $this->user->data['user_type'] == USER_FOUNDER));
			$this->user->ip = $post['data']['poster_ip'];
			$this->auth->acl($this->user->data);
		}

		// Precautions to make sure submit_post() publishes attachments correctly
		if (sizeof($post['data']['attachment_data']))
		{
			$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
				SET is_orphan = 1, poster_id = ' . $this->user->data['user_id'] . '
				WHERE ' . $this->db->sql_in_set('attach_id', array_column($post['data']['attachment_data'], 'attach_id'));
			$this->db->sql_query($sql);

			foreach ($post['data']['attachment_data'] as &$d) $d['is_orphan'] = 1;
		}

		$new_topic_url = submit_post('post', $post['post_data']['post_subject'], $post['post_author_name'], $post['post_data']['topic_type'], $post['poll'], $post['data']);

		if ($vice_user_data)
		{
			$this->user->data = $real_user_data;
			$this->user->ip = $this->user->data['user_ip'];
			$this->auth->acl($this->user->data);
		}

		return $new_topic_url;
	}

	private function check_date($date, $format = 'Y-m-d')
	{
		$stamp = strtotime($date);
		return $stamp && date($format, $stamp) == $date;
	}

	// Not in use currently. Has been tried in a heavy fight against Plupload errors
	// @TODO Remove if no Plupload issues
	static public function attach_error_handler($errno, $msg_text, $errfile, $errline)
	{
		if ($errno == E_USER_ERROR && $msg_text == 'NO_ACCESS_ATTACHMENT')
		{
			return true;
		}

		msg_handler($errno, $msg_text, $errfile, $errline);
	}
}

// Do not follow my way, developer! Just write your stuff from scratch, entirely separate from phpBB code. Use it as nothing more than a framework. Forget forever about its "listeners" and don't even try to "extend" its legacy scripts. Otherwise you will spend much more time, power and headaches. Any attempt to "extend" this old junk is not a work, but a workaround.
