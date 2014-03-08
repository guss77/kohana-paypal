<?php defined('SYSPATH') or die('No direct script access.');

class PayPal_Exception_Configuration extends PayPal_Exception {
	
	public function __construct($field) {
		parent::__construct("Missing configuration field: " . $field);
	}
}
