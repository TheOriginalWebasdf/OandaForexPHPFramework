<?php
require_once __DIR__."/../../Fx.class.php";
require_once __DIR__."/../../Statistics.class.php";

/*
				"acceptedLossPerTrade" => .02,		// how much % loss of balance accepted per trade
				"trendLookbackCandles" => 24*2,		// how many candles to look back for trend detection
				"trendRatioThreshold" => .65,		// after up/down ratio calculated, if it exceeds this threshold, it is determined to be a trend
				"tradeLookbackCandles" => 16,		// how many candles to look back for trade determination
				"windowRatioThresholdSell" => .85,	// when closing price goes above this ratio of the trade lookback window, sell
				"windowRatioThresholdBuy" => .15,	// when closing price goes below this ratio of the trade lookback window, buy
				"tpMultiplier" => 2.75,				// multiply the trade lookback window range to determine TP price
				"slMultiplier" => 2,				// multiply the trade lookback window range to determine SL price
				"hedgeThreshold" => .9,				// when to open hedge (if applicable)

*/





class Supernova extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"EUR_USD",
		//"AUD_USD",
		//"NZD_USD",
		//"USD_JPY",
		//"USD_CAD",
		//"GBP_USD",
		);


		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .005,
				"tradeLookbackCandles" => 4,
				"tradeTimeSecs" => 60 * 60 * 1 - 1,
				"slMultiplier" => .8,
				"tpMultiplier" => 2,
				"minSL" => .0020,
			),


		);	// END: settings array


		$this->granularity = "M10";
		$this->statObj = new Statistics();



		// configure the account
		// account setup
		if ($this->system == "Backtest") {

			$this->btStartTickTime = $configArr['btStartTickTime'];

			$this->btEndTickTime = $configArr['btEndTickTime'];

			$this->btLeverage = $configArr['btLeverage'];
			$this->riskResultFilename = $configArr['riskResultFilename'];

			$this->btAccountId = $configArr['btAccountId'];
			$this->btAccountName = $configArr['btAccountName'];
			$this->btOpeningBalance = $configArr['btOpeningBalance'];
			$this->btLogFile = $configArr['btLogFile'];
			$this->btStatsFile = $configArr['btStatsFile'];
			$this->btCorrelationFile = $configArr['btCorrelationFile'];
			
			$this->btOpeningBalanceHedge = $configArr['btOpeningBalanceHedge'];
			$this->btHedgeBalancePercentageOfTotal = $configArr['btHedgeBalancePercentageOfTotal'];

		} else {

			$this->oandaApiKey = $configArr['oandaApiKey'];
			$this->oandaAccountId = $configArr['oandaAccountId'];

		}

		$this->configureAccount();


		
	}	// END: __construct()
	
	
	
	
	
	

	

	// Execute strategy
	function execute()
	{

		// get current NAV
		$acctInfo = $this->accountInfo();
		$NAV = $acctInfo->NAV;

		print "================================ ".date("c", $this->getTickTime())." ===============================\n";
		print "NAV=".$acctInfo->NAV."\n";



		foreach ($this->currencyBasket as $idx=>$pairToTrade) {
			
			// don't trade USDCHF before 1/10/2015
			if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
				continue;
			}



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;

			print "\n\n TRADE MAINTENANCE \n\n";
			// check if trade timer is expired. (tradeTimeSecs)			
			// if (date("H", $this->getTickTime()) == $this->settings[$pairToTrade]['closeHour'] && count($trades->trades) > 0) {
			if (count($trades->trades) > 0) {

				print "=== $pairToTrade Trade Mgmt ===\n";

				foreach ($trades->trades as $t) {
					
					if (($this->getTickTime() - $t->time) >= $this->settings[$pairToTrade]['tradeTimeSecs']) {
						print "=== CLOSE $pairToTrade (".$t->id.") ===\n";
						$this->trade_close($t->id);
						$didSomething = true;
						$refreshTrades = true;
					}
					
				}
				
			}


			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}





			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0) {

				$didSomething = true;

				print "=== $pairToTrade Get Candles ===\n";

				// get current candles
				$oRest = array("count"=>$this->settings[$pairToTrade]['tradeLookbackCandles'] + 24, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";


				if ($this->system == "Backtest") {
					$daysLookback = 0;

					do {
						$btNumCandles = $this->settings[$pairToTrade]['tradeLookbackCandles'] + ($daysLookback * 24 * 6);
						$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, $btNumCandles);
						print "btNumCandles=$btNumCandles\n";
						print "candle count=".count($candleArr[$pairToTrade]->candles)."\n";
						$daysLookback++;
					} while (count($candleArr[$pairToTrade]->candles) <= ($this->settings[$pairToTrade]['tradeLookbackCandles']+1));

				} else {
					$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, 0);
				}


				// pop off last candle in the candleArr
				// in backtest, it will affect a lookback bias
				// in live, it will be incomplete.
				array_pop($candleArr[$pairToTrade]->candles);
				
				// get last candle and the last candle array index
				$candle = end($candleArr[$pairToTrade]->candles);
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
				print "candleArrLAstIdx=$candleArrLastIdx";


				// get current quote
				$q = $this->price($pairToTrade); print_r($q);

				echo 'Price of ' . $pairToTrade . ' is: ' .$q->bid . ' => ' . $q->ask . "\n";
				$quote[$pairToTrade]['bid'] = $q->bid;
				$quote[$pairToTrade]['ask'] = $q->ask;
				$quote[$pairToTrade]['mid'] = ($q->bid + $q->ask) / 2;
				$quote[$pairToTrade]['spread'] = $q->ask - $q->bid;





				print "================================ $pairToTrade New Trades ==================================\n";

				// analyze the "trade window"
				$tradeMin = 99999999;
				$tradeMax = 0;
				$candleSizeArr = array();

				$downCount = 0;
				$upCount = 0;

				// start analysis back one more candle.
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {

					$bodySize =  $candleArr[$pairToTrade]->candles[$i]->openAsk - $candleArr[$pairToTrade]->candles[$i]->closeAsk;
					if ($bodySize > 0) { $upCount++; }
					else if ($bodySize < 0) { $downCount++; }

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $tradeMin) {
						$tradeMin = $candleArr[$pairToTrade]->candles[$i]->lowBid;
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $tradeMax) {
						$tradeMax = $candleArr[$pairToTrade]->candles[$i]->highAsk;
					}

				}
				
				$tradeWindowRange = $tradeMax - $tradeMin;

				
				print "tradeMin = $tradeMin\n";
				print "tradeMax = $tradeMax\n";
				print "tradeWindowRange = $tradeWindowRange\n";

				print "upCount = $upCount\n";
				print "downCount = $downCount\n";


				
				

				
				if ($downCount == $this->settings[$pairToTrade]['tradeLookbackCandles']) {

					// short
					$SL = $candle->closeAsk + ($tradeWindowRange * $this->settings[$pairToTrade]['slMultiplier']);
					$TP = $candle->closeAsk - ($tradeWindowRange * $this->settings[$pairToTrade]['tpMultiplier']);
					
					if (abs($candle->closeAsk - $SL) > $this->settings[$pairToTrade]['minSL']) {
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->sell_market($calcUnits, $pairToTrade, $rest);

						print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";
						
					}

				} else if ($upCount == $this->settings[$pairToTrade]['tradeLookbackCandles']) {

					// long
					$SL = $candle->closeBid - ($tradeWindowRange * $this->settings[$pairToTrade]['slMultiplier']);
					$TP = $candle->closeBid + ($tradeWindowRange * $this->settings[$pairToTrade]['tpMultiplier']);

					if (abs($candle->closeBid - $SL) > $this->settings[$pairToTrade]['minSL']) {
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['bid'], $SL, "sell");
				
						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->buy_market($calcUnits, $pairToTrade, $rest);

						print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";
						
					}
							
				} else {
					
					print "\n\nNo trades taken on $pairToTrade\n";
					// print_r($this->settings[$pairToTrade]);
					
				}
					
					
			}  // end: check for new trade availability

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()




	
	
}	// END: class

