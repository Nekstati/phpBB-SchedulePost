<?php

namespace nekstati\schedulePost\controller;

class file
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

	public function send()
	{
		$attach_id = $this->request->variable('id', 0);
		$thumbnail = $this->request->variable('t', false);

		$this->user->add_lang('viewtopic');

		if (!$this->config['allow_attachments'])
		{
			send_status_line(404, 'Not Found');
			trigger_error('ATTACHMENT_FUNCTIONALITY_DISABLED');
		}

		if (!$attach_id)
		{
			send_status_line(404, 'Not Found');
			trigger_error('NO_ATTACHMENT_SELECTED');
		}

		require $this->root_path . 'includes/functions_download.php';

		$sql = 'SELECT attach_id, physical_filename, real_filename, extension, mimetype, filesize, filetime
			FROM ' . ATTACHMENTS_TABLE . "
			WHERE attach_id = $attach_id
			AND post_msg_id = 0";
		$result = $this->db->sql_query($sql);
		$attachment = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$attachment)
		{
			send_status_line(404, 'Not Found');
			trigger_error('ERROR_NO_ATTACHMENT');
		}

		$attachment['physical_filename'] = utf8_basename($attachment['physical_filename']);

		if ($thumbnail)
		{
			$attachment['physical_filename'] = 'thumb_' . $attachment['physical_filename'];
		}

		send_file_to_browser($attachment, $this->config['upload_path'], ATTACHMENT_CATEGORY_IMAGE);
		file_gc();
	}
}
