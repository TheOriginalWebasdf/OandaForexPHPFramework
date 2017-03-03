<?php
/* This code was modified from https://github.com/tavurth/OandaWrap to make it class-based */
/* License is duplicate to this project's license.  Please see LICENSE file */

class OandaApi {
	protected $baseUrl;
	protected $account;
	protected $apiKey;
	protected $instruments;
	protected $socket;
	protected $callback;
	protected $checkSSL;


	// constructor
	public function __construct()
	{
		
	}

	//////////////////////////////////////////////////////////////////////////////////
	//
	//	VARIABLE DECLARATION AND HELPER FUNCTIONS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function valid($jsonObject, $verbose=FALSE, $message=FALSE) {
		//Return boolean false if object has been corrupted or has error messages/codes included
		if (isset($jsonObject->code)) {
			if ($verbose && isset($jsonObject->message))
				echo 'OandaWrap: Invalid object. ' . $jsonObject->message . ' ';
			return FALSE;
		}
		if (isset($jsonObject) === FALSE || $jsonObject === FALSE || empty($jsonObject)) {
			if ($verbose && $message)
				echo 'OandaWrap: Error. ' . $message . ' ';
			return FALSE;
		}
		return $jsonObject;
	}
	
	protected function setup_account($baseUrl, $apiKey = FALSE, $accountId = FALSE, $checkSSL = 2) {
		//Generic account setup program, prints out errors in the html if incomplete
		//Set the url
		$this->baseUrl = $baseUrl;
		$this->instruments = array();
		$this->checkSSL = $checkSSL;
		//Checking our login details
		if (strpos($baseUrl, 'https') !== FALSE || strpos($baseUrl, 'fxpractice') !== FALSE) {
			
			//Check that we have specified an API key
			if (! $this->valid($apiKey, TRUE, 'Must provide API key for ' . $baseUrl . ' server.'))
				return FALSE;
			
			//Set the API key
			$this->apiKey  = $apiKey;
			
			//Check that we have specified an accountId
			if (! $this->valid($accountId)) {
				if (! $this->valid(($accounts = $this->accounts()), TRUE, 'No valid accounts for API key.'))
					return FALSE;
				$this->account = $accounts->accounts[0];
			
				//else if we passed an accountId
			} else $this->account = $this->account($accountId);
		}
		//Completed
		return $this->nav_account(TRUE);
	}
	
	public function setup($server=FALSE, $apiKey=FALSE, $accountId=FALSE, $checkSSL = 2) {
		//Setup our enviornment variables
		if ($this->valid($this->account))
			if ($this->account->accountId == $accountId)
				return;
		
		$this->callback = FALSE;
		//'Live', 'Demo' or the default 'Sandbox' servers.
		switch (ucfirst(strtolower($server))) { //Set all to lowercase except the first character
		case 'Live':
			return $this->setup_account('https://api-fxtrade.oanda.com/v1/', $apiKey, $accountId, $checkSSL);
		case 'Demo':
			return $this->setup_account('https://api-fxpractice.oanda.com/v1/', $apiKey, $accountId, $checkSSL);
		case 'Sandbox':
			return $this->setup_account('http://api-sandbox.oanda.com/v1/');
		default:
			echo 'User must specify: "Live", "Demo", or "Sandbox" server for OandaWrap setup.';
			return FALSE;
		}
	}
	
	protected function index() {
		//Return a formatted string for more concise code
		if ($this->valid($this->account))
			return 'accounts/' . $this->account->accountId . '/';
		return 'accounts/0/';
	}
	protected function position_index() {
		//Return a formatted string for more concise code
		return $this->index() . 'positions/';
	}
	protected function trade_index() {
		//Return a formatted string for more concise code
		return $this->index() . 'trades/';
	}
	protected function order_index() {
		//Return a formatted string for more concise code
		return $this->index() . 'orders';
	}
	protected function transaction_index() {
		//Return a formatted string for more concise code
		return $this->index() . 'transactions/';
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	DIRECT NETWORK ACCESS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	protected function data_decode($data) {
		//Return decoded data
		if (! $this->valid($data)) {
			//Return a stdObj with failure codes and message
			$failure = new stdClass();
			$failure->code = -1;
			$failure->message = 'OandaWrap throws curl error: ' . curl_error($this->socket);
			
			return $failure;
		}

		return json_decode(($decoded = @gzinflate(substr($data,10,-8))) ? $decoded : $data);
	}
	protected function authenticate($curl) {
		//Authenticate our curl object
		$headers = array('X-Accept-Datetime-Format: UNIX',			//Milliseconds since epoch
						 'Accept-Encoding: gzip, deflate',		//Compress data
						 'Connection: Keep-Alive');				//Persistant http connection
		if (isset($this->apiKey)) {    								//Add our login hash
			array_push($headers, 'Authorization: Bearer ' . $this->apiKey);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->checkSSL);			//Verify Oanda
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->checkSSL);			//Verify Me
		}
		//Set the sockets headers
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	}
	protected function configure($curl) {
		//Configure default connection settings
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);				//We want the data returned as a variable
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);						//Maximum wait before timeout
		$this->authenticate($curl);									//Authenticate our socket
		return $curl;
	}
	protected function socket_new() {
		return $this->configure($socket = curl_init());
	}
	protected function socket() {
		//Return our active socket for reuse
		if (! $this->valid($this->socket))
			$this->socket = $this->socket_new();
		return $this->socket;
	}
	protected function get($index, $queryData=false) {
		//Send a GET request to Oanda
		$queryData = ($queryData ? $queryData : array());
		$curl = $this->socket();
		
		curl_setopt($curl, CURLOPT_HTTPGET, 1);
		curl_setopt($curl, CURLOPT_URL, //Url setup
					$this->baseUrl . $index . ($queryData ? '?' . http_build_query($queryData) : '')); 
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');   //GET request setup
		return $this->data_decode(curl_exec($curl));         //Launch and store decrypted data
	}
	protected function post($index, $queryData) {
		//Send a POST request to Oanda
		$curl = $this->socket();
		
		curl_setopt($curl, CURLOPT_URL, $this->baseUrl . $index);       //Url setup
		curl_setopt($curl, CURLOPT_POST, 1);                            //Tell curl we want to POST
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');              //POST request setup
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($queryData));  //Include the POST data
		return $this->data_decode(curl_exec($curl)); 		//Launch and return decrypted data
	}
	protected function patch($index, $queryData) {
		//Send a PATCH request to Oanda
		$curl = $this->socket();
										
		curl_setopt($curl, CURLOPT_URL, $this->baseUrl . $index);              //Url setup
		curl_setopt($curl, CURLOPT_POST, 1);                                   //Tell curl we want to POST
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');                    //PATCH request setup
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($queryData));  //Include the POST data
		return $this->data_decode(curl_exec($curl));                            //Launch and return decrypted data
	}
	protected function delete($index) {
		//Send a DELETE request to Oanda
		$curl = $this->socket();
		
		curl_setopt($curl, CURLOPT_URL, $this->baseUrl . $index);		//Url setup
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');			//DELETE request setup
		return $this->data_decode(curl_exec($curl)); 		            //Launch and return decrypted data
	}
	private function stream_callback($curl, $str) {
		//Callback that then calls your function to process streaming data

		// Decode the data and check
		if (($decoded = @json_decode($str)) != NULL)
			// Call the users callback
			if (call_user_func($this->callback, $decoded) != NULL)
				return FALSE; // Quit the stream when user returns value

		// Continue the stream
		return strlen($str);
	}

	private function stream_url($type) {
		//Find the base of the url
		return str_replace("api", 'stream', $this->baseUrl) . $type . '?';
	
	}
	private function stream_setup($callback, $streamUrl) {
		//Set the internal callback function
		$this->callback = $callback;

		//Authenticate the socket to Oanda
		$this->authenticate(($streamHandle = curl_init()));
			
		//Setup the stream
		curl_setopt($streamHandle, CURLOPT_URL, $streamUrl);

		//Our callback, called for every new data packet
		curl_setopt($streamHandle, CURLOPT_WRITEFUNCTION, $this->stream_callback);
		
		return $streamHandle;
	}
	
	private function event_stream($callback, $options=FALSE) {
		//Load the account from setup
		if ($this->valid($account = $this->nav_account(TRUE))) {

			// Check that we passed at least one valid account
			if (! $this->valid($options, TRUE, 'Must provide array of AccountIds for events streaming.'))
				return FALSE;

			// Return a curl handle to the new stream
			return $this->stream_setup($callback, $this->stream_url('events') . 'accountIds=' . implode(',', $options));
		}

	}
	private function quote_stream($callback, $options=FALSE) {
		//Load the account from setup
		if ($this->valid($account = $this->nav_account(TRUE))) {

			// Check that we passed at least one valid account
			if (! $this->valid($options, TRUE, 'Must provide array of currency pairs for quote streaming.'))
				return FALSE;

			// Return a curl handle to the new stream
			return $this->stream_setup($callback, $this->stream_url('prices') . 'accountId=' . $account->accountId . '&instruments='. implode(',', $options));
		}
	}

	function stream_exec($multiHandle) {
		$running = 1;
		while ($running > 0) {
			curl_multi_exec($multiHandle, $running);
			curl_multi_select($multiHandle);
		}
	}
	
	public function stream($callback, $quotes = FALSE, $accounts = FALSE) {
		//Open a stream to Oanda 
		//	$callback = function ($jsonObject) { /* { YOUR CODE } */;  }
		//
		// Quotes Example:
		//	OandaWrap::stream(function ($event) { var_dump($event); }, array('EUR_USD'), FALSE);
		//
		// Events Example:
		//	OandaWrap::stream(function ($event) { var_dump($event); }, FALSE, array('12345'));
		//
		//
		// Events Example:
		//	OandaWrap::stream(function ($event) { var_dump($event); }, array('EUR_USD'), array('12345'));
		//Notes:
		//	Returning any value from the callback function (true or false) will exit the stream

		$eventStream = false;
		$quoteStream = false;

		// Return false if no parameters were passed
		if (! $quotes && ! $accounts)
			return FALSE;

		// Create the multi curl base
		$multiHandle = curl_multi_init();

		// Add the quote stream
		if ($quotes !== false && ! empty($quotes))
			if (($quoteStream = $this->quote_stream($callback, $quotes)))
				curl_multi_add_handle($multiHandle, $quoteStream);

		// Add the event stream
		if ($accounts !== false && ! empty($accounts))
			if (($eventStream = $this->event_stream($callback, $accounts)))
				curl_multi_add_handle($multiHandle, $eventStream);

		$this->stream_exec($multiHandle);

		// Cleanup
		if ($eventStream)
			curl_multi_remove_handle($multiHandle, $eventStream);
		if ($quoteStream)
			curl_multi_remove_handle($multiHandle, $quoteStream);
		curl_multi_close($multiHandle);
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	ACCOUNT WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function account($accountId) {
		//Return the information for $accountId
		return $this->get('accounts/' . $accountId);
	}
	
	public function accounts() {
		//Return a jsonObject of the accounts for $username 
		return $this->get('accounts');
	}
	
	public function account_id($accountName, $uName) {
		//Return the accountId for $accountName
		return $this->valid($account = $this->account_named($accountName, $uName)) ? $account->accountId : $account;
	}
	
	public function account_named($accountName, $uName) {
		//Return the information for $accountName

		if (! $this->valid($accounts = $this->accounts($uName)))
			return $accounts;
		
		foreach ($accounts->accounts as $account)
			if ($account->accountName == $accountName) 
				return $account;
			
		return FALSE;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	INSTRUMENT WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function instruments() {
		//Return a list of tradeable instruments for $accountId
		if (! $this->valid($this->account))
			return $this->account;
			
		if (empty($this->instruments))
			$this->instruments = $this->get('instruments', array('accountId' => $this->account->accountId));
		
		return $this->instruments;
	}
	public function instrument($pair) {
		//Return instrument for named $pair
	
		if (! $this->valid($instruments = $this->instruments()))
			return $instruments;
		
		foreach($instruments->instruments as $instrument)
			if ($pair == $instrument->instrument)
				return $instrument;
		
		return FALSE;
	}
	public function instrument_pairs($currency) {
		//Return instruments for that correspond to $currency
		
		if (! $this->valid($instruments = $this->instruments()))
			return $instruments;
		
		$result = (object) array('instruments' => array());
		foreach ($instruments->instruments as $instrument)
			if (strpos($instrument->instrument, $currency))
				$result->instruments[] = $instrument->instrument;
		
		return $result;
	}
	
	public function instrument_name($home, $away) {
		//Return a proper instrument name for two currencies
		//Example: OandaWrap::instrument_name('AUD', 'CHF') returns 'AUD_CHF'
		//Example: OandaWrap::instrument_name('USD', 'EUR') returns 'EUR_USD' 
		if ($this->instrument($home . '_' . $away))
			return $home . '_' . $away;
		if ($this->instrument($away . '_' . $home))
			return $away . '_' . $home;
		return 'Invalid_instrument__' . $home . '/' . $away;
	}
	
	public function instrument_split($pair) {
		//Split an instrument into two currencies and return an array of them both
		$currencies = array();
		$dividerPos = strpos($pair, '_');
		//Failire
		if ($dividerPos === FALSE) return FALSE;
		//Building array
		array_push($currencies, substr($pair, 0, $dividerPos));
		array_push($currencies, substr($pair, $dividerPos+1));
		return $currencies;
	}
	
	public function instrument_pip($pair) {
		//Return a floating point number declaring the pip size of $pair
		return $this->valid(($instrument = $this->instrument($pair)), TRUE) ? $instrument->pip : $instrument;
	}
	
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	CALCULATOR FUNCTIONS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function convert($pairHome, $pairAway, $amount) {
		//Convert $amount of $pairHome to $pairAway
		if (! $this->valid($pair = $this->instrument_name($pairHome, $pairAway)))
			return $pair;
		
		
		if (! $this->valid($price = $this->price($pair)))
			return $price;
		
		//Use the $baseIndex currency of $pair (AUD_JPY = Aud or Jpy)
		$reverse	= (strpos($pair, $pairHome) > strpos($pair, '_') ? FALSE : TRUE);
		
		//Which way to convert 
		return ($reverse ? $amount * $price->ask : $amount / $price->ask);
	
	}
	public function convert_pair($pair, $amount, $home) {
		//Convert $amount of $pair from $home 
		//	i.e. OandaWrap::convert_pair('EUR_USD', 500, 'EUR'); Converts 500 EUR to USD
		//	i.e. OandaWrap::convert_pair('EUR_USD', 500, 'USD'); Converts 500 USD to EUR
		if (! $this->valid($price = $this->price($pair)))
			return $price;
		
		$pairNames  = $this->instrument_split($pair);
		$homeFirst  = $home == $pairNames[0] ? TRUE : FALSE;
		return ($homeFirst ? 
				$this->convert($pairNames[0], $pairNames[1], $amount) :
				$this->convert($pairNames[1], $pairNames[0], $amount));
	}
	
	public function calc_pips($pair, $open, $close) {
		//Return the pip difference between prices $open and $close for $pair
		if (! $this->valid($instrument = $this->instrument_pip($pair)))
			return $instrument;
		
		return round(($open - $close)/$instrument, 2);
	}
	
	public function calc_pip_price($pair, $size, $side=1) {
		//Return the cost of a single pip of $pair when $size is used
		return ($this->valid($price = $this->price($this->nav_instrument_name($pair, 1)))) ?
								   ($this->instrument_pip($pair)/($side ? $price->bid : $price->ask))*$size : $price;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	NAV (NET ACCOUNT VALUE) WRAPPERS
	//
	////////////////////////////////////////////////////////////////////////////////// 
	
	public function nav_account_set($accountId) {
		//Set our environment variable $account
		return $this->valid($this->account = $this->account($accountId));
	}
	
	public function nav_account($verbose=FALSE) {
		//Return our environment variable account
		return (isset($this->account->message) ? FALSE : $this->account);
	}
	
	public function nav_instrument_name($pair, $index=0) {
		//Return the instrument name used to convert currency for the NAV
		if (! $this->valid($this->account))
			return $this->account;
		
		//Split the $pair
		if (! $this->valid($splitName = $this->instrument_split($pair)))
			return $splitName;
		
		//Choose the correct pair for the nav
		if ($splitName[$index] == $this->account->accountCurrency)
			$index = ($index == 1 ? 0 : 1);
		
		//Find the new instrument for the nav with $pair
		return $this->instrument_name($this->account->accountCurrency, $splitName[$index]);
	}
	
	public function nav_size_percent($pair, $percent, $leverage = 50) {
		//Return the value of a percentage of the NAV (Net account value)
/*
		print "nav_size_percent\n";
		print "pair=$pair\n";
		print "percent=$percent\n";
		print "leverage=$leverage\n";
*/
		
		
		//Validate account details
		if (! $this->valid($this->account))
			return $this->account;
		
		//Validate pair name	
		if (! $this->valid($name = $this->nav_instrument_name($pair)))
			return $name;
		
		//Calculate the percentage balance to use in the trade	
		$percent = $this->account->balance*($percent/100);
		
		// If home currency in the pair is same as account currency, simply return leveraged amount
		$ins = $this->instrument_split($pair);
		if ($ins[0] == $this->account->accountCurrency) {
			return ceil($percent * $leverage);
		}

		// Otherwise, convert the size to the trade currency
		if (! $this->valid($baseSize = $this->convert_pair($name, $percent, $this->account->accountCurrency))) {
//				print "nav_size_percent: baseSize=$baseSize\n";
			return $baseSize;
		}

//			print "nav_size_percent: baseSize=$baseSize\n\n\n";
							
		//Calculate and return the leveraged size
		return ceil($baseSize * $leverage);
	}
	
	public function nav_size_percent_per_pip($pair, $riskPerPip, $leverage = 50) {
		//Return the size for $pair that risks $riskPerPip every pip
		
		//Calculate maximum size @ $leverage
		if (! $this->valid($maxSize = $this->nav_size_percent($pair, 100, $leverage)))
			return $maxSize;
		
		//@ maximum 50:1 leverage, risk is 0.5% per pip
		$baseSize = ($riskPerPip/0.5)*$maxSize;
		
		//Calculate our leveraged size
		return floor(($leverage/50)*$baseSize);
	}
	
	public function nav_pnl($dollarValue=FALSE) {
		//Return the pnl for account, if $dollarValue is set TRUE, return in base currency, else as %.
		
		//Check for valid account
		if (! $this->valid($this->account))
			return $this->account;

		if ($this->account->balance == 0)
			return 0.00;
		
		//Percentage
		if ($dollarValue === FALSE)
			return round(($this->account->unrealizedPl / $this->account->balance) * 100, 2);
		
		//Default
		return $this->account->unrealizedPl;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	TRANSACTION WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function transaction($transactionId) {
		//Get information on a single transaction
		return $this->get($this->transaction_index() . $transactionId);
	}
	public function transactions($number=50, $pair='all') {
		//Return an object with all transactions (max 50)
		return $this->get($this->transaction_index(), array('count' => $number, 'instrument' => $pair));
	}
	
	public function transactions_types($types, $number=50, $pair='all') {
		//Return a jsonObject with all transactions conforming to one of $types which is an array of strings
		
		if (! $this->valid($transactions = $this->transactions()))
			return $transactions;
		
		$result = (object) array('transactions' => array());
		foreach ($transactions->transactions as $transaction)
			//If the type is valid
			if (in_array($transaction->type, $types))
				//Buffer it in the object
				$result->transactions[] = $transaction;
		//Return sucess object
		return $result;
	}
	public function transactions_type($type, $number=50, $pair='all') {
		//Return up to 50 transactions of $type
		return $this->transactions_types(array($type), $number, $pair);
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	! LIVE FUNCTIONS !
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	// TIME FUNCTIONS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function time_seconds($time) {
		//Convert oanda time from microseconds to seconds
		return floor($time/1000000);
	}
	
	public function gran_seconds($gran) {
		//Return a the number of seconds per Oandas 'granularity'
		switch (strtoupper($gran)) {
		case 'S5': return 5;
		case 'S10': return 10;
		case 'S15': return 15;
		case 'S30': return 30;
		case 'M1': return 60;
		case 'M2': return 2*60;
		case 'M3': return 3*60;
		case 'M4': return 4*60;
		case 'M5': return 5*60;
		case 'M10': return 10*60;
		case 'M15': return 15*60;
		case 'M30': return 30*60;
		case 'H1': return 60*60;
		case 'H2': return 2*60*60;
		case 'H3': return 3*60*60;
		case 'H4': return 4*60*60;
		case 'H6': return 6*60*60;
		case 'H8': return 8*60*60;
		case 'H12': return 12*60*60;
		case 'D' : return 24*60*60;
		case 'W' : return 7*24*60*60;
		case 'M' : return (365*24*60*60)/12;
		}
	}
	
	public function expiry($seconds=5) {
		//Return the Oanda compatible timestamp of time() + $seconds
		return time()+$seconds;
	}
	public function expiry_min($minutes=5) {
		//Return the Oanda compatible timestamo of time() + $minutes
		return $this->expiry($minutes*60);
	}
	public function expiry_hour($hours=1) {
		//Return the Oanda compatible timestamp of time() + $hours
		return $this->expiry_min($hours*60);
	}
	public function expiry_day($days=1) {
		//Return the Oanda compatible timestamp of time() + $days
		return $this->expiry_hour($days*24);
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	BIFUNCTIONAL MODIFICATION WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	//$type in all cases for bidirectional is either 'order' or 'trade'
	
	protected function set_($type, $id, $args) {
		//Macro function for setting attributes of both orders and trades
		switch ($type) {
		case 'order':
			return $this->order_set($id, $args);
		case 'trade':
			return $this->trade_set($id, $args);
		}
	}
	public function set_stop($type, $id, $price) {
		//Set the stopLoss of an order or trade
		return $this->set_($type, $id, array('stopLoss' => $price));
	}
	public function set_tp($type, $id, $price) {
		//Set the takeProfit of an order or trade
		return $this->set_($type, $id, array('takeProfit' => $price));
	}
	public function set_trailing_stop($type, $id, $distance) {
		//Set the trailingStop of an order or trade
		return $this->set_($type, $id, array('trailingStop' => $distance));
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	ORDER WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function order($orderId) {
		//Return an object with the information about $orderId
		return $this->get($this->order_index() . '/' . $orderId);
	}
	public function order_pair($pair, $number=50) {
		//Get an object with all the orders for $pair
		return $this->get($this->order_index(), array('instrument' => $pair, 'count' => $number));
	}
	public function order_open($side, $units, $pair, $type, $price=FALSE, $expiry=FALSE, $rest = FALSE) {
		//Open a new order
		
		//failure to provide expiry and price to limit or stop orders?
		if ($type !== 'market' && ($price === FALSE || $expiry === FALSE))
			return FALSE;
		
		//Setup options
		$orderOptions = array(
			'instrument' => $pair, 
			'units' => $units, 
			'side' => $side, 
			'type' => $type
		);
		
		if ($price) $orderOptions['price'] = $price;
		if ($expiry) $orderOptions['expiry'] = $expiry;
		
		if (is_array($rest))
			foreach ($rest as $key => $value)
				$orderOptions[$key] = $value;
		
		return $this->post($this->order_index(), $orderOptions);
	}
	public function order_close($orderId) {
		//Close an order by Id
		return $this->delete($this->order_index() . '/' . $orderId);
	}
	public function order_close_all($pair) {
		//Close all orders in $pair
	
		if (! $this->valid($orders = $this->order_pair($pair)))
			return $orders;

		$result = (object) array('orders' => array());
		foreach ($orders->orders as $order)
			if (isset($order->id))
				$result->orders[] = $this->order_close($order->id);
		return $result;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	ORDER MODIFICATION WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function order_set($orderId, $options) {
		//Modify the parameters of an order
		return $this->patch($this->order_index()  . '/' . $orderId, $options);
	}
	public function order_set_stop($id, $price) {
		//Set the stopLoss of an order
		return $this->set_stop('order', $id, $price);
	}
	public function order_set_tp($id, $price) {
		//Set the takeProfit of an order
		return $this->set_tp('order', $id, $price);
	}
	public function order_set_trailing_stop($id, $distance) {
		//Set the trailingStop of an order
		return $this->set_trailing_stop('order', $id, $distance);
	}
	public function order_set_expiry($id, $time) {
		//Set the expiry of an order
		return $this->set_('order', $id, array('expiry' => $time));
	}
	public function order_set_units($id, $units) {
		//Set the units of an order
		return $this->set_('order', $id, array('units' => $units));
	}
	
	public function order_set_all($pair, $options) {
		//Modify all orders on $pair
		
		if (! $this->valid($orders = $this->order_pair($pair)))
			return $orders;
			
		$result = (object) array('orders' => array());
		foreach ($orders->orders as $order)
			if (isset($order->id))
				$result->orders[] = $this->set_('order', $order->id, $options);
		return $result;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	TRADE WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function trade($tradeId) {
		//Return an object containing information on a single pair
		return $this->get($this->trade_index() . $tradeId);
	}
	public function trade_pair($pair, $number=50) {
		//Return an object with all the trades on $pair
		return $this->get($this->trade_index(), array('instrument' => $pair, 'count' => $number));
	}
	public function trade_close($tradeId) {
		//Close trade referenced by $tradeId
		return $this->delete($this->trade_index() . $tradeId);
	}
	public function trade_close_all($pair) {
		//Close all trades on $pair
		if (! $this->valid($trades = $this->trade_pair($pair)))
			return $trades;

		$result = (object) array('trades' => array());
		foreach ($trades->trades as $trade)
			if (isset($trade->id))
				$result->trades[] = $this->trade_close($trade->id);
		return $result;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	TRADE MODIFICATION WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function trade_set($tradeId, $options) {
		//Modify attributes of a trade referenced by $tradeId
		return $this->patch($this->trade_index() . $tradeId, $options);
	}
	public function trade_set_stop($id, $price) {
		//Set the stopLoss of a trade
		return $this->set_stop('trade', $id, $price);
	}
	public function trade_set_tp($id, $price) {
		//Set the takeProfit of a trade
		return $this->set_tp('trade', $id, $price);
	}
	public function trade_set_trailing_stop($id, $distance) {
		//Set the trailingStop of a trade
		return $this->set_trailing_stop('trade', $id, $distance);
	}
	
	public function trade_set_all($pair, $options) {
		//Modify all trades on $pair
		
		if (! $this->valid($trades = $this->trade_pair($pair)))
			return $trades;

		$result = (object) array('trades' => array());
		foreach ($trades->trades as $trade)
			if (isset($trade->id))
				$result->trades[] = $this->set_('trade', $trade->id, $options);
		return $result;
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	POSITION WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function position($pair) {
		//Return an object with the information for a single $pairs position
		return $this->get($this->position_index() . $pair);
	}
	public function position_pnl_pips($pair) {
		//Return an int() of the calculated profit or loss for $pair in pips

		//Check position validity
		if (! $this->valid($position = $this->position($pair)))
			return $position;
			
		//Buy back across the spread
		$price = $position->side == 'buy' ? $this->price($pair)->bid : $this->price($pair)->ask;
		
		//Calculate and return the pips
		return $this->calc_pips($pair, $position->avgPrice, $price);
	
	}
	public function positions() {
		//Return an object with all the positions for the account
		return $this->get($this->position_index());
	}
	public function position_close($pair) {
		//Close the position for $pair
		return $this->delete($this->position_index() . $pair);
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	BIDIRECTIONAL WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function market($side, $units, $pair, $rest = FALSE) {
		//Open a new @ market order
		return $this->order_open($side, $units, $pair, 'market', FALSE, FALSE, $rest);
	}
	public function limit($side, $units, $pair, $price, $expiry, $rest = FALSE) {
		//Open a new limit order
		return $this->order_open($side, $units, $pair, 'limit', $price, $expiry, $rest);
	}
	public function stop($side, $units, $pair, $price, $expiry, $rest = FALSE) {
		//Open a new stop order
		return $this->order_open($side, $units, $pair, 'stop', $price, $expiry, $rest);
	}
	public function mit($side, $units, $pair, $price, $expiry, $rest = FALSE) {
		//Open a new marketIfTouched order
		return $this->order_open($side, $units, $pair, 'marketIfTouched', $price, $expiry, $rest);
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	BUYING WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function buy_market($units, $pair, $rest = FALSE) {
		//Buy @ market
		return $this->market('buy', $units, $pair, $rest);
	}
	public function buy_limit($units, $pair, $price, $expiry, $rest = FALSE) {
		//Buy limit with expiry
		return $this->limit('buy', $units, $pair, $price, $expiry, $rest);
	}
	public function buy_stop($units, $pair, $price, $expiry, $rest = FALSE) {
		//Buy stop with expiry
		return $this->stop('buy', $units, $pair, $price, $expiry, $rest);
	}
	public function buy_mit($units, $pair, $price, $expiry, $rest = FALSE) {
		//Buy marketIfTouched with expiry
		return $this->mit('buy', $units, $pair, $price, $expiry, $rest);
	}
	public function buy_bullish($pair, $risk, $stop, $leverage=50) {
		//Macro: Buy $pair and limit size to equal %NAV loss over $stop pips. Then set stopLoss
		
		//Retrieve current price
		if (! $this->valid($price = $this->price($pair)))
			return $price;
		
		//Find the correct size so that $risk is divided by $pips	
		if (! $this->valid($size = $this->nav_size_percent_per_pip($pair, ($risk/$stop))))
			return $size;
		
		if (! $this->valid($newTrade = $this->buy_market($size, $pair)))
			return $newTrade;
		
		//Set the stoploss
		return $this->trade_set_stop($newTrade->tradeId, $price->ask + ($this->instrument_pip($pair) * $stop));
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	SELLING WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	public function sell_market($units, $pair, $rest = FALSE) {
		//Sell @ market
		return $this->market('sell', $units, $pair, $rest);
	}
	public function sell_limit($units, $pair, $price, $expiry, $rest = FALSE) {
		//Sell limit with expiry
		return $this->limit('sell', $units, $pair, $price, $expiry, $rest);
	}
	public function sell_stop($units, $pair, $price, $expiry, $rest = FALSE) {
		//Sell stop with expiry
		return $this->stop('sell', $units, $pair, $price, $expiry, $rest);
	}
	public function sell_mit($units, $pair, $price, $expiry, $rest = FALSE) {
		//Sell marketIfTouched with expiry
		return $this->mit('sell', $units, $pair, $price, $expiry, $rest);
	}
	public function sell_bearish($pair, $risk, $stop, $leverage=50) {
		//Macro: Sell $pair and limit size to equal %NAV loss over $stop pips. Then set stopLoss

		//Retrieve current price
		if (! $this->valid($price = $this->price($pair)))
			return $price;
		
		//Find the correct size so that $risk is divided by $pips
		if (! $this->valid($size = $this->nav_size_percent_per_pip($pair, ($risk/$stop))))
			return $size;
		
		if (! $this->valid($newTrade = $this->sell_market($size, $pair)))
			return $newTrade;
		
		//Set the stoploss
		return $this->trade_set_stop($newTrade->tradeId, $price->bid - ($this->instrument_pip($pair) * $stop));
	}
	
	//////////////////////////////////////////////////////////////////////////////////
	//
	//	PRICE WRAPPERS
	//
	//////////////////////////////////////////////////////////////////////////////////
	
	protected function candle_time_to_seconds($candle) {
		//Convert the timing of $candle from microseconds to seconds
		$candle->time = $this->time_seconds($candle->time);
		return $candle;
	}
	
	protected function candles_times_to_seconds($candles) {
		//Convert the times of $candles from microseconds to seconds
		if ($this->valid($candles))
			$candles->candles = array_map(array($this, "candle_time_to_seconds"), $candles->candles);
		return $candles;
	}
	
	public function price($pair) {
		//Wrapper, return the current price of '$pair'
		return ($this->valid($prices = $this->prices(array($pair)))) ? $prices->prices[0] : $prices;
	}
	
	public function prices($pairs) {
		//Return a jsonObject {prices} for {$pairs}
		return $this->get('prices', array('instruments' => implode(',', $pairs)));
	}
	
	public function price_time($pair, $date) {
		//Wrapper, return the price of '$pair' at $date which is a string such as "20:15 5th november 2012"
		return ($this->valid($candles = $this->candles_time($pair, 'S5', ($time=strtotime($date)), $time+10))) ?
									 $candles->candles[0] : $candles;
	}

	public function candles($pair, $gran, $rest = null, $candleFormat = "midpoint") {
		//Return a number of candles for '$pair'
		
		//Defaults for $rest
		$rest = is_array($rest) ? $rest : array('count' => 1);
		
		//If we passed an array with no start time, then choose one candle
		if (!isset($rest['count']) && !isset($rest['start']))
			$rest['count'] = 1;
		
		//Setup stamdard options
		$candleOptions = array( 
			'candleFormat'  => $candleFormat,
			'instrument'    => $pair, 
			'granularity'   => strtoupper($gran)
		);
		
		//Check for rest processing
		if (is_array($rest))
			foreach ($rest as $key => $value) 
				$candleOptions[$key] = $value;

		//Check the object
		return ($this->valid($candles = $this->get('candles', $candleOptions))) ? 
									 $this->candles_times_to_seconds($candles) : $candles;
	}
	
	public function candles_time($pair, $gran, $start, $end) {
		//Return candles for '$pair' between $start and $end
		return $this->candles($pair, $gran, array('start' => $start, 'end' => $end));
	}
	
	public function candles_count($pair, $gran, $count) {
		//Return $count of the previous candles for '$pair'
		return $this->candles($pair, $gran, array('count' => $count));
	}
}

