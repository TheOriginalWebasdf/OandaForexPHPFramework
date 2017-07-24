<?php
require_once __DIR__."/../../Fx.class.php";

/*
This method is a day trade strategy for eurusd
Starts at 7:00am central time with a sample of the last 7 hrs (covering the london session)

- Analyze existing trades
At 3pm central time close all open trades


- Determine new trade (at 7am only)
if no trades open
	Get min and max of sample (7 hrs)
	
	If more positive bars than negative bars, go long (TP=current quote + (max - min); SL=current quote - ((max - min) * .5))
	Else go short (TP=current quote - (max - min); SL=current quote + ((max - min) * .5))

*/





class BigBenH extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"USD_CHF",	// [LRrSquared] => 0.97101321057613
		"GBP_USD",	// [LRrSquared] => 0.96346561545654
		"USD_CAD",	// [LRrSquared] => 0.96343819188801
		"USD_JPY",	// [LRrSquared] => 0.95109175221574
		"NZD_USD",	// [LRrSquared] => 0.92565644270773
		"EUR_USD",	// [LRrSquared] => 0.91741651072189
		"AUD_USD",	// [LRrSquared] => 0.83050810962551
		);

		$this->settings = array(

			"USD_CHF" => array(
				"acceptedLossPerTrade" => .02,  // percentage of NAV acceptable loss
				"tpLevelMax" => 1.75,
				"slLevel" => 1.7,
				"openRatioMin" => .75,
				"openRatioMax" => .85,
				"openHour" => 7,
				"closeHour" => 10,
				"lookbackCandles" => 6,
			),

			"GBP_USD" => array(
				"acceptedLossPerTrade" => .02,
				"tpLevelMax" => 2.2,
				"slLevel" => 2,
				"openRatioMin" => .80,
				"openRatioMax" => .85,
				"openHour" => 5,
				"closeHour" => 10,
				"lookbackCandles" => 6,
			),

			"USD_CAD" => array(
				"acceptedLossPerTrade" => .02,  // percentage of NAV acceptable loss
				"tpLevelMax" => 0.75,
				"slLevel" => 0.35,
				"openRatioMin" => .70,
				"openRatioMax" => .85,
				"openHour" => 17,
				"closeHour" => 25,
				"lookbackCandles" => 48,
			),

			"USD_JPY" => array(
				"acceptedLossPerTrade" => .02,
				"tpLevelMax" => 6.00,
				"slLevel" => 1.00,
				"openRatioMin" => .70,
				"openRatioMax" => .90,
				"openHour" => 2,
				"closeHour" => 11,
				"lookbackCandles" => 12,
			),

			"NZD_USD" => array(
				"acceptedLossPerTrade" => .02,  // percentage of NAV acceptable loss
				"tpLevelMax" => 6.00,
				"slLevel" => 1.25,
				"openRatioMin" => .80,
				"openRatioMax" => .90,
				"openHour" => 8,
				"closeHour" => 11,
				"lookbackCandles" => 8,
			),

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .02,  // percentage of NAV acceptable loss
				"tpLevelMax" => 10.00,
				"slLevel" => 1.50,
				"openRatioMin" => .75,
				"openRatioMax" => .85,
				"openHour" => 6,
				"closeHour" => 10,
				"lookbackCandles" => 6,
			),

			"AUD_USD" => array(
				"acceptedLossPerTrade" => .015,  // percentage of NAV acceptable loss
				"tpLevelMax" => 9.00,
				"slLevel" => 3.00,
				"openRatioMin" => .80,
				"openRatioMax" => .85,
				"openHour" => 6,
				"closeHour" => 22,
				"lookbackCandles" => 6,
			),


		);	// END: settings array


		$this->granularity = "H1";




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


