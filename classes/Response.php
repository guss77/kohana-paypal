<?php defined('SYSPATH') or die('No direct script access.');

class Response extends Kohana_Response {

	public function isSuccess() {
		return (int)($this->status() / 100) == 2;
	}
	
}
