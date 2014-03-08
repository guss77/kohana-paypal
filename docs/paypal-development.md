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
    			"payment_method" => "paypal", // forces customer to use PayPal. can be 'credit_card'
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
    	"id" => "PAY-291416610S107651XKMNRMTA", // transaction resource ID - save that
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
the user to either the `return_url` (if the customer approved the payment) or the `cancel_url` (if the
cusotmer declined).

When PayPal redirects the user to the return URL, they will provide in the query string the
transaction token (which is the same token as in the `approval_url`) and an ID that represents the
customer for PayPal, e.g. '?token=EC-7SU71444C2014322D&PayerID=WK88QHZ95YBPS'

In order to complete the transaction, call the PayPal payments execute method at the `execute` URL
from the registration links. There's no need to save that URL, as it can be composed from the
PayPal transaction resource ID. The call should provide the "payer ID" that was delivered in
the customer redirect:

	(object)[
		'payer_id' => 'WK88QHZ95YBPS'
	]

If the transaction is valid (and was approved), PayPal response will include the current transaction
registration details:

	(object)[
		"id" => "PAY-291416610S107651XKMNRMTA", // transaction resource ID - save that
    	"create_time" => "2014-03-08T13:08:28Z", // dates are in UTC on the PayPal server
    	"update_time" => "2014-03-08T13:08:47Z",
    	"state" => "pending", // means that the transaction is in the process of clearing
  		"intent" => "sale",
  		"payer" => (object)[
    		"payment_method" => "paypal",
    		"payer_info" => (object)[
    			"email" => "oded-test@geek.co.il",
    			"first_name" => "Oded",
    			"last_name" => "Arbel",
    			"payer_id" => "WK88QHZ95YBPS",
    			"shipping_address" => (object)[
    				"line1" => "1 Main St",
    				"city" => "San Jose",
    				"state" => "CA",
    				"postal_code" => "95131",
        			"country_code" => "US"
        		]
        	]
        ],
        "transactions" => [
        	(object)[
        		"amount" => (object)[
        			"total" => "1.23"
        			"currency" => "ILS"
        			"details" => (object)[
        				"subtotal" => "1.23"
        			]
        		],
        		"related_resources" => [
        			(object)[
        				"sale" => (object)[
        					"id" => "7GX43578S7109592D",
        					"create_time" => "2014-03-08T15:30:14Z",
        					"update_time" => "2014-03-08T15:30:33Z",
        					"state" => "pending",
        					"amount" => (object)[
        						"total" => "1.23",
        						"currency" => "ILS",
        					],
        					"pending_reason" => "multicurrency",
        					"parent_payment" => "PAY-291416610S107651XKMNRMTA",
        					"links" => [
        						(object)[
        							"href" => "https://api.sandbox.paypal.com/v1/payments/sale/7GX43578S7109592D",
        							"rel" => "self",
        							"method" => "GET",
        						],
        						(object)[
        							"href" => "https://api.sandbox.paypal.com/v1/payments/sale/7GX43578S7109592D/refund",
        							"rel" => "refund",
        							"method" => "POST",
        						]
        						(object)[
        							"href" => "https://api.sandbox.paypal.com/v1/payments/payment/PAY-291416610S107651XKMNRMTA",
        							"rel" => "parent_payment",
        							"method" => "GET",
        						]
        					]
        				]
        			]
        		]
        	]
        ],
        "links" => [
        	(object)[
        		"href" => "https://api.sandbox.paypal.com/v1/payments/payment/PAY-291416610S107651XKMNRMTA",
        		"rel" => "self",
        		"method" => "GET",
        	]
        ]
    ]

The 'payer' proprety contains all the information about the customer.

# Implementation Details

* To start the transaction, call PayPal::payment($amount [, "application transaction id"). 
* PayPal::payment will 
  * register the transaction with PayPal
  * store the PayPal transaction resource ID in the session
  * redirect the user to the PayPal approval URL
* The PayPal module wil register routing rules to capture the completion or cancellation of the
transaction.
* When PayPal redirects the user to complete the transaction, the PayPal module's controller will
call PayPal::execute() with the PayPal transaction details.
* PayPal::execute() will
	* retrieve the PayPal transaction ID from the session
	* execute the transaction with PayPal
	* call the application's PayPal->approved() implementation with the transaction resource Id,
	the payer details and the application's provided local transaction Id.
