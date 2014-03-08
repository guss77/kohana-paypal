<?php defined('SYSPATH') or die('No direct script access.');

/*
 * Kohana 3.3 PayPal payment processing Module
 * Copyright (C) 2014 Oded Arbel
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * PayPal API implementation
 * To process payments, create a new instance of this class and call the 
 * {@link Kohana_PayPal#payment} method.
 * @author Oded Arbel <oded@geek.co.il>
 */
class Kohana_PayPal {

	const CACHE_TOKEN = 'paypal_cached_token';
	const SESSION_TOKEN = 'paypal_transaction_id';
	const MAX_SESSION_LENGTH = 3600;
	
	var $endpoint;
	var $clientID;
	var $secret;
	var $currency;
	var $cache;
	
	public static function payment($amount, $localTrxID = null) {
		$impl = new PayPal();
		$registration = $impl->registerTransaction($amount, $impl->storeLocalTrx($localTrxID));
		Session::instance()->set(self::SESSION_TOKEN, $registration->id);
		
		foreach ($registration->links as $link) {
			if ($link->rel == 'approval_url') {
				HTTP::redirect($link->href);
				exit; // shouldn't be needed, redirect throws
			}
		}
		throw new PayPal_Exception_InvalidResponse('Missing approval URL');
	}
	
	public static function execute($payerID, $localTrxID) {
		$trxid = Session::instance()->get_once(self::SESSION_TOKEN);
		$impl = new PayPal();
		$response = $impl->complete($trxid, $payerID);
		$localTrxID = $impl->retrieveLocalTrx($localTrxID);
		self::debug("Paypal response for " . $localTrxID, $response);
		$impl->approved($localTrxID, $response->id, $response->payer->payer_info);
	}
	
	public function __construct() {
		$config = Kohana::$config->load('paypal');
		
		$this->endpoint = $config['endpoint'];
		if (!isset($this->endpoint))
			throw new PayPal_Exception_Configuration("endpoint");
		
		$this->clientID = $config['clientId'];
		if (!isset($this->clientID))
			throw new PayPal_Exception_Configuration("client ID");
		
		$this->secret = $config['secret'];
		if (!isset($this->secret))
			throw new PayPal_Exception_Configuration("client secret");
		
		$this->currency = $config['currency'];
		if (!isset($this->currency))
			$this->currency = 'USD';
		
		$this->cache = Cache::instance();
		
		// garbage collect the cache (if needed)
		$gc = 5;
		if ($this->cache instanceof Cache_GarbageCollect and rand(0,99) <= $gc)
			$this->cache->garbage_collect();
	}

	public function authenticate() {
		if ($token = $this->cache->get(static::CACHE_TOKEN, FALSE) and 
				$token->acquired + $token->expires_in > time())
			return $token;
		
		$request = $this->genRequest('oauth2/token', [ 'grant_type' => 'client_credentials' ]);	
		$token = $this->call($request);
		
		$token->acquired = time();
		$this->cache->set(static::CACHE_TOKEN, $token, $token->expires_in);
		return $token;
	}

	public function registerTransaction($amount, $localTrxID) {
		$token = $this->authenticate();
		
		// paypal like the amount as string, to prevent floating point errors
		if (!is_string($amount))
			$amount = sprintf("%0.2f", $amount);
		
		$route = Route::get('paypal_response');
		$base = URL::base(true);
		$payment_data = (object)[
			'intent' => "sale",
			'redirect_urls' => (object)[
				'return_url' => $base . $route->uri([
						'action' => 'complete', 'trxid' => $localTrxID]),
				'cancel_url' => $base . $route->uri([
						'action' => 'cancel', 'trxid' => $localTrxID]),
			],
			'payer' => (object)[
    			'payment_method' => 'paypal',
    		],
    		"transactions" => [
    			(object)[
    				"amount" => (object)[
        				"total" => $amount,
        				"currency" => $this->currency,
        			]
        		]
        	],
        ];
		
		$request = $this->genRequest('payments/payment', $payment_data, $token);
		return $this->call($request);
	}
	
	public function complete($trxid, $payerid) {
		$token = $this->authenticate();
				
		$request = $this->genRequest('payments/payment/' . $trxid . '/execute/', 
				(object)[ 'payer_id' => $payerid ],
				$token);
		return $this->call($request);
	}
	
	private function storeLocalTrx($localTrxID) {
		if (is_null($localTrxID))
			return $localTrxID;
		
		// store the local transaction Id in the cache, so I don't have to pass it
		// through the client
		$trxco = sha1(time() . "" . $localTrxID);
		$this->cache->set($trxco, $localTrxID, self::MAX_SESSION_LENGTH);
		return $trxco;
	}
	
	private function retrieveLocalTrx($localTrxHash) {
		if (is_null($localTrxHash))
			return $localTrxHash;
		
		// retrueve the local transaction Id from the cache
		return $this->cache->get($localTrxHash, null);
	}
	
	protected function genRequest($address, $data = array(), $token = null) {
		$req = (new Request($this->endpoint . '/v1/' . $address))->method('POST')
			->headers('Accept','application/json')
			->headers('Accept-Language', 'en_US')
			->headers('Authorization', is_null($token) ?
				'Basic ' . base64_encode($this->clientID . ":" . $this->secret) :
				$token->token_type . ' ' . $token->access_token)
			->headers('Content-Type', is_array($data) ?
					'application/x-www-form-urlencoded' : 'application/json');
		
		self::debug("Sending message: ", print_r($data,true));
		if (is_array($data))
			return $req->post($data); // set all fields directly
		
		if (!is_object($data))
			throw new PayPal_Exception("Invalid data type in PayPal::genRequest");
		
		return $req->body(json_encode($data));
	}
	
	protected function call(Request $request) {
		$response = $request->execute();
		if (!$response->isSuccess()) {
			self::debug("Error in PayPal call", $response);			
			throw new PayPal_Exception_InvalidResponse("Error " . $response->status() . " in PayPal call");
		}
		$res = json_decode($response->body());
		
		if (isset($res->error)) {
			self::error("Error in PayPal call: " . print_r($res, true));			
			throw new PayPal_Exception_InvalidResponse('PayPal: ' . $res->error_description . ' [' . $res->error . 
					'] while calling ' . $request->uri());
		}
		return $res;
	}
	
	private static function debug($message, $values = null) {
		$data = "";
		if ($values) {
			$values = func_get_args();
			array_shift($values); 
			$data = "\n" . print_r($values, true);
		}
		Kohana::$log->add(Log::DEBUG, $message . $data);
	}

	private static function error($message, $values = null) {
		Kohana::$log->add(Log::ERROR, $message, $values);
	}
}
