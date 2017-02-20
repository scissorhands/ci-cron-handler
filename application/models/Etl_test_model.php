<?php
namespace Scissorhands\CiCronHandler\Models;
defined('BASEPATH') OR exit('No direct script access allowed');

class Etl_test_model extends \CI_Model {

	public function do_etl( $account, $date )
	{
		// sleep(1);
		$rand = mt_rand() / mt_getrandmax()*999.99;
		$this->util->generic_insert('test_provider_stats', [
			'test_provider_id' => $account->id,
			'stats_date' => $date,
			'value' => $rand
		]);
		// $this->random_fail();
	}

	public function random_fail()
	{
		$rand = rand(1,1000);
		if( $rand === 1 ){
			throw new Exception("This process stopped unexpectedly $rand", 1);
		}
	}

}

/* End of file Etl_test_model.php */
/* Location: ./application/models/Etl_test_model.php */