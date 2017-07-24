<?php
define ("YEAR_2000", mktime(0, 0, 0, 1, 1, 2000));
define ("YEAR_2001", mktime(0, 0, 0, 1, 1, 2001));
define ("YEAR_2002", mktime(0, 0, 0, 1, 1, 2002));
define ("YEAR_2003", mktime(0, 0, 0, 1, 1, 2003));
define ("YEAR_2004", mktime(0, 0, 0, 1, 1, 2004));
define ("YEAR_2005", mktime(0, 0, 0, 1, 1, 2005));
define ("YEAR_2006", mktime(0, 0, 0, 1, 1, 2006));
define ("YEAR_2007", mktime(0, 0, 0, 1, 1, 2007));
define ("YEAR_2008", mktime(0, 0, 0, 1, 1, 2008));
define ("YEAR_2009", mktime(0, 0, 0, 1, 1, 2009));
define ("YEAR_2010", mktime(0, 0, 0, 1, 1, 2010));
define ("YEAR_2011", mktime(0, 0, 0, 1, 1, 2011));
define ("YEAR_2012", mktime(0, 0, 0, 1, 1, 2012));
define ("YEAR_2013", mktime(0, 0, 0, 1, 1, 2013));
define ("YEAR_2014", mktime(0, 0, 0, 1, 1, 2014));
define ("YEAR_2015", mktime(0, 0, 0, 1, 1, 2015));
define ("YEAR_2016", mktime(0, 0, 0, 1, 1, 2016));
define ("YEAR_2017", mktime(0, 0, 0, 1, 1, 2017));
define ("YEAR_2018", mktime(0, 0, 0, 1, 1, 2018));
define ("YEAR_2019", mktime(0, 0, 0, 1, 1, 2019));
define ("YEAR_2020", mktime(0, 0, 0, 1, 1, 2020));

define ("USDCHF_UNPEGGED", 1421366400);		// jan 16, 2015
define ("POST_BREXIT", 1466812800);			// june 25, 2016
define ("POST_ELECTION_2016", 1478692800);	// nov 9, 2016



/*
* Backtest account class
*
* Keep track of account info for back testing purposes
*
*
*
* ======================================= ACCOUNT INFO ===================================
stdClass Object
(
    [accountId] => 7854977
    [accountName] => Engulf
    [balance] => 91.4082
    [unrealizedPl] => 0
    [realizedPl] => -8.5623
    [marginUsed] => 0
    [marginAvail] => 91.4082
    [openTrades] => 0
    [openOrders] => 0
    [marginRate] => 0.05
    [accountCurrency] => USD
    [unrealizedPlPerc] => 0.0000
    [NAV] => 91.4082
    [PL] => 0
)

*/


class BacktestAccount {

	public $accountId = "999999999";
	public $accountName = "myAccount";
	private $backtestDBDir = "backtestDB";
	private $logFile = "backtest.log";
	private $statsFile = "backtest.stats.csv";
	private $correlationFile = "backtestCorrelation.stats.csv";
	private $leverage = 20;
	private $balance = 0;
	private $perfectBalance = 0;
	private $unrealizedPl = 0;
	private $unrealizedPlPerc = 0;
	private $realizedPl = 0;
	private $marginUsed = 0;
	private $marginAvail = 0;
	private $openTrades = 0;
	private $openOrders = 0;
	private $NAV = 0;
	private $accountCurrency = "USD";
	private $longTradeCount = 0;
	private $shortTradeCount = 0;
	private $profitableTradeCount = 0;
	private $unprofitableTradeCount = 0;
	private $profitableTradeCountPerc = 0;
	private $unprofitableTradeCountPerc = 0;
	private $TPcount = 0;
	private $SLcount = 0;
	private $withdrawls = 0;
	private $deposits = 0;
	private $transferIn = 0;
	private $transferOut = 0;
	private $totalTradeTime = 0;
	private $avgTimePerTrade = 0;
	private $totalWinTradeTime = 0;
	private $avgTimePerWinTrade = 0;
	private $totalLossTradeTime = 0;
	private $avgTimePerLossTrade = 0;
	private $trades = array();
	private $orders = array();
	private $pairPerformance = array();
	private $hourPerformance = array();
	private $dayPerformance = array();
	private $monthPerformance = array();
	private $yearPerformance = array();
	
	public $corrSnapshotTime = 86400;     // how often to take correlation snapshots (seconds)
	private $corrLastSnapshotTime = 0;    // last time a correlation snapshot was taken
	public $corrBalance = array();        // balance array for correlations
	public $corrPerfectBalance = array(); // perfectBalance array for correlations
	
	private $perTradePLArr = array();		// array of each trade's P&L
	private $perTradePLPercArr = array();	// array of each trade's P&L percentage
	public $perTradePLStddev = 0;			// std dev of each trade's P&L

	// http://www.onestepremoved.com/probability-tools/?utm_source=OneStepRemoved.com+Trading+Strategies&utm_campaign=deea204cf2-Probability+Tools+for+Better+Trading&utm_medium=email&utm_term=0_6cab771538-deea204cf2-420613505&mc_cid=deea204cf2&mc_eid=b5702f9af0
	private $strategyDailyReturnPerc = array();				// for calculating sharpe ratio
	private $RFRperYearPerc = .030;		// risk-free return per year for calculating sharpe ratio (long term "safe" investments) (http://money.cnn.com/data/bonds/)
	private $RFRperDayPerc;					// risk-free return per day
	
	private $maxDrawdown = 0;
	private $maxDrawdownPerc = 0;
	
	
	
	// time of the current tick...should match  beginning time of the candle
	private $tickTime = 0;
	public $endTickTime = 0;
	private $granularity = "H1";   // http://developer.oanda.com/rest-live/rates/#retrieveInstrumentHistory
	private $db = NULL;
	private $tradeID = 0;
	private $orderID = 0;
	
	
	private $toUsdPairs = array(
		"AUD" => "AUD_USD",
		"JPY" => "USD_JPY",
		"EUR" => "EUR_USD",
		"CAD" => "USD_CAD",
		"CHF" => "USD_CHF",
		"GBP" => "GBP_USD",
		"NZD" => "NZD_USD",
	);
	
	
	private $priceCache = array();


