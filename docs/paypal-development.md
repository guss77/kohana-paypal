PayPal Protocol Implementation Notes
====================================

Implementation notes from implementing PayPal REST API for Kohana

# Authentication

Clients authenticate to PayPal by doing a token exchange with the PayPal OAUTH v2.0 endpoint at 
{endpoint}/v1/oauth2/token. The received token can be used for authentication of all operations. The
authentication token has a limited lifetime, but can be used multiple times or different transactions. 

This implementation stores the received token using the default Kohana cache engine, and re-uses the
token if it did not expire yet. Note: because some Kohana cache engines do not support automatic
expiry management, this module calls the cache implementation garbage collection method periodically
(if the cache implementation reports that it requires garbage collection).

# Transaction Process

A transaction starts (assuming there is a valid authentication token) by calling the 
{endpoint}/v1/payments/payment method and providing a list of transactions to be charged to the 
customer, payment metadata and callback URLs. For example:

		(object)[
			'intent' => "sale", // I'm not sure what other options are available
			'redirect_urls' => (object)[ // the two local endpoints for your website
				'return_url' => $base . $route->uri(['action' => 'complete']),
				'cancel_url' => $base . $route->uri(['action' => 'cancel']),
			],
			"payer" => (object)[ // which payment method do you want the customer to use
    			"payment_method" => "paypal", // not sure what other options are available
    		],
    		"transactions" => [ // list of transactions you want to perform
    			(object)[
    				"amount" => (object)[
        				"total" => $amount,
        				"currency" => $currency,
        			]
        		]
        	]
        ]
 
The PayPal service should respond with the registration for the transaction. For example:

	(object)[
    	"id" => "PAY-291416610S107651XKMNRMTA", // transaction ID - save that
    	"create_time" => "2014-03-08T13:08:28Z", // dates are the request time in UTC on the PayPal server
    	"update_time" => "2014-03-08T13:08:28Z",
    	"state" => "created", // as expected
    	"intent" => "sale",  // what you said
    	"payer" => (object)[ // what you said
			"payment_method" => "paypal",
			"payer_info" => (object)[ // i'm not sure what that doing here
				"shipping_address" => (object)[]
			]
		]
		"transactions"=> [ // repeating what you said
			(object)[
				"amount" => (object)[
					"total" => "1.23",
					"currency" => "ILS",
					"details" => (object)[
						"subtotal" => "1.23"
					]
				]
			]
		]
		"links" => [ // list of transaction URLs
			(object)[
				"href" => "https://api.sandbox.paypal.com/v1/payments/payment/PAY-291416610S107651XKMNRMTA",
				"rel" => "self",
				"method" => "GET",
			],
			(object)[
      			"href" => "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-39E27912LC7580541",
      			"rel" => "approval_url",
      			"method" => "REDIRECT",
      		]
      		(object)[
      			"href" => "https://api.sandbox.paypal.com/v1/payments/payment/PAY-291416610S107651XKMNRMTA/execute",
      			"rel" => "execute",
      			"method" => "POST",
      		]
      	]
	]

For interactive completion (what you normally want to do), redirect your customer to the `approval_url`.
PayPal would prompt the user for payment details (PayPal login or credit card) and will then redirect
the user to either the `return_url` (if the customer completed the payment) or the `cancel_url` (if the
cusotmer cancelled).
