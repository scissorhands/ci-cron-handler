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

	public function run_task( $task_name )
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
				$threads = $this->get_fired_threads();
				if( count($threads) < $this->task_tracking->total_threads ){
					$this->init_thread();
				} else {
					$unfinished_threads = $this->get_unfinished_threads();
					$thread_indexes = [];
					foreach ($unfinished_threads as $unfinished) {
						$last_log = $this->get_last_thread_tracking( $unfinished->id );
						dump( $last_log, false );
						// $thread_indexes[$unfinished->id] = 
					}
					sleep(10);
					foreach ($unfinished_threads as $unfinished) {
						$last_log = $this->get_last_thread_tracking( $unfinished->id );
						dump( $last_log, false );
						if( $last_log->current == $thread_indexes[$unfinished->id] ){
							$unfinished->current = $last_log->current;
							$this->thread = $unfinished;
							continue;
						}
						// $thread_indexes[$unfinished->id] = 
					}
				}
				break;
			case 'ENDED':
				break;
			case 'ERROR':
				break;
			default:
				exit('Uncaught tracking status');
				break;
		}
		$this->status_check();
		if($this->task_tracking->status != 'ENDED'){
			$this->run_thread();
		}
		$this->status_check();
		return (Object) [
			'task' => $this->task,
			'task_tracking' => $this->task_tracking,
			'thread' => $this->thread
		];
	}

	public function status_check()
	{
		$threads = $this->get_fired_threads();
		$unfinished_threads = $this->get_unfinished_threads();
		if($this->task_tracking->total_threads == count($threads) && !$unfinished_threads){
			$this->update_tracking_status('ENDED');
		}
		$fired = count($threads);
		$unfinished = count($unfinished_threads);
		$this->task_tracking->progress = (Object)[
			'fired' => $fired,
			'unfinished' => $unfinished
		];
	}

	public function get_fired_threads()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id',$this->task_tracking->id)
		->get();
		return $query->result();
	}

	public function get_unfinished_threads()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id', $this->task_tracking->id)
		->where('done', false)
		->get();
		return $query->result();
	}

	public function get_last_thread_tracking( $thread_id )
	{
		$query = $this->db->where('id',$thread_id)
		->from('cron_task_thread_tracking')
		->order_by('started_at', 'DESC')
		->get();
		return $query->result() ? $query->row() : null;
	}

	public function update_tracking_status( $status )
	{
		$this->util->generic_update('cron_task_tracking', 
			['id'=>$this->task_tracking->id], 
			['status'=>$status]
		);
		$this->task_tracking = $this->db->where('id', $this->task_tracking->id)->get('cron_task_tracking')->row();
	}

	public function run_thread()
	{
		try {
			$this->load->model( $this->task->etl_model, 'etl_model');
		} catch (Exception $e) {
			exit(json_encode(['error'=>$e->getMessage()]));
		}
		$index = $this->thread->current? $this->thread->current : $this->thread->from;
		$offset = $this->task->thread_tracking_interval? 
			$this->task->thread_tracking_interval: 
			$this->thread->to;

		while ($index != $this->thread->to && $index < $this->thread->to) {
			$next_index = $index+$offset;
			$next_index = $next_index < $this->thread->to? $next_index : $this->thread->to;

			$provider_rows = $this->get_paginated_providers($index, $offset);
			foreach ($provider_rows as $row) {
				$this->etl_model->{$this->task->etl_function}( $row, $this->date );
			}
			if(!$this->debug_mode){
				$this->insert_thread_log( $next_index );
			}
			$index = $next_index;
		}
		if( !$this->debug_mode ){
			$this->set_thread_done();
		}
	}

	public function set_thread_done()
	{
		$where_clause = ['id'=>$this->thread->id];
		$this->util->generic_update('cron_task_threads', $where_clause, ['done' => true]);
		$this->thread = $this->util->get('cron_task_threads', $where_clause );
	}

	public function insert_thread_log( $current )
	{
		$this->util->generic_insert('cron_task_thread_tracking', [
			'thread_id' => $this->thread->id,
			'current' => $current
		]);
	}

	public function init_thread( $from = 0 )
	{
		$this->thread = $this->get_last_started_thread();
		$range = $this->get_range();
		$new_thread = [
			'id' => uniqid(),
			'cron_task_tracking_id' => $this->task_tracking->id,
			'from' => $range->from,
			'to' => $range->to,
			'started_at' => date('Y-m-d H:i:s')
		];
		if( !$this->debug_mode ){
			$id = $this->util->generic_insert('cron_task_threads', $new_thread);
		}
		$new_thread['current'] = null;
		$this->thread = (Object)$new_thread;
	}

	public function get_last_started_thread()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id',$this->task_tracking->id)
		->order_by('started_at', 'desc')->get();
		return $query->result()? $query->row() : null;
	}

	public function get_range()
	{
		if(!$this->thread) {
			$from = 0;
			$to = $this->task->thread_interval <= $this->task_tracking->total_rows ? 
				$this->task->thread_interval : 
				$this->task_tracking->total_rows;
		} else {
			$from = $this->thread->to;
			$to = $from + $this->task->thread_interval <= $this->task_tracking->total_rows ? 
				$from + $this->task->thread_interval : 
				($from + $this->task->thread_interval) + ($this->task_tracking->total_rows - ($from + $this->task->thread_interval));
		}
		return (Object)["from"=>$from,"to"=>$to];
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

	public function get_paginated_providers($index, $offset)
	{
		return $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->order_by("provider.{$this->task->provider_id}", 'ASC')
		->limit($offset, $index)
		->get()->result();
	}

	public function reset_task( $task_name )
	{
		$task = $this->util->get('cron_tasks', ['name'=>$task_name]);
		$task->tracking = $this->util->get('cron_task_tracking', [
			'cron_task_id'=>$task->id,
			'date'=>$this->date
		]);
		if( $task->tracking ){
			$task->tracking->threads = $this->util->get('cron_task_threads', ['cron_task_tracking_id'=> $task->tracking->id], true);
			if($task->tracking->threads){
				foreach ($task->tracking->threads as $thread) {
					$this->util->generic_delete('cron_task_thread_tracking', ['thread_id'=>$thread->id]);
				}
				$this->util->generic_delete('cron_task_threads', ['cron_task_tracking_id'=> $task->tracking->id]);
			}
			$this->util->generic_delete('cron_task_tracking', [
				'cron_task_id'=>$task->id,
				'date'=>$this->date
			]);
		}
		dump($task);
	}

}

/* End of file Cron_handler_model.php */
/* Location: ./application/models/Cron_handler_model.php */