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