//		if ($this->system == "Backtest") {
//			if ($this->monthChange()) {
//				$this->deposit(10);
//			}
//		}



		foreach ($this->currencyBasket as $idx=>$pairToTrade) {
			
			// don't trade USDCHF before 1/10/2015
			if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
				continue;
			}



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;
			
			// if hour is closeHour, close all trades
			if (date("H", $this->getTickTime()) == $this->settings[$pairToTrade]['closeHour'] && count($trades->trades) > 0) {

				print "=== $pairToTrade Trade Mgmt ===\n";
				$didSomething = true;

				print "Close $pairToTrade\n";

				foreach ($trades->trades as $t) {
					print "=== CLOSE $pairToTrade (".$t->id.") ===\n";
					$this->trade_close($t->id);
					$refreshTrades = true;
				}
				
			}

			

			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}

			

			// if it's time to trade, determine how to trade
			if (date("H", $this->getTickTime()) == $this->settings[$pairToTrade]['openHour']) {

				$didSomething = true;

				print "=== $pairToTrade Get Candles ===\n";

				// get current candles
				$oRest = array("count"=>$this->settings[$pairToTrade]['lookbackCandles'] + 24, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";

				$btNumCandles = 500;

				$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, $btNumCandles);

				array_pop($candleArr[$pairToTrade]->candles);
				$candle = end($candleArr[$pairToTrade]->candles);
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;



				// get current quote
				$q = $this->price($pairToTrade); print_r($q);

				echo 'Price of ' . $pairToTrade . ' is: ' .$q->bid . ' => ' . $q->ask . "\n";
				$quote[$pairToTrade]['bid'] = $q->bid;
				$quote[$pairToTrade]['ask'] = $q->ask;
				$quote[$pairToTrade]['mid'] = ($q->bid + $q->ask) / 2;
				$quote[$pairToTrade]['spread'] = $q->ask - $q->bid;


				// get long/short bias depening on number of pips up or down
				$downDistance = 0;
				$upDistance = 0;
				$candleCloseBidArr = array();
				$candleCloseAskArr = array();
				
				$loop=$this->settings[$pairToTrade]['lookbackCandles'];
				
				print "determine long/short bias\n";
				print "=========================\n";
				print "candleArrLastIdx=$candleArrLastIdx\n";
				print "loop=$loop\n";

				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['lookbackCandles']; $i--) {
					$distance = ($candleArr[$pairToTrade]->candles[$i]->closeBid - $candleArr[$pairToTrade]->candles[$i]->openBid) * $loop;

					print "i=$i\n";
					print "loop=$loop\n";
					print "distance=$distance\n";
					print "current loop candle\n";
					print_r($candleArr[$pairToTrade]->candles[$i]);
					print "\n\n";
					
					$candleCloseBidArr[] = $candleArr[$pairToTrade]->candles[$i]->closeBid;
					$candleCloseAskArr[] = $candleArr[$pairToTrade]->candles[$i]->closeAsk;
					
					if ($distance < 0) { $downDistance += $distance; }
					else if ($distance > 0) { $upDistance += $distance; }
					$loop--;
				}

				print "candleCloseBidArr\n";
				print_r($candleCloseBidArr);

				print "candleCloseAskArr\n";
				print_r($candleCloseAskArr);


				$downDistance = abs($downDistance);
				$totalDistance = $upDistance + $downDistance;
				
				if ($totalDistance > 0) {
					$upRatio = $upDistance / $totalDistance;
					$downRatio = $downDistance / $totalDistance;
				} else {
					$upRatio = 0;
					$downRatio = 0;
				}

				$sampleLow = min($candleCloseBidArr);
				$sampleHigh = max($candleCloseAskArr);
				
				if ($sampleLow > 0 && $sampleHigh > 0) {
					$sampleDiff = $sampleHigh - $sampleLow;
				} else {
					$sampleDiff = 0;
				}

				print "upDistance = $upDistance\n";
				print "downDistance = $downDistance\n";
				print "upRatio = $upRatio\n";
				print "downRatio = $downRatio\n";
				print "sampleLow = $sampleLow\n";
				print "sampleHigh = $sampleHigh\n";
				print "sampleDiff = $sampleDiff\n";


				if ($downRatio > $this->settings[$pairToTrade]['openRatioMin'] && $downRatio < $this->settings[$pairToTrade]['openRatioMax']) {
					$bias = "down";
				} else if ($upRatio > $this->settings[$pairToTrade]['openRatioMin'] && $upRatio < $this->settings[$pairToTrade]['openRatioMax']) {
					$bias = "up";
				} else {
					$bias = "none";
				}






				print "================================ $pairToTrade New Trades ==================================\n";
				print "count trades=".count($trades->trades)."\n";
				print_r($trades->trades);

				if (count($trades->trades) == 0) {
					
					if ($bias == "down") {

						// short
						$tpLevelPerc = ($downRatio-$this->settings[$pairToTrade]['openRatioMin'])/($this->settings[$pairToTrade]['openRatioMax']-$this->settings[$pairToTrade]['openRatioMin']);
						$tpLevel = $this->settings[$pairToTrade]['tpLevelMax'] - (($this->settings[$pairToTrade]['tpLevelMax']-$this->settings[$pairToTrade]['slLevel']) * $tpLevelPerc);

						$TP = $this->forex_round($pairToTrade, $quote[$pairToTrade]['ask'] - $sampleDiff * $tpLevel);
						$SL = $this->forex_round($pairToTrade, $quote[$pairToTrade]['ask'] + $sampleDiff * $this->settings[$pairToTrade]['slLevel']);
						
						print "tpLevel=$tpLevel\n";
						print "TP=$TP\n";
						print "SL=$SL\n";
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $TP, "stopLoss" => $SL);
						$this->sell_market($calcUnits, $pairToTrade, $rest);

					} else if ($bias == "up") {

						// long
						$tpLevelPerc = ($upRatio-$this->settings[$pairToTrade]['openRatioMin'])/($this->settings[$pairToTrade]['openRatioMax']-$this->settings[$pairToTrade]['openRatioMin']);
						$tpLevel = $this->settings[$pairToTrade]['tpLevelMax'] - (($this->settings[$pairToTrade]['tpLevelMax']-$this->settings[$pairToTrade]['slLevel']) * $tpLevelPerc);

						$TP = $this->forex_round($pairToTrade, $quote[$pairToTrade]['bid'] + $sampleDiff * $tpLevel);
						$SL = $this->forex_round($pairToTrade, $quote[$pairToTrade]['bid'] - $sampleDiff * $this->settings[$pairToTrade]['slLevel']);

						print "tpLevel=$tpLevel\n";
						print "TP=$TP\n";
						print "SL=$SL\n";

						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['bid'], $SL, "sell");

						$rest = array("takeProfit" => $TP, "stopLoss" => $SL);
						$this->buy_market($calcUnits, $pairToTrade, $rest);
					
					}
					
					
				}  // end: new trade

			}  // end: hour matches settings openHour

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()
	
	
	
}	// END: class

