<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron_handler_model extends CI_Model {
	private $task = null;
	private $date;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('utilities_model', 'util');
		$this->date = date('Y-m-d');
	}

	public function set_task( $task = null )
	{
		if(!$task){ 
			throw new Exception("Error: No task was found under the given name", 1);
		}
		$this->task = $task;
	}

	public function generate_thread( $task_name )
	{
		$task = $this->util->get('cron_tasks', ['name'=>$task_name]);
		$this->set_task( $task );
		$tracking = $this->util->get('cron_task_tracking', [
			'cron_task_id'=>$task->id,
			'date'=>$this->date
		]);
		if( $tracking && $tracking->status == 'ENDED' ){
			return (Object)[
				"status" 	=> "ENDED",
				"message" 	=> "The thread excecution was successful"
			];
		}
		$providers = $this->get_total_providers();
		exit( json_encode( $providers ) );
	}

	public function get_total_providers()
	{
		return $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->count_all_results();
	}

}

/* End of file Cron_handler_model.php */
/* Location: ./application/models/Cron_handler_model.php */