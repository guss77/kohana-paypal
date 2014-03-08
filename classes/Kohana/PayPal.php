<?php defined('SYSPATH') or die('No direct script access.');


class Kohana_PayPal {

	const CACHE_TOKEN = 'paypal_cached_token';
	
	var $endpoint;
	var $clientID;
	var $secret;
	var $currency;
	var $cache;
	
	public function __construct() {
		$config = Kohana::$config->load('paypal');
		
		$this->endpoint = $config['endpoint'];
		if (!isset($this->endpoint))
			throw new Exception("Missing Paypal endpoint configuration");
		
		$this->clientID = $config['clientId'];
		if (!isset($this->clientID))
			throw new Exception("Missing Paypal client ID configuration");
		
		$this->secret = $config['secret'];
		if (!isset($this->secret))
			throw new Exception("Missing Paypal client secret configuration");
		
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
	
	public function payment($amount) {
		$token = $this->authenticate();
		
		// paypal like the amount as string, to prevent floating point errors
		if (!is_string($amount))
			$amount = sprintf("%0.2f", $amount);
		
		$route = Route::get('paypal_response');
		$payment_data = (object)[
			'intent' => "sale",
			'redirect_urls' => (object)[
				'return_url' => URL::base(true) . $route->uri(['action' => 'complete']),
				'cancel_url' => URL::base(true) . $route->uri(['action' => 'cancel']),
			],
			"payer" => (object)[
    			"payment_method" => "paypal",
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
	
	protected function genRequest($address, $data = array(), $token = null) {
		$req = (new Request($this->endpoint . '/v1/' . $address))->method('POST')
			->headers('Accept','application/json')
			->headers('Accept-Language', 'en_US')
			->headers('Authorization', is_null($token) ?
				'Basic ' . base64_encode($this->clientID . ":" . $this->secret) :
				$token->token_type . ' ' . $token->access_token)
			->headers('Content-Type', is_array($data) ?
					'application/x-www-form-urlencoded' : 'application/json');
		
		Log::debug("Sending message: ", print_r($data,true));
		if (is_array($data))
			return $req->post($data); // set all fields directly
		
		if (!is_object($data))
			throw new Exception("Invalid data type in PayPal::genRequest");
		
		return $req->body(json_encode($data));
	}
	
	protected function call(Request $request) {
		$response = $request->execute();
		if (!$response->isSuccess()) {
			Log::error("Error " . $response->status() . " in PayPal call: " . $response->body());			
			throw new Exception("Error " . $response->status() . " in PayPal call");
		}
		$res = json_decode($response->body());
		
		if (isset($res->error)) {
			Log::error("Error in PayPal call: " . print_r($res, true));			
			throw new Exception('PayPal: ' . $res->error_description . ' [' . $res->error . 
					'] while calling ' . $request->uri());
		}
		return $res;
	}
	
}
