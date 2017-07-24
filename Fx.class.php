<?php
date_default_timezone_set("America/Chicago");

require_once __DIR__."/OandaApi.class.php";
require_once __DIR__."/BacktestAccount.class.php";
require_once __DIR__."/LinearRegression.class.php";
require_once __DIR__."/Statistics.class.php";
require_once __DIR__."/apikey.inc.php";



/*
*
Fx              ---->        Strategy (inherits Fx)     ---->   driver program (implements Strategy)

Fx USES                      Strategy has a function to         calls the Strategy execution function
OandaApi                     execute the strategy and
BacktestAccount              whatever other functions are wanted

*/


class Fx {

	/////////////////
	// Constructor //
	/////////////////
	public function __construct($system="Backtest")
	{
		$this->system = $system;
		$this->acctObj = NULL;
		
		$this->oandaApiKey = NULL;
		$this->oandaAccountId = NULL;
		$this->usleepTime = 100000;
		
		$this->btAccountId = NULL;
		$this->btAccountName = NULL;
		$this->btOpeningBalance = NULL;
		$this->granularity = NULL;
		$this->btStartTickTime = NULL;
		$this->btEndTickTime = NULL;
		$this->btLogFile = NULL;
		$this->btStatsFile = NULL;
		$this->btCorrelationFile = NULL;
		$this->btLeverage = NULL;
		

		if ($system != "Backtest" && $system != "Demo" && $system != "Live") {
			throw new Exception ("Invalid system type.  Expected: Backtest, Demo or Live");
		}
		
	}	// END: constructor



	/////////////////
	// configure the account (either Backtest, Demo or Live)
	/////////////////
	public function configureAccount()
	{
		if ($this->system == "Backtest") {
			$this->configureBacktest();
		} else {
			$this->configureOanda();
		}
	}
	
	
	
	/////////////////
	// Configure backtest account
	/////////////////
	public function configureBacktest()
	{
		if ($this->btAccountId == NULL || $this->btAccountName == NULL || $this->btOpeningBalance == NULL || $this->granularity == NULL || $this->btStartTickTime == NULL || $this->btLogFile == NULL || $this->btStatsFile == NULL || $this->btCorrelationFile == NULL) {
			print "The following object variables are required:\n";
			print "btAccountId\n";
			print "btAccountName\n";
			print "btOpeningBalance\n";
			print "granularity\n";
			print "btStartTickTime\n";
			print "btLogFile\n";
			print "btStatsFile\n";
			print "btCorrelationFile\n";
			throw new Exception ("cannot configure backtest.\n");
		}

		$this->acctObj = new BacktestAccount($this->btAccountId, $this->btAccountName, $this->btOpeningBalance, $this->granularity, $this->btStartTickTime, $this->btLogFile, $this->btStatsFile, $this->btCorrelationFile);
		
		if ($this->btEndTickTime != NULL) {
			$this->acctObj->endTickTime = $this->btEndTickTime;
		}
		
		if ($this->btLeverage != NULL) {
			$this->acctObj->setLeverage($this->btLeverage);
		}
	}
	
	
	
	
	
	/////////////////
	// Configure oanda account
	/////////////////
	public function configureOanda()
	{
		if ($this->oandaApiKey == NULL || $this->oandaAccountId == NULL) {
			print "The following object variables are required:\n";
			print "oandaApiKey\n";
			print "oandaAccountId\n";
			throw new Exception ("cannot setup oanda access.\n");
		}

		$this->acctObj = new OandaApi();
		
		if ($this->acctObj->setup($this->system, $this->oandaApiKey, $this->oandaAccountId) === false) {
			throw new Exception ('cannot setup account.');
		}
		
	}




