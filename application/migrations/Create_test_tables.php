<?php
namespace Scissorhands\application\migrations;
defined('BASEPATH') OR exit('No direct script access allowed');

class Create_test_tables extends \CI_Migration {

	public function __construct()
	{
		$this->load->dbforge();
		$this->load->database();
		$this->load->model('utilities_model', 'util');
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
		])->add_key('id', true);
		$this->dbforge->create_table('test_provider', true);

		$this->dbforge->add_field([
			'test_provider_id' => [
				'type' => 'INT',
				'constraint' => 11,
				'insigned' => true
			],
			'stats_date' => [
				'type' => 'DATE'
			],
			'value' => [
				'type' => 'DOUBLE',
				'insigned' => true
			],
		])->add_key(['test_provider_id', 'stats_date'], true);
		$this->dbforge->create_table('test_provider_stats', true);

		$faker = \Faker\Factory::create();
		for( $i =0 ; $i<1500 ; $i++){
			$this->util->generic_insert('test_provider', [
				'name' => $faker->name
			]);
		}

		$this->util->generic_insert('cron_tasks',[
			'name' => 'ETL example',
			'provider_table' => 'test_provider',
			'provider_id' => 'id',
			'thread_interval' => 200,
			'thread_tracking_interval' => 10,
			'etl_model' => 'etl_test_model',
			'etl_function' => 'do_etl'
		]);
	}

	public function down() {
		$this->dbforge->drop_table('test_provider', true);
		$this->dbforge->drop_table('test_provider_stats', true);
	}

}

/* End of file 20170213131159_Create_test_tables.php */
/* Location: ./application/migrations/20170213131159_Create_test_tables.php */