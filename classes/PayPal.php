<?php defined('SYSPATH') or die('No direct script access.');

abstract class PayPal extends Kohana_PayPal { 
	
	abstract public function approved($localTrxID, $transcationID, $payerDetails, $salesData);
	
}
