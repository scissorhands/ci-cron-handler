<?php
namespace Scissorhands\application\controllers;
defined('BASEPATH') OR exit('No direct script access allowed');
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
			exit( json_encode($data) );
		}
	}

	public function reset( $id = null )
	{
		if($id){
			$data = $this->cron_handler->reset_task('ETL example');
			exit( json_encode($data) );
		}
	}

	public function get_tasks()
	{
		$tasks = $this->cron_handler->get_tasks();
		exit( json_encode($tasks) );
	}

	public function monitor( $id = 1 )
	{
		$data = $this->cron_handler->monitor($id);
		exit( json_encode($data) );
	}

}

/* End of file Retriever.php */
/* Location: ./application/controllers/Retriever.php */