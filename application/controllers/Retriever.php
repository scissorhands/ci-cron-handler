<?php
namespace Scissorhands\application\controllers;
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(__DIR__.'/../../../ci-utilities/helpers/utilities.php');
class Retriever extends \CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('cron_handler_model', 'cron_handler');
	}

	public function run( $id = null )
	{
		if($id){
			$data = $this->cron_handler->run_task($id);
			cron_response( $data );
		}
	}

	public function run_by_name( $name = null )
	{
		if($name){
			$task = $this->util->get('cron_tasks', ['name'=>$name]);
			if($task){
				$this->run( $task->id );
			} else {
				throw new Exception("Unknown task", 1);
			}
		}
	}

	public function reset( $id = null )
	{
		if($id){
			$data = $this->cron_handler->reset_task($id);
			cron_response( $data );
		}
	}

	public function get_tasks()
	{
		$tasks = $this->cron_handler->get_tasks();
		cron_response( $tasks );
	}

	public function monitor( $id = 1 )
	{
		$data = $this->cron_handler->monitor($id);
		cron_response( $data );
	}

}

/* End of file Retriever.php */
/* Location: ./application/controllers/Retriever.php */