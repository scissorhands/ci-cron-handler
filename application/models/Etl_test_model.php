<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Etl_test_model extends CI_Model {

	public function do_etl( $account, $date )
	{
		$rand = mt_rand() / mt_getrandmax()*999.99;
		$this->util->generic_insert('test_provider_stats', [
			'test_provider_id' => $account->id,
			'stats_date' => $date,
			'value' => $rand
		]);
	}

}

/* End of file Etl_test_model.php */
/* Location: ./application/models/Etl_test_model.php */