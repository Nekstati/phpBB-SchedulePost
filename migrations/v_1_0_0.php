<?php

namespace nekstati\schedulePost\migrations;

class v_1_0_0 extends \phpbb\db\migration\container_aware_migration
{
	public function effectively_installed()
	{
		return isset($this->config['schedulePost_version']) && version_compare($this->config['schedulePost_version'], '1.0.0', '>=');
	}

	static public function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\dev'];
	}

	public function update_schema()
	{
		return [
			'add_tables'		=> [
				$this->table_prefix . 'topics_scheduled'		=> [
					'COLUMNS'		=> [
						'item_id'			=> ['UINT', null, 'auto_increment'],
						'forum_id'			=> ['UINT', 0],
						'poster_id'			=> ['UINT', 0],
						'publish_time'		=> ['TIMESTAMP', 0],
						'item_data'			=> ['MTEXT_UNI', ''],
					],
					'PRIMARY_KEY'	=> 'item_id',
					'KEYS'			=> [
						'forum_id'			=> ['INDEX', 'forum_id'],
						'poster_id'			=> ['INDEX', 'poster_id'],
						'publish_time'		=> ['INDEX', 'publish_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables'		=> [
				$this->table_prefix . 'topics_scheduled',
			],
		];
	}

	public function update_data()
	{
		$data = [
			['config.add', ['schedulePost_gc', '3600']],
			['config.add', ['schedulePost_last_gc', '0', '1']],
			['config.add', ['schedulePost_version', '1.0.0']],
			['permission.add', ['f_schedule_post', '0']],
			['permission.add', ['f_schedule_view', '0']],
		];

		$request = $this->container->get('request');
		$cnf_schedule_post = $request->variable('cnf_schedule_post', 1);
		$cnf_schedule_view = $request->variable('cnf_schedule_view', 1);

		if ($cnf_schedule_post >= 1 && $this->role_exists('ROLE_FORUM_FULL'))
		{
			$data[] = ['permission.permission_set', ['ROLE_FORUM_FULL', 'f_schedule_post']];
		}
		if ($cnf_schedule_post == 2 && $this->role_exists('ROLE_FORUM_STANDARD'))
		{
			$data[] = ['permission.permission_set', ['ROLE_FORUM_STANDARD', 'f_schedule_post']];
		}
		if ($cnf_schedule_post == 2 && $this->role_exists('ROLE_FORUM_POLLS'))
		{
			$data[] = ['permission.permission_set', ['ROLE_FORUM_POLLS', 'f_schedule_post']];
		}
		if ($cnf_schedule_view == 1 && $this->role_exists('ROLE_FORUM_FULL'))
		{
			$data[] = ['permission.permission_set', ['ROLE_FORUM_FULL', 'f_schedule_view']];
		}

		return $data;
	}

	public function revert_data()
	{
		return [];
	}

	protected function role_exists($name)
	{
		$sql = 'SELECT role_id
			FROM ' . ACL_ROLES_TABLE . "
			WHERE role_name = '" . $this->db->sql_escape($name) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		return $role_id;
	}
}
