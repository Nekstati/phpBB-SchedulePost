<?php

namespace nekstati\schedulePost\cron;

class publish extends \phpbb\cron\task\base
{
	protected $auth, $config, $db, $user, $root_path, $table_prefix;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, $root_path, $table_prefix)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->table_prefix = $table_prefix;
	}

	public function should_run()
	{
		return $this->config['schedulePost_last_gc'] < time() - $this->config['schedulePost_gc'];
	}

	public function run()
	{
		$current_time = time();

		$sql = "SELECT * FROM {$this->table_prefix}topics_scheduled WHERE publish_time <= $current_time";
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$post = json_decode($row['item_data'], true);

			$this->publish($post);

			$this->db->sql_query("DELETE FROM {$this->table_prefix}topics_scheduled WHERE item_id = {$row['item_id']}");
		}
		$this->db->sql_freeresult($result);

		$this->config->set('schedulePost_last_gc', $current_time, true);
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
}
