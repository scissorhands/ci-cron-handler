<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_cron_tables extends \CI_Migration {

	public function __construct()
	{
		$this->load->dbforge();
		$this->load->database();
	}

	public function up() {
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => true,
				'insigned' => true
			],
			'name' => [
				'type' => 'VARCHAR',
				'constraint' => 128,
				'unique' => true
			],
			'start_time' => [
				'type' => 'TIME',
				'null' => true,
				'default' => null
			],
			'alert_time' => [
				'type' => 'TIME',
				'null' => true,
				'default' => null
			],
			'custom_provider' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true,
				'default' => null
			],
			'provider_table' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true,
				'default' => null
			],
			'provider_id' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true,
				'default' => 'null'
			],
			'thread_interval' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true,
				'null' => true,
				'default' => null
			],
			'thread_tracking_interval' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true,
				'null' => true,
				'default' => null
			],
			'etl_model' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true,
				'default' => null
			],
			'etl_function' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true,
				'default' => null
			]
		])->add_key('id', true);
		$this->dbforge->create_table('cron_tasks', true);

		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => true,
				'insigned' => true
			],
			'cron_task_id' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'date' => [
				'type' => 'DATE'
			],
			'total_rows' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'total_processes' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'status' => [
				'type' => 'ENUM',
				'constraint' => [ 'UNSTARTED', 'STARTED', 'ENDED', 'ERROR']
			],
			'last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
			'started_at TIMESTAMP DEFAULT NULL'
		])
		->add_key('id', true)
		->add_key(['cron_task_id', 'date'])
		->add_key('status');
		$this->dbforge->create_table('cron_task_tracking', true);

		$this->dbforge->add_field([
			'id' => [
				'type' => 'VARCHAR',
				'constraint' => 32
			],
			'cron_task_tracking_id' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'from' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'to' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'done' => [
				'type' => 'TINYINT',
				'default' => false
			],
			'last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		])
		->add_key('id', true)
		->add_key(['cron_task_tracking_id', 'date'])
		->add_key('done');
		$this->dbforge->create_table('cron_task_threads', true);

		$this->dbforge->add_field([
			'thread_id' => [
				'type' => 'VARCHAR',
				'constraint' => 32
			],
			'current' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
		])
		->add_key(['thread_id', 'current'], true);
		$this->dbforge->create_table('cron_task_thread_tracking', true);
	}

	public function down() {
		$this->dbforge->drop_table('cron_tasks', true);
		$this->dbforge->drop_table('cron_task_tracking', true);
		$this->dbforge->drop_table('cron_task_threads', true);
		$this->dbforge->drop_table('cron_task_thread_tracking', true);
	}

}

/* End of file 20170210174159_Create_cron_tables.php */
/* Location: ./application/migrations/20170210174159_Create_cron_tables.php */