	/////////////////
	// Get account info
	/////////////////
	public function accountInfo()
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->accountInfo();
			
		} else {
			$acctInfo = $this->acctObj->account($this->oandaAccountId);
			$acctInfo->unrealizedPlPerc = sprintf("%.04f", ($acctInfo->unrealizedPl / $acctInfo->balance * 100));
			$acctInfo->NAV = $acctInfo->balance + $acctInfo->unrealizedPl;
			usleep($this->usleepTime);

			return $acctInfo;
		}
	}




	/////////////////
	// Return quote price for current tickTime
	/////////////////
	public function price($instrument, $tickTime = NULL)
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->price($instrument, $tickTime);
		} else {
			$r = $this->acctObj->price($instrument);
			usleep($this->usleepTime);
			return $r;
		}
	}
	
	
	
	
	/////////////////
	// Return candle data for a pair
	/////////////////
	public function candles($pair, $oGran, $oRest=NULL, $oCandleFormat="midpoint", $btNumCandles=100, $btTickTime=NULL)
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->getCandles($pair, $btNumCandles, $btTickTime);
		} else {
			$r = $this->acctObj->candles($pair, $oGran, $oRest, $oCandleFormat);
			usleep($this->usleepTime);
			return $r;
		}
	}

	
	
	
	
	/////////////////
	// Buy market
	/////////////////
	public function buy_market($units, $pair, $rest=FALSE, $bidPrice=NULL, $askPrice=NULL)
	{
		if (isset($rest['takeProfit'])) { $rest['takeProfit'] = $this->forex_round($pair, $rest['takeProfit']); }
		if (isset($rest['stopLoss'])) { $rest['stopLoss'] = $this->forex_round($pair, $rest['stopLoss']); }

		print "\n";
		print "==================\n";
		print "FxClass-buy_market\n";
		print "pair=$pair\n";
		print "units=$units\n";
		if (isset($rest['stopLoss'])) { print "SL=".$rest['stopLoss']."\n"; }
		if (isset($rest['takeProfit'])) { print "TP=".$rest['takeProfit']."\n"; }
		print "==================\n";

		if ($this->system == "Backtest") {
			return $this->acctObj->buy_market($units, $pair, $rest);
		} else {
			$r = $this->acctObj->buy_market($units, $pair, $rest);
			usleep($this->usleepTime);
			return $r;
		}
		
	}



	/////////////////
	// Sell market
	/////////////////
	public function sell_market($units, $pair, $rest=FALSE, $bidPrice=NULL, $askPrice=NULL)
	{
		if (isset($rest['takeProfit'])) { $rest['takeProfit'] = $this->forex_round($pair, $rest['takeProfit']); }
		if (isset($rest['stopLoss'])) { $rest['stopLoss'] = $this->forex_round($pair, $rest['stopLoss']); }

		print "\n";
		print "==================\n";
		print "FxClass-sell_market\n";
		print "pair=$pair\n";
		print "units=$units\n";
		if (isset($rest['stopLoss'])) { print "SL=".$rest['stopLoss']."\n"; }
		if (isset($rest['takeProfit'])) { print "TP=".$rest['takeProfit']."\n"; }
		print "==================\n";

		if ($this->system == "Backtest") {
			return $this->acctObj->sell_market($units, $pair, $rest);
		} else {
			$r = $this->acctObj->sell_market($units, $pair, $rest);
			usleep($this->usleepTime);
			return $r;
		}
		
	}



	///////////////////////////////////////////////////
	// return trade info for a specific trade ID
	///////////////////////////////////////////////////
	public function trade($tradeID) {
		if ($this->system == "Backtest") {
			return $this->acctObj->trade($tradeID);
		} else {
			$r = $this->acctObj->trade($tradeID);
			usleep($this->usleepTime);
			return $r;
		}
	}



	///////////////////////////////////////////////////
	// return all trades for a single pair
	///////////////////////////////////////////////////
	public function trade_pair($pair, $number=50)
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->trade_pair($pair, $number);
		} else {
			$r = $this->acctObj->trade_pair($pair, $number);
			usleep($this->usleepTime);
			return $r;
		}
		
	}
	
	
	
	
	///////////////////////////////////////////////////
	// get current tick time
	///////////////////////////////////////////////////
	public function getTickTime()
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->getTickTime();
		} else {
			return time();
		}
		
	}




	///////////////////////////////////////////////////
	// trade set SL
	///////////////////////////////////////////////////
	public function trade_set_stop($id, $pair, $SL)
	{
		$SL = $this->forex_round($pair, $SL);

		print "\n";
		print "==================\n";
		print "FxClass-trade_set_stop\n";
		print "id=$id\n";
		print "pair=$pair\n";
		print "new SL=$SL\n";
		print "==================\n";

		if ($this->system == "Backtest") {
			return $this->acctObj->trade_set_stop($id, $SL);
		} else {
			$r = $this->acctObj->trade_set_stop($id, $SL);
			usleep($this->usleepTime);
			return $r;
		}
	}
	
	
	
	///////////////////////////////////////////////////
	// trade set TP
	///////////////////////////////////////////////////
	public function trade_set_tp($id, $pair, $TP)
	{
		$TP = $this->forex_round($pair, $TP);

		print "\n";
		print "==================\n";
		print "FxClass-trade_set_tp\n";
		print "id=$id\n";
		print "pair=$pair\n";
		print "new TP=$TP\n";
		print "==================\n";


		if ($this->system == "Backtest") {
			return $this->acctObj->trade_set_tp($id, $TP);
		} else {
			$r = $this->acctObj->trade_set_tp($id, $TP);
			usleep($this->usleepTime);
			return $r;
		}
	}

	
	
	///////////////////////////////////////////////////
	// close a trade
	///////////////////////////////////////////////////
	public function trade_close($tradeID)
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->trade_close($tradeID);
		} else {
			$r = $this->acctObj->trade_close($tradeID);
			usleep($this->usleepTime);
			return $r;
		}
	}




	///////////////////////////////////////////////////
	// backtest: start risk result file
	///////////////////////////////////////////////////
	public function btRiskResultFileStart()
	{
		$this->riskResultContent  = "\n\n\n\n===========================================================================\n\n\n";
		$this->riskResultContent .= "SETTINGS\n";
		$this->riskResultContent .= "leverage = ".$this->btLeverage."\n\n";
		$this->riskResultContent .= print_r($this->settings, true);
		$this->riskResultContent .= "\n\n";
		$this->riskResultContent .= "START TIME: ".$this->getTickTime()."\t".date("r", $this->getTickTime())."\n";
	
	}

	
	
	///////////////////////////////////////////////////
	// backtest: end risk result file
	///////////////////////////////////////////////////
	public function btRiskResultFileEnd()
	{
		$acctResult  = "";
		
		$acctResult .= "HOUR PERFORMANCE\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getHourPerformance(), true);
		
		$acctResult .= "DAY PERFORMANCE\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getDayPerformance(), true);
		
		$acctResult .= "MONTH PERFORMANCE\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getMonthPerformance(), true);
		
		$acctResult .= "YEAR PERFORMANCE\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getYearPerformance(), true);
		
		$acctResult .= "PAIR PERFORMANCE\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getPairPerformance(), true);
		
		$acctResult .= "ACCOUNT INFO\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->accountInfo(), true);
		
		$acctResult .= "WRAP-UP VARIABLES\n";
		$acctResult .= "================\n";
		$acctResult .= print_r($this->acctObj->getWrapupVariables(), true);
		

		$this->riskResultContent .= "END TIME: ".$this->getTickTime()."\t".date("r", $this->getTickTime())."\n";
		$this->riskResultContent .= "\nACCOUNT\n";
		$this->riskResultContent .= "==============\n";
		$this->riskResultContent .= $acctResult;

		file_put_contents($this->riskResultFilename, $this->riskResultContent, FILE_APPEND);
	}
	
	
	
	
	/////////////////////////////////////////////////////////////////
	// round a forex price to the required number of decimal points
	/////////////////////////////////////////////////////////////////
	public function forex_round($pair, $amount)
	{
		if (strstr($pair, "JPY") || strstr($pair, "THB")) {
			return round($amount, 3);
		} else {
			return round($amount, 5);
		}

		return $amount;
	}
	
	
	
	/////////////////////////////////////////////////////////////////
	// Calculate units based on accepted loss per trade
	// pairToTrade - the pair to trade
	// acceptedLossPerTrade - accepted loss per trade (percent ie .02 for 2%)
	// nav - current nav of the account
	// tradePrice - open price of the trade
	// closingPrice - closing price of the trade
	// side - the side where the trade closes...
	//                             if trade is long, side=sell because to close a long the units are sold back
	//                             if trade is short, side=buy because to close a long the units are bought back
	/////////////////////////////////////////////////////////////////
	public function calculateUnits($pairToTrade, $acceptedLossPerTrade, $nav, $tradePrice, $closingPrice, $side)
	{
		$desiredProfit = $nav * $acceptedLossPerTrade;

		$a = explode("_", $pairToTrade);
		$base = $a[0];
		$quote = $a[1];

		if ($side == "sell") {
			$profit = $tradePrice - $closingPrice;  // profit per unit
		} else {
			$profit = $closingPrice - $tradePrice;  // profit per unit
		}

		// quote side (RIGHT) is USD
		if ($quote == "USD") {
			$profitUSDPerUnit = $profit;  // profit USD per unit
		}
		
		// base side (LEFT) is USD
		if ($base == "USD") {
			$closingPriceInverse = 1/$closingPrice;
			$profitUSDPerUnit = $profit * $closingPriceInverse;  // profit USD per unit
		}

		$calcUnits = intval($desiredProfit / $profitUSDPerUnit);
		
		print "calculated units\n";
		print "$pairToTrade\n";
		print "acceptedLossPerTrade=$acceptedLossPerTrade\n";
		print "nav=$nav\n";
		print "tradePrice=$tradePrice\n";
		print "closingPrice=$closingPrice\n";
		print "side=$side\n";
		print "calculated units=$calcUnits\n\n";
		
		return $calcUnits;
	}





	/////////////////////////////////////////////////////////////////
	// Calculate hedge units based on accepted loss per trade so that if
	// the original trade hits SL, the hedge trade will cancel out losses and produce a net break even
	//
	// pairToTrade - the pair to trade
	// originalTradeUnits - accepted loss per trade (dollar amount)
	// origTradePrice - the original trade's price
	// hedgeTradePrice - the hedge's trade price (current quote bid or ask)
	// closingPrice - the closing price (where we want to hit TP on the hedge trade)
	// hedgeSide - the hedge trade side (buy or sell)
	/////////////////////////////////////////////////////////////////
	public function calculateHedgeUnits($pairToTrade, $originalTradeUnits, $origTradePrice, $hedgeTradePrice, $closingPrice, $hedgeSide)
	{
		$a = explode("_", $pairToTrade);
		$base = $a[0];
		$quote = $a[1];

		print "calculateHedgeUnits\n";
		print "$pairToTrade\n";
		print "originalTradeUnits=$originalTradeUnits\n";
		print "origTradePrice=$origTradePrice\n";
		print "hedgeTradePrice=$hedgeTradePrice\n";
		print "closingPrice=$closingPrice\n";
		print "hedgeSide=$hedgeSide\n";



		// calculate the original trade's stop loss (total in USD)
		if ($hedgeSide == "sell") {
			$origLoss = $origTradePrice - $closingPrice;  // profit per unit
		} else {
			$origLoss = $closingPrice - $origTradePrice;  // profit per unit
		}
		print "origLoss (pips)=$origLoss\n";
		

		// quote side (RIGHT) is USD
		if ($quote == "USD") {
			$origLossUSDPerUnit = $origLoss;  // profit USD per unit
		}
		
		// base side (LEFT) is USD
		if ($base == "USD") {
			$closingPriceInverse = 1/$closingPrice;
			$origLossUSDPerUnit = $origLoss * $closingPriceInverse;  // profit USD per unit
		}
		print "origLossUSDPerUnit=$origLossUSDPerUnit\n";


		$origAcceptedLossPerTradeDollars = $origLossUSDPerUnit * $originalTradeUnits;
		print "origAcceptedLossPerTradeDollars=$origAcceptedLossPerTradeDollars\n";



		// Now that we know the original trade's accepted loss (in USD)
		// how many units do we need to get that same amount in profit 
		// based on the current quote price and the original trade's stop loss
		if ($hedgeSide == "sell") {
			$hedgeProfit = $hedgeTradePrice - $closingPrice;  // profit per unit
		} else {
			$hedgeProfit = $closingPrice - $hedgeTradePrice;  // profit per unit
		}
		print "hedgeProfit (pips)=$hedgeProfit\n";

		// quote side (RIGHT) is USD
		if ($quote == "USD") {
			$hedgeProfitUSDPerUnit = $hedgeProfit;  // profit USD per unit
		}
		
		// base side (LEFT) is USD
		if ($base == "USD") {
			$closingPriceInverse = 1/$closingPrice;
			$hedgeProfitUSDPerUnit = $hedgeProfit * $closingPriceInverse;  // profit USD per unit
		}
		print "hedgeProfitUSDPerUnit=$hedgeProfitUSDPerUnit\n";


		$calcUnits = intval($origAcceptedLossPerTradeDollars / $hedgeProfitUSDPerUnit);
		print "calculated hedge units=$calcUnits\n\n";

		
		return $calcUnits;
	}	// END: calculateHedgeUnits






	/////////////////
	// backtest-determine if month has changed
	/////////////////
	public function monthChange()
	{
		if ($this->system == "Backtest") {
			return $this->acctObj->monthChange();
		} else {
			return false;
		}
	}


	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// backtest-deposit funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function deposit($amount)
	{
		if ($amount < 0) {
			print "Fx.class deposit amount < 0.  Not allowed!";
			exit;
		}
		
		if ($this->system == "Backtest") {
			print "\n";
			print "==================\n";
			print "FxClass-deposit\n";
			print "amount=$amount\n";
			print "==================\n";

			return $this->acctObj->deposit($amount);
		}
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// backtest-transfer in funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function transferIn($amount)
	{
		if ($amount < 0) {
			print "Fx.class transfer amount < 0.  Not allowed!";
			exit;
		}

		if ($this->system == "Backtest") {
			print "\n";
			print "==================\n";
			print "FxClass-transferIn\n";
			print "amount=$amount\n";
			print "==================\n";

			return $this->acctObj->transferIn($amount);
		}
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// backtest-withdrawl funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function withdrawl($amount)
	{
		if ($amount < 0) {
			print "Fx.class withdrawl amount < 0.  Not allowed!";
			exit;
		}

		if ($this->system == "Backtest") {
			print "\n";
			print "==================\n";
			print "FxClass-withdrawl\n";
			print "amount=$amount\n";
			print "==================\n";

			return $this->acctObj->withdrawl($amount);
		}
	}
	
	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// backtest-withdrawl funds
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function transferOut($amount)
	{
		if ($amount < 0) {
			print "Fx.class transferOut amount < 0.  Not allowed!";
			exit;
		}

		if ($this->system == "Backtest") {
			print "\n";
			print "==================\n";
			print "FxClass-transferOut\n";
			print "amount=$amount\n";
			print "==================\n";

			return $this->acctObj->transferOut($amount);
		}
	}

	
		
}	 // END: Fx class



