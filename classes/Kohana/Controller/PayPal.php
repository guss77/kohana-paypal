<?php

class Kohana_Controller_PayPal extends Controller {
	
	public function complete() {
		var_dump($this->request);
	}

	public function cancel() {
		var_dump($this->request);
	}

}