	/////////////////
	// Constructor //
	/////////////////
	public function __construct($accountId = "999999999", $accountName = "myAccount", $openingBalance=0, $granularity="H1", $tickTime=0, $logFile=NULL, $statsFile=NULL, $correlationFile=NULL)
	{
		$this->accountId = $accountId;
		$this->accountName = $accountName;
		$this->balance = $openingBalance;
		$this->perfectBalance = $openingBalance;
		$this->NAV = $this->balance;
		$this->marginAvail = $this->NAV;
		$this->granularity = $granularity;
		$this->RFRperDayPerc = $this->RFRperYearPerc / 365;	// risk-free return per day

		if (!$this->sqliteCandleDbExists() && $this->db == NULL) {
			print "Cannot find candle db: ".__DIR__."/".$this->backtestDBDir."/candles-".$granularity.".db\n";
			print "DIE\n";
			exit;

		} else if ($this->db == NULL) {
			$this->sqliteConnectToDB();

		}


		if ($tickTime > 0 && $tickTime != NULL) {
			$this->tickTime = $tickTime;
		} else {
			$this->tickTime = $this->getMinTickTime($granularity);
		}
				

		if ($logFile != NULL) {
			$this->logFile = $logFile;
		}
		
		if ($statsFile != NULL) {
			$this->statsFile = $statsFile;
		}
		
		if ($correlationFile != NULL) {
			$this->correlationFile = $correlationFile;
		}

		// clobber the logFile and statsFile
		if (file_exists($this->logFile)) { unlink($this->logFile); }
		if (file_exists($this->statsFile)) { unlink($this->statsFile); }
		if (file_exists($this->correlationFile)) { unlink($this->correlationFile); }
	}


	/////////////////////////////////////////
	// Get current ticktime
	/////////////////////////////////////////
	public function getTickTime()
	{
		return $this->tickTime;
	}

	/////////////////////////////////////////
	// Get current tradeID
	/////////////////////////////////////////
	public function getTradeID()
	{
		return $this->tradeID;
	}

	/////////////////////////////////////////
	// Get min tickTime from the sqlite db //
	/////////////////////////////////////////
	public function getMinTickTime($granularity=NULL, $instrument=NULL)
	{
		if (!$this->sqliteCandleDbExists()) {
			print "Cannot find candle db: ".__DIR__."/candles-".$granularity.".db\n";
			print "DIE\n";
			exit;
		}

		if ($granularity != NULL) {
			$this->granularity = $granularity;
		}
		
		$this->sqliteConnectToDB();
		
		if ($instrument != NULL) {
			$query = "SELECT MIN(time) as minTime FROM candles WHERE instrument='".$instrument."'";
		} else {
			$query = "SELECT MIN(time) as minTime FROM candles";
		}
		
		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);
		
