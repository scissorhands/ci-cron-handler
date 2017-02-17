<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Retriever extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('cron_handler_model', 'cron_handler');
	}

	public function index()
	{
		// $this->cron_handler->debug_mode();
		$data = $this->cron_handler->run_task('ETL example');
		dump( $data, false );
	}


	public function reset()
	{
		$this->cron_handler->reset_task('ETL example');
		
	}



}

/* End of file Retriever.php */
/* Location: ./application/controllers/Retriever.php */