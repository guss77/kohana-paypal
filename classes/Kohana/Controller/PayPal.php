<?php defined('SYSPATH') or die('No direct script access.');

/*
 * Kohana 3.3 PayPal payment processing Module
 * Copyright (C) 2014 Oded Arbel
 * 
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
 * PayPal transaction local endpoint receiver.
 * This controller is automatically wired into the Kohana routing engine to receive
 * transaction responses from PayPal
 * 
 * @author Oded Arbel <oded@geek.co.il>
 */
class Kohana_Controller_PayPal extends Controller {
	
	public function action_complete() {
		PayPal::execute($this->request->PayerID, $this->request->param('trxid'));
	}

	public function action_cancel() {
		var_dump($this->request);
	}
	
	public function action_return() {
		Kohana::$log->add(Log::DEBUG, "Got an unsolicted call from PayPal: " . print_r($request,true));
	}

}
