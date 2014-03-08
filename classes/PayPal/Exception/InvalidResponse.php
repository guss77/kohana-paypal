<?php defined('SYSPATH') or die('No direct script access.');

class PayPal_Exception_InvalidResponse extends PayPal_Exception {
	public function __construct($message) {
		parent::__construct($message);
	}
}
