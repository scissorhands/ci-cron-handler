<?php
namespace Scissorhands\CiCronHandler\Controllers;
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
			dump( $data, false );
		}
	}

	public function reset( $id = null )
	{
		if($id){
			$data = $this->cron_handler->reset_task('ETL example');
			dump( $data, false );
		}
	}

	public function get_tasks()
	{
		$tasks = $this->cron_handler->get_tasks();
		dump( $tasks, false );
	}

	public function monitor( $id = 1 )
	{
		$data = $this->cron_handler->monitor($id);
		dump( $data, false );
	}

}

/* End of file Retriever.php */
/* Location: ./application/controllers/Retriever.php */