<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron_handler_model extends CI_Model {
	private $task = null;
	private $task_tracking = null;
	private $thread = null;
	private $date;

	private $debug_mode = false;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('utilities_model', 'util');
		$this->date = date('Y-m-d');
	}

	public function debug_mode()
	{
		$this->debug_mode = true;
	}

	public function set_date( $date )
	{
		$this->date = $date;
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
		$this->task_tracking = $this->util->get('cron_task_tracking', [
			'cron_task_id'=>$task->id,
			'date'=>$this->date
		]);
		if( !$this->task_tracking ){
			$this->task_tracking = $this->init_tracking();
		}
		switch ($this->task_tracking->status) {
			case 'UNSTARTED':
				$this->init_thread();
				$this->update_tracking_status('STARTED');
				break;
			case 'STARTED':
				$this->init_thread();
				break;
			case 'ENDED':
				return (Object)[
					"status" => "ENDED",
					"message" => "The thread excecution was successful"
				];
				break;
			case 'ERROR':
				break;
			default:
				exit('Uncaught tracking status');
				break;
		}

		$this->run_thread();
	}

	public function update_tracking_status( $status )
	{
		$this->util->generic_update('cron_task_tracking', 
			['id'=>$this->task_tracking->id], 
			['status'=>$status]
		);
	}

	public function run_thread()
	{
		try {
			$this->load->model( $this->task->etl_model, 'etl_model');
		} catch (Exception $e) {
			exit(json_encode(['error'=>$e->getMessage()]));
		}
		$index = $this->thread->from;
		$offset = $this->task->thread_tracking_interval? $this->task->thread_tracking_interval: $this->thread->to;

		while ($index != $this->thread->to && $index < $this->thread->to) {
			$next_index = $index+$offset;
			$next_index = $next_index < $this->thread->to? $next_index : $this->thread->to;
			$provider_rows = $this->get_paginated_providers($index, $next_index);
			foreach ($provider_rows as $row) {
				$this->etl_model->{$this->task->etl_function}( $row );
			}
			if(!$this->debug_mode){
				$this->insert_thread_log( $next_index );
			}
			$index = $next_index;
		}
	}

	public function insert_thread_log( $current )
	{
		$this->util->generic_insert('cron_task_thread_tracking', [
			'thread_id' => $this->thread->id,
			'current' => $current
		]);
	}

	public function init_thread()
	{
		$threads = $this->util->get('cron_task_threads', ['cron_task_tracking_id' => $this->task_tracking->id], true);
		if( !$threads || count($threads) < $this->task_tracking->total_threads ){
			$from = 0;
			$to = $this->task->thread_interval <= $this->task_tracking->total_rows ? 
				$this->task->thread_interval : 
				$this->task_tracking->total_rows;
			$new_thread = [
				'id' => uniqid(),
				'cron_task_tracking_id' => $this->task_tracking->id,
				'from' => $from,
				'to' => $to
			];
			if( !$this->debug_mode ){
				$id = $this->util->generic_insert('cron_task_threads', $new_thread);
			}
			$this->thread = (Object)$new_thread;
		}
	}

	private function init_tracking()
	{
		$total_providers = $this->get_total_providers();
		$total_threads = ceil($total_providers / $this->task->thread_interval);
		$new_task_tracking = [
			'cron_task_id' => $this->task->id,
			'date' => $this->date,
			'total_rows' => $total_providers,
			'total_threads' => $total_threads,
			'status' => 'UNSTARTED',
			'started_at' => date('Y-m-d H:i:s')
		];
		$id = $this->util->generic_insert('cron_task_tracking', $new_task_tracking);
		return $this->util->get('cron_task_tracking', ['id' => $id]);
	}

	public function get_total_providers()
	{
		return $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->count_all_results();
	}

	public function get_paginated_providers($index, $limit)
	{
		return $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->order_by("provider.{$this->task->provider_id}", 'ASC')
		->limit($limit, $index)
		->get()->result();
	}

}

/* End of file Cron_handler_model.php */
/* Location: ./application/models/Cron_handler_model.php */