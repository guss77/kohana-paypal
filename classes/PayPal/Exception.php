<?php defined('SYSPATH') or die('No direct script access.');

class PayPal_Exception extends Exception {
	
	public function __construct($message, $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