		return $row['minTime'];
	}



	/////////////////////////////////////////
	// Check if the candle db file exists
	/////////////////////////////////////////
	private function sqliteCandleDbExists($granularity = NULL)
	{
		if ($granularity != NULL) {
			$this->granularity = $granularity;
		}

		if (!file_exists(__DIR__."/".$this->backtestDBDir."/candles-".$this->granularity.".db")) {
			return false;
		} else {
			return true;
		}
	}


	/////////////////////////////////////////
	// Connect to the sqlite db
	/////////////////////////////////////////
	private function sqliteConnectToDB()
	{
		if ($this->db == NULL) {
			$this->db = new SQLite3(__DIR__."/".$this->backtestDBDir."/candles-".$this->granularity.".db");
		}
	}


	/////////////////
	// Get array of account info
	/////////////////
	public function accountInfo()
	{
		$info = array("accountId" => $this->accountId,
						"accountName" => $this->accountName,
						"leverage" => $this->leverage,
						"balance" => $this->balance,
						"perfectBalance" => $this->perfectBalance,
						"unrealizedPl" => $this->unrealizedPl,
						"realizedPl" => $this->realizedPl,
						"marginUsed" => $this->marginUsed,
						"marginAvail" => $this->marginAvail,
						"openTrades" => $this->openTrades,
						"openOrders" => $this->openOrders,
						"unrealizedPlPerc" => $this->unrealizedPlPerc,
						"NAV" => $this->NAV,
						"longTradeCount" => $this->longTradeCount,
						"shortTradeCount" => $this->shortTradeCount,
						"profitableTradeCount" => $this->profitableTradeCount,
						"unprofitableTradeCount" => $this->unprofitableTradeCount,
						"profitableTradeCountPerc" => $this->profitableTradeCountPerc,
						"unprofitableTradeCountPerc" => $this->unprofitableTradeCountPerc,
						"TPcount" => $this->TPcount,
						"SLcount" => $this->SLcount,
						"totalTradeTime" => $this->totalTradeTime,
						"avgTimePerTradeHours" => $this->avgTimePerTrade / 60 / 60,
						"avgTimePerTradeDays" => $this->avgTimePerTrade / 86400,
						"totalWinTradeTime" => $this->totalWinTradeTime,
						"avgTimePerWinTradeHours" => $this->avgTimePerWinTrade / 60 / 60,
						"avgTimePerWinTradeDays" => $this->avgTimePerWinTrade / 86400,
						"totalLossTradeTime" => $this->totalLossTradeTime,
						"avgTimePerLossTradeHours" => $this->avgTimePerLossTrade / 60 / 60,
						"avgTimePerLossTradeDays" => $this->avgTimePerLossTrade / 86400,
						"deposits" => $this->deposits,
						"withdrawls" => $this->withdrawls,
						"transferIn" => $this->transferIn,
						"transferOut" => $this->transferOut,
		);
		
		$object = json_decode(json_encode($info), FALSE);
		return $object;
	}
	
	

	/////////////////
	// Get correlation and linear regression results (betweeen perfect trades and the actual trades)
	/////////////////
	public function getWrapupVariables()
	{
		$regObj = new LinearRegression($this->corrPerfectBalance, $this->corrBalance);
		$statObj = new Statistics();
		$this->calculateMaxDrawdown();
		
		$ret = array("LRslope" => $regObj->slope,
						"LRintercept" => $regObj->intercept,
						"LRrSquared" => $regObj->coefficentOfDetermination(),
						"perTradePLStddev" => $statObj->standardDeviation($this->perTradePLArr),
						"perTradeMeanReturn" => $statObj->mean($this->perTradePLArr),
						"perTradeMeanReturnPerc" => $statObj->mean($this->perTradePLPercArr),
						"sharpeRatio" => $this->getSharpeRatio(),
						"maxDrawdown" => $this->maxDrawdown,
						"maxDrawdownPerc" => $this->maxDrawdownPerc,
						"minBalance" => min($this->corrBalance),
						"maxBalance" => max($this->corrBalance),
						);
		
		$object = json_decode(json_encode($ret), FALSE);
		return $object;
	}
	
	
	
	/////////////////
	// Get performance for pairs
	/////////////////
	public function getPairPerformance()
	{
		$object = json_decode(json_encode($this->pairPerformance), FALSE);
		return $object;
	}



	/////////////////
	// Get performance for hours of the day
	/////////////////
	public function getHourPerformance()
	{
		$object = json_decode(json_encode($this->hourPerformance), FALSE);
		return $object;
	}
	
	
	
	/////////////////
	// Get performance for days of the week
	/////////////////
	public function getDayPerformance()
	{
		$object = json_decode(json_encode($this->dayPerformance), FALSE);
		return $object;
	}
	
	
	
	/////////////////
	// Get performance for months of the year
	/////////////////
	public function getMonthPerformance()
	{
		$object = json_decode(json_encode($this->monthPerformance), FALSE);
		return $object;
	}


	
	/////////////////
	// Get year performance
	/////////////////
	public function getYearPerformance()
	{
		$object = json_decode(json_encode($this->yearPerformance), FALSE);
		return $object;
	}



	/////////////////
	// calculate Sharpe ratio
	// http://www.onestepremoved.com/probability-tools/
	// https://en.wikipedia.org/wiki/Sharpe_ratio
	// http://investexcel.net/calculating-the-sharpe-ratio-with-excel/
	/////////////////
	public function getSharpeRatio()
	{
		$statObj = new Statistics();

		// excess return
		$excessReturnArr = array();
		
		foreach ($this->strategyDailyReturnPerc AS $r) {
			$excessReturnArr[] = $r - $this->RFRperDayPerc;
		}
		
		$excessReturnMean = $statObj->mean($excessReturnArr);
		$excessReturnStddev = $statObj->standardDeviation($excessReturnArr);
		
		$sharpe = $excessReturnMean / $excessReturnStddev;
		return $sharpe;
	}

	
	/////////////////
	// Set leverage
	/////////////////
	public function setLeverage ($leverage=20)
	{
		$this->leverage = $leverage;
	}
	
	
	
	/////////////////
	// Set opening balance
	/////////////////
	public function setOpeningBalance ($newBalance = 0)
	{
		if ($this->balance == 0) {
			$this->balance = $newBalance;
		}
	}



	/////////////////
	// Return quote price for current tickTime
	/////////////////
	public function price($instrument, $tickTime = NULL)
	{
		if ($tickTime == NULL) {
			$tickTime = $this->tickTime;
		}

		$query = "SELECT
					time,
					instrument,
					openBid AS bid,
					openAsk AS ask
				FROM candles
				WHERE time = '".$tickTime."' AND instrument = '".$instrument."'";

//		print "$query\n";
	
		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);

		if ($row['time'] != "") {
			$this->priceCache[$instrument]['tickTime'] = $tickTime;
			$this->priceCache[$instrument]['bid'] = $row['bid'];
			$this->priceCache[$instrument]['ask'] = $row['ask'];
		} else {
			$row['instrument'] = $instrument;
			$row['bid'] = $this->priceCache[$instrument]['bid'];
			$row['ask'] = $this->priceCache[$instrument]['ask'];
		}


		$object = json_decode(json_encode($row), FALSE);
		return $object;
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////
	// Return array of candle objects available for instrument
	/////////////////////////////////////////////////////////////////////////////////////
	public function getCandles($instrument, $numCandles=100, $tickTime=NULL)
	{
		if ($tickTime == NULL) {
			$tickTime = $this->tickTime;
		}

		$returnArr = array();
		$returnArr['candles'] = array();

		// determine start and end time as well as difference between the two
		$startTime = $tickTime;
		
		if ($numCandles > 1) {
			$endTime = $tickTime - ($this->gran_seconds($this->granularity) * ($numCandles-1));
		} else {
			$endTime = $tickTime - $this->gran_seconds($this->granularity);
		}
		
		$timeDiff = $startTime - $endTime;


		// get min tick time for this instrument
		$query = "SELECT MIN(time) AS minTickTime FROM candles WHERE instrument='".$instrument."'";
		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);
		$minTickTime = $row['minTickTime'];


		// while candles retrieved is less than requested candles, keep incrementing endTime by timeDiff
		do {
			
			$query = "SELECT * FROM candles WHERE instrument='".$instrument."' AND time <= '".$tickTime."' AND time >= '".$endTime."' ORDER BY time";
			$res = $this->db->query($query);

			// stupid sqlite3 functions grr...can't get a simple row count
			$numRows = 0;
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				$numRows++;
			}
			$res->reset();
			
			$endTime -= $timeDiff;
			
		} while ($numRows < $numCandles && $endTime >= $minTickTime);


		// fill in the candles array
		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$row['openMid'] = ($row['openBid'] + $row['openAsk']) / 2;
			$row['highMid'] = ($row['highBid'] + $row['highAsk']) / 2;
			$row['lowMid'] = ($row['lowBid'] + $row['lowAsk']) / 2;
			$row['closeMid'] = ($row['closeBid'] + $row['closeAsk']) / 2;
			$returnArr['candles'][] = $row;
		}


		// trim the candle array to the desired length
		if (count($returnArr['candles']) > $numCandles) {
			$returnArr['candles'] = array_slice($returnArr['candles'], -1 * $numCandles);
		}
		
		
		// convert candles to json object
		$object = json_decode(json_encode($returnArr), FALSE);
		return $object;
	}
	
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Return number of seconds based on a granularity string
	/////////////////////////////////////////////////////////////////////////////////////
	private function gran_seconds($granularity=NULL)
	{
		if ($granularity == NULL) {
			$granularity = $this->granularity;
		}
		
		if ($granularity == "M1") { return 60; }
		if ($granularity == "M5") { return 300; }
		if ($granularity == "M10") { return 600; }
		if ($granularity == "H1") { return 3600; }
		if ($granularity == "D") { return 86400; }
		if ($granularity == "W") { return 86400*7; }

		else { return 0; }
	}
	
	
	//////////////////////////////////////////////////////////////////////////
	// check if there is tick data available for a given time and instrument
	// used for error checking
	//////////////////////////////////////////////////////////////////////////
	public function tickDataAvailable($instrument, $time)
	{
		$query = "SELECT
					time,
					instrument
				FROM candles
				WHERE time = '".$time."' AND instrument = '".$instrument."'";

//		print "$query\n";
	
		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);

		if ($row['time'] != "") {
			return true;
		} else {
			return false;
		}
		
	}
	
	
	/////////////////
	// buy market
	/////////////////
	public function buy_market($units, $pair, $rest = FALSE, $bidPrice=NULL, $askPrice=NULL)
	{

		if ($units < 1) {
			$this->backtestLog("ERROR: Attempted negative units.  pair:$pair  type:BUY MARKET  units:$units  tick: ".$this->tickTime);
			return false;
		}

		// figure out prices
		// bid and ask prices sent to this function...it's probably an order that's been placed
		if ($bidPrice != NULL && $askPrice != NULL) {
			$quote = new stdClass();
			$quote->bid = $bidPrice;
			$quote->ask = $askPrice;
		} else {
			// bid and ask not sent, get current quote for tickTime
			// get current quote for this pair
			$quote = $this->price($pair, $this->tickTime);
		}


		// get parameters for this trade
		if (is_array($rest) && isset($rest['takeProfit'])) { $TP = $rest['takeProfit']; }
		else { $TP = NULL; }
		
		if (is_array($rest) && isset($rest['stopLoss'])) { $SL = $rest['stopLoss']; }
		else { $SL = NULL; }


		// check if tick data is available here.  if not, drop log and return false
		if ($this->tickDataAvailable($pair, $this->tickTime) === false) {
			$this->backtestLog("ERROR: No tick data available for this trade.  pair:$pair  type:BUY MARKET  units:$units  desired tick: ".$this->tickTime);
			return false;
		}

		// check margin to see if it can handle this trade
		// how much is the trade going to cost? value in USD
		$tradeCost = $this->tradeCost($pair, $units, $quote->ask);
		
		if ($tradeCost > $this->marginAvail * $this->leverage) {
			$this->backtestLog("ERROR: Not enough margin available for this trade.  pair:$pair  type:BUY MARKET  units:$units  price:".$quote->ask);
			$this->backtestLog("ERROR: Trade cost: ".$tradeCost."   marginAvail:".$this->marginAvail."   leverage:".$this->leverage);

			return false;
		}


		// error checking bounds checks
		// TP can't be lower than current quote
		if ($TP != NULL && $TP < $quote->bid) {
			$this->backtestLog("ERROR: TP can't be lower than current bid price.  pair:$pair  type:BUY MARKET  units:$units  TP:$TP  price:".$quote->bid);
			return false;
		}
		
		// SL can't be higher than the current quote
		if ($SL != NULL && $SL > $quote->bid) {
			$this->backtestLog("ERROR: SL can't be higher than current bid price.  pair:$pair  type:BUY MARKET  units:$units  SL:$SL  price:".$quote->bid);
			return false;
		}
		

		

		// increment tradeID
		$this->tradeID++;
		

		// check if trade side conflicts
		// with any other trades for this instrument...
		// ie already have a sell trade for EUR_USD for 100 units
		// then want to enter a buy trade for EUR_USD for 50 units.
		// in this case, FIFO rules apply
		// the earliest sell trade for EUR_USD is reduced by 50 units
		// and profit of those 50 units is applied to the balance and NAV
		// future...
		
		

		// "normal" trade where no trades exist with opposite side for this instrument.
		// set up array for this trade
		$t = array("id" => $this->tradeID,
					"units" => $units,
					"side" => "buy",
					"instrument" => $pair,
					"time" => $this->tickTime,
					"price" => $quote->ask,
					"takeProfit" => $TP,
					"stopLoss" => $SL,
					"humanTime" => date("c", $this->tickTime));

		$this->trades[] = $t;

		// update NAV after trade is put into the trades array
		$this->updateNav();
		
		// log entry
		$this->backtestLog("pair:$pair  id:".$this->tradeID."  type:BUY MARKET  units:$units  price:".$quote->ask);

		// increment trade count (total long trades for the life of the backtest)
		$this->longTradeCount++;
		
		// return success
		return true;
	}
	
	
	/////////////////
	// sell market
	/////////////////
	public function sell_market($units, $pair, $rest = FALSE, $bidPrice=NULL, $askPrice=NULL)
	{

		if ($units < 1) {
			$this->backtestLog("ERROR: Attempted negative units.  pair:$pair  type:SELL MARKET  units:$units  tick: ".$this->tickTime);
			return false;
		}


		// figure out prices
		// bid and ask prices sent to this function...it's probably an order that's been placed
		if ($bidPrice != NULL && $askPrice != NULL) {
			$quote = new stdClass();
			$quote->bid = $bidPrice;
			$quote->ask = $askPrice;
		} else {
			// bid and ask not sent, get current quote for tickTime
			// get current quote for this pair
			$quote = $this->price($pair, $this->tickTime);
		}


		// get parameters for this trade
		if (is_array($rest) && isset($rest['takeProfit'])) { $TP = $rest['takeProfit']; }
		else { $TP = NULL; }
		
		if (is_array($rest) && isset($rest['stopLoss'])) { $SL = $rest['stopLoss']; }
		else { $SL = NULL; }


		// check if tick data is available here.  if not, drop log and return false
		if ($this->tickDataAvailable($pair, $this->tickTime) === false) {
			$this->backtestLog("ERROR: No tick data available for this trade.  pair:$pair  type:SELL MARKET  units:$units  desired tick: ".$this->tickTime);
			return false;
		}
		

		// check margin to see if it can handle this trade
		// how much is the trade going to cost? value in USD
		$tradeCost = $this->tradeCost($pair, $units, $quote->bid);
		
		if ($tradeCost > $this->marginAvail * $this->leverage) {
			$this->backtestLog("ERROR: Not enough margin available for this trade.  pair:$pair  type:SELL MARKET  units:$units  price:".$quote->bid);
			$this->backtestLog("ERROR: Trade cost: ".$tradeCost."   marginAvail:".$this->marginAvail."   leverage:".$this->leverage);
			return false;
		}


		// error checking bounds checks
		// TP can't be higher than current quote
		if ($TP != NULL && $TP > $quote->ask) {
			$this->backtestLog("ERROR: TP can't be higher than current ask price.  pair:$pair  type:SELL MARKET  units:$units  TP:$TP  price:".$quote->ask);
			return false;
		}
		
		// SL can't be lower than the current quote
		if ($SL != NULL && $SL < $quote->ask) {
			$this->backtestLog("ERROR: SL can't be lower than current ask price.  pair:$pair  type:SELL MARKET  units:$units  SL:$SL price:".$quote->ask);
			return false;
		}
		


		// increment tradeID
		$this->tradeID++;
		

		// check if trade side conflicts
		// with any other trades for this instrument...
		// ie already have a sell trade for EUR_USD for 100 units
		// then want to enter a buy trade for EUR_USD for 50 units.
		// in this case, FIFO rules apply
		// the earliest sell trade for EUR_USD is reduced by 50 units
		// and profit of those 50 units is applied to the balance and NAV
		// future...
		
		

		// "normal" trade where no trades exist with opposite side for this instrument.
		// set up array for this trade
		$t = array("id" => $this->tradeID,
					"units" => $units,
					"side" => "sell",
					"instrument" => $pair,
					"time" => $this->tickTime,
					"price" => $quote->bid,
					"takeProfit" => $TP,
					"stopLoss" => $SL);

		$this->trades[] = $t;


		// update NAV after trade is put into the trades array
		$this->updateNav();
		
		// log entry
		$this->backtestLog("pair:$pair  id:".$this->tradeID."  type:SELL MARKET  units:$units  price:".$quote->bid);

		// increment trade count (total short trades over the life of the backtest)
		$this->shortTradeCount++;

		// return success
		return true;
	}


	///////////////////////////////////////////////////
	// return trade info for a specific trade ID
	///////////////////////////////////////////////////
	public function trade($tradeID)
	{
		$trades = $this->trade_all();
		foreach ($trades as $t) {
			if ($t['id'] == $tradeID) {
				$object = json_decode(json_encode($t), FALSE);
				return $object;
			}
		}
	}


	///////////////////////////////////////////////////
	// return all trades for a single pair
	///////////////////////////////////////////////////
	public function trade_pair($pair, $number=50)
	{
//		print_r($this->trades);
		
		$returnArr = array();
		$returnArr['trades'] = array();
		
		foreach ($this->trades as $t) {

			if ($t['instrument'] == $pair) {
				$returnArr['trades'][] = $t;
			}
			
		}

		$object = json_decode(json_encode($returnArr), FALSE);
		return $object;
	}


	
	///////////////////////////////////////////////////
	// return all active trades as 2-dim array
	///////////////////////////////////////////////////
	public function trade_all()
	{
		return $this->trades;
//		print_r($this->trades);
	}


	///////////////////////////////////////////////////
	// Set the stopLoss of a trade
	///////////////////////////////////////////////////
	public function trade_set_stop($id, $SL)
	{
		foreach ($this->trades as $idx=>$t) {

			if ($t['id'] == $id) {
				
				// error checking
				$q = $this->price($t['instrument']);
				
				if ($t['side'] == "buy" && $SL >= $q->bid) {
					$this->backtestLog("ERROR: SL can't be higher than current bid price.  id:".$t['id']."  pair:".$t['instrument']."  type:LONG ADJUST SL  SL:$SL  price:".$q->bid);
					return false;
				} else if ($t['side'] == "sell" && $SL <= $q->ask) {
					$this->backtestLog("ERROR: SL can't be lower than current ask price.  id:".$t['id']."  pair:".$t['instrument']."  type:SHORT ADJUST SL  SL:$SL  price:".$q->ask);
					return false;
				} else {
					$this->backtestLog("type: ADJUST SL  id:".$t['id']."  pair:".$t['instrument']."  old SL:".$t['stopLoss']."  new SL:$SL");
					$this->trades[$idx]['stopLoss'] = $SL;
					return true;
				}
				
				break;
			}
		}

	}
	
	

	///////////////////////////////////////////////////
	// Set the takeProfit of a trade
	///////////////////////////////////////////////////
	public function trade_set_tp($id, $TP)
	{
		foreach ($this->trades as $idx=>$t) {

			if ($t['id'] == $id) {
					
				// error checking
				$q = $this->price($t['instrument']);
				if ($t['side'] == "buy" && $TP <= $q->bid) {
					$this->backtestLog("ERROR: TP can't be lower than current bid price.  id:".$t['id']."  pair:".$t['instrument']."  type:LONG ADJUST TP  TP:$TP  price:".$q->bid);
					return false;
				} else if ($t['side'] == "sell" && $TP >= $q->ask) {
					$this->backtestLog("ERROR: TP can't be higher than current ask price.  id:".$t['id']."  pair:".$t['instrument']."  type:SHORT ADJUST TP  TP:$TP  price:".$q->ask);
					return false;
				} else {
					$this->backtestLog("type: ADJUST TP  id:".$t['id']."  pair:".$t['instrument']."  old TP:".$t['takeProfit']."  new TP:$TP");
					$this->trades[$idx]['takeProfit'] = $TP;
					return true;
				}
				
				break;
			}
		}
	}


	
	///////////////////////////////////////////////////
	// close a trade
	///////////////////////////////////////////////////
	public function trade_close($tradeID, $closingPrice = NULL)
	{

		foreach ($this->trades as $idx=>$t) {

			if ($t['id'] == $tradeID) {
				
				if ($closingPrice === NULL) {
					$q = $this->price($t['instrument']);

					if ($t['side'] == "buy")  { $closingPrice = $q->bid; }
					if ($t['side'] == "sell") { $closingPrice = $q->ask; }
				}

				// calculate total trade count
				$totalTrades = $this->longTradeCount + $this->shortTradeCount;

				// calculate PL for this trade
				$profitUSD = $this->calculateProfit($t['instrument'], $t['side'], $t['units'], $t['price'], $closingPrice);
				
				// update balance with the calculated PL
				$this->balance += $profitUSD;
				$this->realizedPl += $profitUSD;
				
				// calculate the perfect trade profit (max profit from the $t['time'] to tickTime)
				$perfectProfitUSD = $this->calculatePerfectProfit($t['instrument'], $t['side'], $t['units'], $t['price'], $t['time']);
				$this->perfectBalance += $perfectProfitUSD;
				
				// update the per trade P&L array
				$this->perTradePLArr[] = $profitUSD;
				
				// update the per trade P&L percentage array
				$this->perTradePLPercArr[] = $profitUSD / $this->balance;
				
				// update total trade time and avg time per trade (Seconds)
				$this->totalTradeTime += $this->tickTime - $t['time'];
				if ($totalTrades > 0) {
					$this->avgTimePerTrade = $this->totalTradeTime / $totalTrades;
				}
				
				// update profitable and unprofitable trade counts
				// calculated total and avg trade time for each
				if ($profitUSD >= 0) {
					$this->profitableTradeCount++;
					$this->totalWinTradeTime += $this->tickTime - $t['time'];
					$this->avgTimePerWinTrade = $this->totalWinTradeTime / $this->profitableTradeCount;
				} else if ($profitUSD < 0) {
					$this->unprofitableTradeCount++;
					$this->totalLossTradeTime += $this->tickTime - $t['time'];
					$this->avgTimePerLossTrade = $this->totalLossTradeTime / $this->unprofitableTradeCount;
				}
				
				
				// set profitable trade count and unprofitable trade count percentages
				if ($totalTrades > 0) {
					$this->profitableTradeCountPerc = $this->profitableTradeCount / $totalTrades;
					$this->unprofitableTradeCountPerc = $this->unprofitableTradeCount / $totalTrades;
				}
				
				// update individual pair performance array
				if (!isset($this->pairPerformance[$t['instrument']])) {
					$this->pairPerformance[$t['instrument']] = $profitUSD;
				} else {
					$this->pairPerformance[$t['instrument']] += $profitUSD;
				}
				
				
				// update hour performance array
				if (!isset($this->hourPerformance[date("H", $t['time'])])) {
					$this->hourPerformance[date("H", $t['time'])] = $profitUSD;
				} else {
					$this->hourPerformance[date("H", $t['time'])] += $profitUSD;
				}
				
				// update day performance array
				if (!isset($this->dayPerformance[date("D", $t['time'])])) {
					$this->dayPerformance[date("D", $t['time'])] = $profitUSD;
				} else {
					$this->dayPerformance[date("D", $t['time'])] += $profitUSD;
				}
				
				// update month performance array
				if (!isset($this->monthPerformance[date("M", $t['time'])])) {
					$this->monthPerformance[date("M", $t['time'])] = $profitUSD;
				} else {
					$this->monthPerformance[date("M", $t['time'])] += $profitUSD;
				}
				
				// update year performance array
				if (!isset($this->yearPerformance[date("Y", $t['time'])])) {
					$this->yearPerformance[date("Y", $t['time'])] = $profitUSD;
				} else {
					$this->yearPerformance[date("Y", $t['time'])] += $profitUSD;
				}
				
				
				
				
				// remove trade from the array
				unset($this->trades[$idx]);
				$this->trades = array_values($this->trades);
				
				// recalculate NAV
				$this->updateNav();
				
				
				// drop log
				$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  type: TRADE CLOSE  PL:".$profitUSD);
				
				// return
				return;
			}

		}

	}
	
	

	/////////////////
	// Calculate the percentage balance to use in a trade
	/////////////////
	public function nav_size_percent($pair, $percent, $leverage = NULL) {
		//Return the value of a percentage of the NAV (Net account value)
		if ($leverage == NULL) { $leverage = $this->leverage; }
		
		//Calculate the percentage balance to use in the trade
		$percent = $this->balance*($percent/100);
		
		// If home currency in the pair is same as account currency, simply return leveraged amount
		$ins = $this->instrument_split($pair);
		if ($ins[0] == $this->accountCurrency) {
			return ceil($percent * $leverage);
		} else {
			// Otherwise, convert the size to the trade currency
			$baseSize = $this->convert_pair($pair, $percent, $this->accountCurrency);
			return ceil($baseSize * $leverage);
		}
							
		//Calculate and return the leveraged size
		return ceil($baseSize * $leverage);

	}



	/////////////////
	// Split instrument into an array
	/////////////////
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



	/////////////////
	//Convert $amount of $pair from $home
	//
	//	i.e. OandaWrap::convert_pair('EUR_USD', 500, 'EUR'); Converts 500 EUR to USD
	//	i.e. OandaWrap::convert_pair('EUR_USD', 500, 'USD'); Converts 500 USD to EUR
	//
	// USD_JPY
	// convert_pair('USD_JPY', 100, 'JPY');  converts 100 JPY to USD
	/////////////////
	public function convert_pair($pair, $amount, $home, $tickTime=NULL, $bidAsk="mid") {
		
		if ($tickTime == NULL) {
			$tickTime = $this->tickTime;
		}
				
		$pairNames  = $this->instrument_split($pair);
		$homeFirst  = $home == $pairNames[0] ? TRUE : FALSE;
	
		// get quote for this pair
		$quote = $this->price($pair, $tickTime);
		
		if ($bidAsk == "bid") { $q = $quote->bid; }
		else if ($bidAsk == "ask") { $q = $quote->ask; }
		else { $q = ($quote->bid + $quote->ask) / 2; }

		if ($homeFirst) {
			return $amount * $q;
		} else {
			return $amount / $q;
		}

		return 1;
	}
	


	/////////////////
	// Tick forward
	/////////////////
	public function tick()
	{
		/* Process the current candle */

		
		/* Check for orders to place (eventually) */
		


		/* Check for trades to close (SL or TP hit)  */
		foreach ($this->trades as $t) {
			
			// get current candle for this instrument;
			$cObj = $this->getCandles($t['instrument'], 1);

			// if candle found, check for SL andTP
			if (isset($cObj->candles[0])) {

				$c = $cObj->candles[0];
				
				$tradeClosed = false;
				if ($t['side'] == "buy") {

					// check if SL and TP are both in the same candle....if so, drop a log just for informational purposes
					if (isset($t['stopLoss']) && isset($t['takeProfit'])) {
						if ($t['stopLoss'] >= $c->lowBid && $t['stopLoss'] <= $c->highBid) {
							if($t['takeProfit'] >= $c->lowBid && $t['takeProfit'] <= $c->highBid) {
								$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  info: Candle covers SL and TP!  Assuming SL.");
							}
						}
					}
					

					// check for SL first (assume worst case scenario)
					if (isset($t['stopLoss']) && $t['stopLoss'] > 0) {

						if ($t['stopLoss'] >= $c->lowBid && $t['stopLoss'] <= $c->highBid) {
							$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  type: STOP LOSS  price:".$t['stopLoss']);
							$this->trade_close($t['id'], $t['stopLoss']);
							$this->SLcount++;
							$tradeClosed = true;
						}

					} // END: Check for buy SL
					
					// then check for TP
					if ($tradeClosed === false && isset($t['takeProfit']) && $t['takeProfit'] > 0) {

						if ($t['takeProfit'] >= $c->lowBid && $t['takeProfit'] <= $c->highBid) {
							$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  type: TAKE PROFIT  price:".$t['takeProfit']);
							$this->trade_close($t['id'], $t['takeProfit']);
							$this->TPcount++;
							$tradeClosed = true;
						}

					} // END: Check for buy TP

					// END: side == buy
				} else if ($t['side'] == "sell") {

					// check if SL and TP are both in the same candle....if so, drop a log just for informational purposes
					if (isset($t['stopLoss']) && isset($t['takeProfit'])) {
						if ($t['stopLoss'] >= $c->lowAsk && $t['stopLoss'] <= $c->highAsk) {
							if($t['takeProfit'] >= $c->lowAsk && $t['takeProfit'] <= $c->highAsk) {
								$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  info: Candle covers SL and TP!  Assuming SL.");
							}
						}
					}


					// check for SL first (assume worst case scenario)
					if (isset($t['stopLoss']) && $t['stopLoss'] > 0) {

						if ($t['stopLoss'] >= $c->lowAsk && $t['stopLoss'] <= $c->highAsk) {
							$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  type: STOP LOSS  price:".$t['stopLoss']);
							$this->trade_close($t['id'], $t['stopLoss']);
							$this->SLcount++;
							$tradeClosed = true;
						}

					}  // END: Sell check for SL
					
					// then check for TP
					if ($tradeClosed === false && isset($t['takeProfit']) && $t['takeProfit'] > 0) {

						if ($t['takeProfit'] >= $c->lowAsk && $t['takeProfit'] <= $c->highAsk) {
							$this->backtestLog("pair:".$t['instrument']."  id:".$t['id']."  type: TAKE PROFIT  price:".$t['takeProfit']);
							$this->trade_close($t['id'], $t['takeProfit']);
							$this->TPcount++;
							$tradeClosed = true;
						}

					}  // END: Sell Check for TP
					
					// END: side == sell
				}
				
			}  // END: candle was found


		}  // END: loop over trades and check if SL or TP was hit


		
		/* increment tickTime to beginning of next candle */
		$query = "SELECT time FROM candles WHERE time > '".$this->tickTime."' ORDER BY time LIMIT 1";
		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);


		$moreCandles = true;
		if ($row['time'] == "") { $moreCandles = false; }
		if ($this->endTickTime != 0 && $row['time'] > $this->endTickTime) { $moreCandles = false; }


		if ($moreCandles === true) {
			$this->tickTime = $row['time'];
			$this->updateNav();
			$this->updateStatsFile();
			$this->updateCorrFile();

			return true;
		} else {
			print "out of candles folks!\n";
			return false;
		}


	
	
	}

	
	
	/////////////////////////////////////////////////
	// calculate NAV based on outstanding trades
	// http://www.oanda.com/forex-trading/analysis/profit-calculator/how
	// Pair EUR_USD base on left, quote on right.  base = EUR quote=USD
	// Pair USD_JPY base on left, quote on right.  base = USD quote=JPY
	//
	// calculate the PL of ALL trades (put together) and add that PL to the current balance
	/////////////////////////////////////////////////
	private function updateNav()
	{
		$totalTradesPL = 0;
		$totalMarginUsed = 0;

		foreach ($this->trades as $idx=>$t) {
	
			// closing price
			if ($t['side'] == "buy") {
				$p = $this->price($t['instrument']);
				$closingPrice = $p->bid;
			} else if ($t['side'] == "sell") {
				$p = $this->price($t['instrument']);
				$closingPrice = $p->ask;
			}

			$profitUSD = $this->calculateProfit($t['instrument'], $t['side'], $t['units'], $t['price'], $closingPrice);
		
			// update the trade array
			$this->trades[$idx]['PL'] = $profitUSD;
			$totalTradesPL += $profitUSD;
			
			// update the total margin used
			$a = explode("_", $t['instrument']);
			$base = $a[0];
			$quote = $a[1];

			if ($base == "USD") {
				$totalMarginUsed += $t['units'] / $this->leverage;
			} else {
				$totalMarginUsed += $t['units'] * $closingPrice / $this->leverage;
			}
			
/*
			print "pair=".$t['instrument']."\n";
			print "units=".$t['units']."\n";
			print "closingPrice=".$closingPrice."\n";
			print "leverage=".$this->leverage."\n";
			print "totalMarginUsed=".$totalMarginUsed."\n";
*/
				
		} /* END: foreach this->trades */

		$this->NAV = $this->balance + $totalTradesPL;
		$this->unrealizedPl = $totalTradesPL;
		$this->unrealizedPlPerc = $this->unrealizedPl / $this->balance * 100;
		$this->marginUsed = $totalMarginUsed;
		$this->marginAvail = $this->NAV - $this->marginUsed;
		$this->openTrades = count($this->trades);

	}
	



	////////////////////////////////////////////////////////////////////////////////////////////////
	// calculate profit for closing a trade (or for calculating NAV on an ongoing basis)
	// returns profit (in USD)
	////////////////////////////////////////////////////////////////////////////////////////////////
	function calculateProfit($instrument, $side, $units, $tradePrice, $closingPrice)
	{
		$a = explode("_", $instrument);
		$base = $a[0];
		$quote = $a[1];

		if ($closingPrice == 0) { print "function calculateProfit 1101 - closingPrice = 0  STOP!"; exit; }


		if ($side == "buy") {
			// profit (either in base or quote currency)
			$profit = $closingPrice * $units - $tradePrice * $units;
		}
		

		if ($side == "sell") {
			// profit (either in base or quote currency)
			$profit = $tradePrice * $units - $closingPrice * $units;
		}

		// quote side (RIGHT) is USD
		if ($quote == "USD") {
			$profitUSD = $profit;
		}
		
		// base side (LEFT) is USD
		if ($base == "USD") {
			$closingPriceInverse = 1/$closingPrice;
			$profitUSD = $profit * $closingPriceInverse;
		}

		// neither side is USD.  do something else
		if ($quote != "USD" && $base != "USD") {
			// TODO
		}
		
		return $profitUSD;
	}
	
	
	
	////////////////////////////////////////////////////////////////////////////////////////////////
	// calculate perfect profit for closing a trade (or for calculating NAV on an ongoing basis)
	// returns perfect profit (in USD)
	////////////////////////////////////////////////////////////////////////////////////////////////
	function calculatePerfectProfit($instrument, $side, $units, $tradePrice, $time)
	{
		if ($side == "buy") {
			$query = "SELECT
						MAX(highBid) AS closePrice
					FROM candles
					WHERE time >= '".$time."' AND time <= '".$this->tickTime."' AND instrument = '".$instrument."'";

		} else if ($side == "sell") {
			$query = "SELECT
						MIN(lowAsk) AS closePrice
					FROM candles
					WHERE time >= '".$time."' AND time <= '".$this->tickTime."' AND instrument = '".$instrument."'";

		} else {
			return 0;

		}

		$res = $this->db->query($query);
		$row = $res->fetchArray(SQLITE3_ASSOC);

		return $this->calculateProfit($instrument, $side, $units, $tradePrice, $row['closePrice']);
	}
	



	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// return size of a pip based on pair name
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function instrument_pip($pair)
	{
		if (strstr($pair, "JPY") !== false) {
			return .01;
		} else {
			return .0001;
		}
	}
	
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// deposit funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function deposit($amount)
	{
		$this->balance += $amount;
		$this->deposits += $amount;
		$this->backtestLog("type: DEPOSIT  amount:$amount");
		$this->updateNav();
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// transfer in funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function transferIn($amount)
	{
		$this->balance += $amount;
		$this->transferIn += $amount;
		$this->backtestLog("type: TRANSFER IN  amount:$amount");
		$this->updateNav();
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// withdrawl funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function withdrawl($amount)
	{
		$this->balance -= $amount;
		$this->withdrawls += $amount;
		$this->backtestLog("type: WITHDRAWL  amount:$amount");
		$this->updateNav();
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// withdrawl funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function transferOut($amount)
	{
		$this->balance -= $amount;
		$this->transferOut += $amount;
		$this->backtestLog("type: TRANSFER OUT  amount:$amount");
		$this->updateNav();
	}
	
	


	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// calculate trade cost for a given trade.  Used for calculating if margin has been exceeded during a trade
	// returns cost (in USD)
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function tradeCost($instrument, $units, $tradePrice)
	{
		$a = explode("_", $instrument);
		$base = $a[0];
		$quote = $a[1];

		$cost = $tradePrice * $units;
		
		// quote side (RIGHT) is USD
		if ($quote == "USD") {
			$costUSD = $cost;
		}
		
		// base side (LEFT) is USD
		if ($base == "USD") {
			$priceInverse = 1 / $tradePrice;
			$costUSD = $cost * $priceInverse;
		}

		// neither side is USD.  do something else
		if ($quote != "USD" && $base != "USD") {
			// TODO
		}
		
		return $costUSD;
	}


	////////////////////////////////////////////////
	// write a message out to the backtest log
	////////////////////////////////////////////////
	function backtestLog($message, $tickTime = NULL)
	{
		if ($tickTime == NULL) {
			$tickTime = $this->tickTime;
		}
		
		$d = date("c", $tickTime);
		
		return error_log($d." - ".$message."\n", 3, $this->logFile);
	}



	////////////////////////////////////////////////
	// update stats file with account statistics
	////////////////////////////////////////////////
	function updateStatsFile()
	{
		$data = array();

		if (!file_exists($this->statsFile)) {
			$data[] = array("date",
							"balance",
							"perfectBalance",
							"unrealizedPL",
							"unrealizedPLPerc",
							"NAV",
							"marginUsed",
							"marginAvail",
							"openTrades",
							"openOrders",
							"profitableTradeCount",
							"unprofitableTradeCount",
						);
		}

		$data[] = array(
			date("D M j G:i:s T Y", $this->tickTime),
			sprintf("%.4f", $this->balance),
			sprintf("%.4f", $this->perfectBalance),
			sprintf("%.4f", $this->unrealizedPl),
			sprintf("%.4f", $this->unrealizedPlPerc),
			sprintf("%.4f", $this->NAV),
			sprintf("%.4f", $this->marginUsed),
			sprintf("%.4f", $this->marginAvail),
			$this->openTrades,
			$this->openOrders,
			$this->profitableTradeCount,
			$this->unprofitableTradeCount,
		);

		$fp = fopen($this->statsFile, 'a');

		foreach($data as $line){
			fputcsv($fp, $line);
		}

		fclose($fp);

	}


	/////////////////////////////////////////////////////////////////
	// update the correlation file with perfect balance and balance
	/////////////////////////////////////////////////////////////////
	function updateCorrFile()
	{
		if ($this->tickTime >= $this->corrLastSnapshotTime + $this->corrSnapshotTime) {
			
			$this->corrBalance[] = $this->balance;
			$this->corrPerfectBalance[] = $this->perfectBalance;

			// for sharpe ratio...daily return (percentage)
			if (count($this->corrBalance) > 1) {
				$prevIdx = count($this->corrBalance) - 2;
				$currIdx = count($this->corrBalance) - 1;
				
				$this->strategyDailyReturnPerc[] = ($this->corrBalance[$currIdx] - $this->corrBalance[$prevIdx]) / $this->corrBalance[$prevIdx];
			}
			

			$data = array();
	
			if (!file_exists($this->correlationFile)) {
				$data[] = array("date",
								"perfectBalance",
								"balance",
							);
			}
	
			$data[] = array(
				date("D M j G:i:s T Y", $this->tickTime),
				$this->perfectBalance,
				$this->balance,
			);
	
			$fp = fopen($this->correlationFile, 'a');
	
			foreach($data as $line){
				fputcsv($fp, $line);
			}
	
			fclose($fp);
			
			$this->corrLastSnapshotTime = $this->tickTime;
		}

	}



	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// calculate the max drawdown in the corrBalance array
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	function calculateMaxDrawdown()
	{
		$currentDrawdown = 0;
		$currentDrawdownPerc = 0;

		// loop through all the balance snapshots
		for ($i=0; $i<count($this->corrBalance)-1; $i++) {

			// the next balance snapshot is less than the current balance snapshot
			if ($this->corrBalance[$i+1] < $this->corrBalance[$i]) {
				$troughStartBalance = $this->corrBalance[$i];
				$troughStartIdx = $i;

				// find the end of the current trough and store it in troughEndIdx
				$troughEndIdx = count($this->corrBalance)-1; // default the troughEndIdx to last element of corrBalance
				for ($j=$i+1; $j<count($this->corrBalance)-1; $j++) {
					$i = $j;
					
					if ($this->corrBalance[$j] > $troughStartBalance) {
						$troughEndIdx = $j;
						break;
					}
				}
				
				// find min balance between troughStartIdx and troughEndIdx
				$minBalance = $troughStartBalance;
				for ($k = $troughStartIdx; $k <= $troughEndIdx; $k++) {
					if ($this->corrBalance[$k] < $minBalance) {
						$minBalance = $this->corrBalance[$k];
					}
					
				}
				
				// calculate maxDrawdown
				$currentDrawdown = $troughStartBalance - $minBalance;
				$currentDrawdownPerc = $currentDrawdown / $troughStartBalance;
				
				if ($currentDrawdown > $this->maxDrawdown) {
					$this->maxDrawdown = $currentDrawdown;
					$this->maxDrawdownPerc = $currentDrawdownPerc;
				}
				
			} // END: next balance snapshot less than current snapshot

		}
		
	}
	
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// determine if the month has changed
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	function monthChange()
	{
		if (!isset($this->previousMonth)) {
			$this->previousMonth = date("M", $this->tickTime);
		}

		if (date("M", $this->tickTime) != $this->previousMonth) {
			$this->previousMonth = date("M", $this->tickTime);
			return true;
		} else {
			$this->previousMonth = date("M", $this->tickTime);
			return false;
		}
	}



	
}   // END: BacktestAccount class



