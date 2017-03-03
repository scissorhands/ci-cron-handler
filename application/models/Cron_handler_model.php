<?php
namespace Scissorhands\application\models;
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron_handler_model extends \CI_Model {
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

	private function set_task( $id = null )
	{
		if(!$id){ 
			throw new Exception("Error: Task id is mandatory", 1);
		}
		$task = $this->util->get('cron_tasks', ['id'=>$id]);
		if(!$task){
			throw new Exception("Error: Task not found", 1);
		}
		$this->task = $task;
	}

	public function run_task( $id = null )
	{
		$this->set_task( $id );
		$unfinished = $this->get_unfinished_dependencies();
		if($unfinished){
			$this->task->unfinished_dependencies = $unfinished;
			return $this->task;
		}
		$this->task_tracking = $this->util->get('cron_task_tracking', [
			'cron_task_id'=>$this->task->id,
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
					foreach ($unfinished_threads as $unfinished) {
						$unfinished->last_log = $this->get_last_thread_tracking( $unfinished->id );
					}
					sleep(15);
					foreach ($unfinished_threads as $unfinished) {
						$last_log = $this->get_last_thread_tracking( $unfinished->id );
						if( $unfinished->last_log && $last_log->current == $unfinished->last_log->current ){
							$unfinished->current = $last_log->current;
							$this->thread = $unfinished;
							break;
						} else {
							$unfinished->current = null;
							$this->thread = $unfinished;
							break;
						}
					}
				}
				break;
			case 'ENDED':
				// Do nothing
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

	private function status_check()
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

	private function get_fired_threads()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id',$this->task_tracking->id)
		->get();
		return $query->result();
	}

	private function get_unfinished_threads()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id', $this->task_tracking->id)
		->where('done', false)
		->get();
		return $query->result();
	}

	private function get_last_thread_tracking( $thread_id )
	{
		$query = $this->db->where('thread_id',$thread_id)
		->from('cron_task_thread_tracking')
		->order_by('current,log_timestamp', 'DESC')
		->get();
		return $query->result() ? $query->row() : null;
	}

	private function update_tracking_status( $status )
	{
		$this->util->generic_update('cron_task_tracking', 
			['id'=>$this->task_tracking->id], 
			['status'=>$status]
		);
		$this->task_tracking = $this->db->where('id', $this->task_tracking->id)->get('cron_task_tracking')->row();
	}

	private function run_thread()
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
		$error_flag = false;

		while ($index != $this->thread->to && $index < $this->thread->to) {
			$next_index = $index+$offset;
			$next_index = $next_index < $this->thread->to? $next_index : $this->thread->to;

			if( $this->task->provider_table || $this->task->custom_provider ){
				$provider_rows = $this->get_paginated_providers($index, $offset);
				foreach ($provider_rows as $row) {
					try {
						$this->etl_model->{$this->task->etl_function}( $row, $this->date );
					} catch (Exception $e) {
						// Log this error;
						$error_flag = true;
						break 2;
					}
				}
			} else {
				$this->etl_model->{$this->task->etl_function}( $this->date );
			}
			if(!$this->debug_mode){
				$this->insert_thread_log( $next_index );
			}
			$index = $next_index;
		}
		if( !$this->debug_mode && !$error_flag ){
			$this->set_thread_done();
		}
	}

	private function set_thread_done()
	{
		$where_clause = ['id'=>$this->thread->id];
		$this->util->generic_update('cron_task_threads', $where_clause, ['done' => true]);
		$this->thread = $this->util->get('cron_task_threads', $where_clause );
	}

	private function insert_thread_log( $current )
	{
		$this->util->generic_insert('cron_task_thread_tracking', [
			'thread_id' => $this->thread->id,
			'current' => $current
		]);
	}

	private function init_thread()
	{
		$this->thread = $this->get_last_started_thread();
		$range = $this->get_range();
		$new_thread = [
			'cron_task_tracking_id' => $this->task_tracking->id,
			'from' => $range->from,
			'to' => $range->to,
			'started_at' => date('Y-m-d H:i:s')
		];
		if( !$this->debug_mode ){
			$new_thread['id'] = $this->util->generic_insert('cron_task_threads', $new_thread);
		}
		$new_thread['current'] = null;
		$this->thread = (Object)$new_thread;
	}

	private function get_last_started_thread()
	{
		$query = $this->db->from('cron_task_threads')
		->where('cron_task_tracking_id',$this->task_tracking->id)
		->order_by('started_at', 'desc')->get();
		return $query->result()? $query->row() : null;
	}

	private function get_range()
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
		if( $this->task->provider_table || $this->task->custom_provider ){
			$total_providers = $this->get_total_providers();
			$total_threads = ceil($total_providers / $this->task->thread_interval);
		} else {
			$total_providers = 1;
			$total_threads = 1; 
		}
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

	private function get_total_providers()
	{
		$query = $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->get();
		return $query->num_rows();
	}

	private function get_paginated_providers($index, $offset)
	{
		return $this->db->from("{$this->task->provider_table} AS provider")
		->group_by("provider.{$this->task->provider_id}")
		->order_by("provider.{$this->task->provider_id}", 'ASC')
		->limit($offset, $index)
		->get()->result();
	}

	public function reset_task( $id )
	{
		$task = $this->util->get('cron_tasks', ['id'=>$id]);
		if(!$task){ throw new Exception("Unknown task", 1);
		 }
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
		return $task;
	}

	public function monitor( $id )
	{
		$task = $this->util->get('cron_tasks', ['id'=>$id]);
		$task->tracking = $this->util->get('cron_task_tracking', [
			'cron_task_id'=>$task->id,
			'date'=>$this->date
		]);
		if( $task->tracking ){
			$task->tracking->threads = $this->util->get('cron_task_threads', ['cron_task_tracking_id'=> $task->tracking->id], true);
			if($task->tracking->threads){
				foreach ($task->tracking->threads as $thread) {
					$logs = $this->util->get('cron_task_thread_tracking', ['thread_id'=>$thread->id], true);
					if($logs){
						$thread->logs = (Object)[
							'total' => count($logs),
							'last' => end($logs)
						];
					} else {
						$thread->logs = null;
					}
				}
			}
		}
		return $task;
	}

	public function get_tasks()
	{
		$tasks = $this->util->get('cron_tasks', [], true);
		return $tasks;
	}

	public function get_filtered( $fields = 'id, name', $filter = null, $start = null, $length = null, $sorting = null )
	{
		if($filter){
			$where_filter = "name LIKE '%%' OR provider_table LIKE '%%' OR etl_function LIKE '%%'";
		}
		$this->db->select($fields, false)
		->from("cron_tasks AS CT");

		if( $filter && $start != null && $length ){
			$this->db->where($where_filter)
			->limit($length, $start);
		} else if( $start != null && $length ){
			$this->db->limit($length, $start);
		} else if( $filter ){
			$this->db->where($where_filter);
		}
		if( $sorting ){
			$this->db->order_by($sorting['field'], $sorting['sort_dir']);
		}
		$query = $this->db->get(); 
		return $query->result();
	}

	public function get_unfinished_dependencies()
	{
		$unfinished = $this->get_dependencies_status();
		$dependencies = [];
		foreach ($unfinished as $row) {
			if(!$row->done){
				$dependencies[] = $this->get_task_status($row->id);
			}
		}
		return $dependencies;
	}

	public function get_task_status( $id )
	{
		$query = $this->db->select('CT.name AS task_name, CTT.status')
		->from('cron_tasks AS CT')
		->join("(
			SELECT * 
			FROM cron_task_tracking
			WHERE date = '{$this->date}'
			) AS CTT",'CT.id=CTT.cron_task_id', 'left')
		->get();
		return $query->result() ? $query->row() : null;
	}

	public function get_dependencies_status()
	{
		$query = $this->db->select('CT.id, CT.name, CTD.dependency_task_id AS dependency, CTT.id AS done', false)
		->from('cron_tasks AS CT')
		->join('cron_task_dependencies AS CTD', 'CT.id=CTD.dependant_task_id')
		->join("(
			SELECT * 
			FROM cron_task_tracking
			WHERE date = '{$this->date}'
			AND status = 'ENDED'
			) AS CTT", 'CTD.dependency_task_id=CTT.cron_task_id', 'left')
		->where('CT.id', $this->task->id)
		->get();
		return $query->result();
	}

}

/* End of file Cron_handler_model.php */
/* Location: ./application/models/Cron_handler_model.php */