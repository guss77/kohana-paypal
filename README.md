kohana-paypal
=============

PayPal module for Kohana 3.3

## Installation

1. Clone into the 'modules' directory of your Kohana installation
1. Add a reference to load the module in your 'bootstrap.php' file
1. Copy 'config/paypal.php' to your application's 'config' directory and edit according to your requirements:
  1. Replace the client Id and secret with your real PayPal client ID and secret
  1. You may also want to change the default currency
1. Copy 'classes/PayPal.php' to your application's 'classes' directory and implement all the abstract methods

## Usage

To create a transaction, call `PayPal::payment($amount)` where `$amount` is the transaction amount.

Please note that, while not required, you should pass `$amount` as a string, otherwise if the amount is not an integer 
(i.e. it has a fractional part) then it may be subject to IEEE 754 floating point rounding. To prevent that, the module
truncates numeric floating point amounts to 2 decimal points, but that operation may still be subject to rounding.

PayPal::payment() will redirect the user to approve the payment through PayPal web interface. When the user completes
the approval, the method PayPal::approved() in the application's implementation will be called. The approved() method
receives the transaction completion details and can do any processing it needs, and then return a URL to which the 
user will be redirected.

If the user cancelled the transaction, the PayPal module will call the method PayPal::cancelled in the application's
implementation, which will also need to return a URL to redirect the user.

An Example flow:

* User clicks on the "buy" button on the page for product "A123".
* Application calls PayPal::payment($priceForProduct, [ 'ProductId' => "A123" ]);
* User gets redirected to PayPal and approved the payment.
* User gets redirected back to the site where the PayPal module completes the transaction.
* PayPal module calls the Application's PayPal::approved([ 'ProductId' => "A123" ], "PAY-ABCD1234EDFGH5678", (object)[
    "email" => "user@example.com", "first_name" => "first", "last_name" => "Last", "payer_id" => "ABCD1234EFGH", "shipping_address" => (object)[ ... ] ]);
* approved() registers the transaction completion in the database and orders a delivery for the user
* approved() returns the URL to the "thanks for purchasing" page
* PayPal module redirects the user to the "thanks" page.
