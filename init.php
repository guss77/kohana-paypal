<?php

/**
 * Set the routes for capturing PayPal responses
 */
Route::set('paypal_response', 'paypal-response/<action>(/<trxid>)')
	->defaults([
		'controller' => 'PayPal'
	]);
