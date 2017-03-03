<?php
require_once __DIR__."/../Fx.class.php";

/*
 * DERIVED FROM bigBen-h.php
 * 
This method is a day trade strategy for eurusd
Starts at 7:00am central time with a sample of the last 7 hrs (covering the london session)

- Analyze existing trades
At 3pm central time close all open trades


- Determine new trade (at 7am only)
if no trades open
	Get min and max of sample (7 hrs)
	
	If more positive bars than negative bars, go long (TP=current quote + (max - min); SL=current quote - ((max - min) * .5))
	Else go short (TP=current quote - (max - min); SL=current quote + ((max - min) * .5))

- DEPOSIT $10 per month into the account!
*/



/*
START TIME: 1136095200  Sun, 01 Jan 2006 00:00:00 -0600
END TIME: 1483484400    Tue, 03 Jan 2017 17:00:00 -0600

ACCOUNT
==============
stdClass Object
(
    [Tue] => 2773.9160345845
    [Thu] => 3677.2266811289
    [Wed] => 2499.2454408769
    [Mon] => 2091.793059896
    [Fri] => 3110.7114351974
    [Sun] => 107.91185781
    [Sat] => -2.9511506898962
)
stdClass Object
(
    [Jan] => 69.906320035013
    [Feb] => 682.08354222216
    [Mar] => 1486.6410436735
    [Apr] => -586.74487428081
    [May] => -35.598665623754
    [Jun] => 581.5782233926
    [Jul] => 3976.1213412158
    [Aug] => 1944.28021253
    [Sep] => 2302.1882552415
    [Oct] => 1559.3740649914
    [Nov] => 276.78723694804
    [Dec] => 2001.2366584583
)
stdClass Object
(
    [USD_JPY] => 1751.333350522
    [NZD_USD] => 1198.43513
    [EUR_USD] => 3419.07001
    [GBP_USD] => 1602.57395
    [USD_CAD] => 3164.97501149
    [AUD_USD] => 188.4554
    [USD_CHF] => 2933.0105067917
)
stdClass Object
(
    [accountId] => 111111
    [accountName] => myAccount
    [leverage] => 30
    [balance] => 15677.853358804
    [perfectBalance] => 95946.400942066
    [unrealizedPl] => 0
    [realizedPl] => 14257.853358804
    [marginUsed] => 0
    [marginAvail] => 15677.853358804
    [openTrades] => 0
    [openOrders] => 0
    [unrealizedPlPerc] => 0
    [NAV] => 15677.853358804
    [longTradeCount] => 1278
    [shortTradeCount] => 1415
    [profitableTradeCount] => 1328
    [unprofitableTradeCount] => 1365
    [profitableTradeCountPerc] => 0.49313033791311
    [unprofitableTradeCountPerc] => 0.50686966208689
    [TPcount] => 122
    [SLcount] => 417
    [totalTradeTime] => 111956400
    [avgTimePerTradeSecs] => 41573.11548459
    [avgTimePerTradeHours] => 11.548087634608
    [avgTimePerTradeDays] => 0.48117031810868
    [deposits] => 1320
    [withdrawls] => 0
    [transferIn] => 0
    [transferOut] => 0
)
stdClass Object
(
    [LRslope] => 0.18734125017207
    [LRintercept] => -327.19149756666
    [LRrSquared] => 0.98356508442781
    [maxDrawdown] => 1860.1430075724
    [maxDrawdownPerc] => 0.11213985983674
    [minBalance] => 95.717403099618
    [maxBalance] => 17176.481057895
)

*/




class BigBen extends Fx {
	
	
	
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
				"acceptedLossPerTrade" => .03,  // percentage of NAV acceptable loss
				"tpLevelMax" => 1.75,
				"slLevel" => 1.7,
				"openRatioMin" => .75,
				"openRatioMax" => .85,
				"openHour" => 7,
				"closeHour" => 10,
				"lookbackCandles" => 6,
			),

			"GBP_USD" => array(
				"acceptedLossPerTrade" => .028,
				"tpLevelMax" => 2.2,
				"slLevel" => 2,
				"openRatioMin" => .80,
				"openRatioMax" => .85,
				"openHour" => 5,
				"closeHour" => 10,
				"lookbackCandles" => 6,
			),

			"USD_CAD" => array(
				"acceptedLossPerTrade" => .026,  // percentage of NAV acceptable loss
				"tpLevelMax" => 0.75,
				"slLevel" => 0.35,
				"openRatioMin" => .70,
				"openRatioMax" => .85,
				"openHour" => 17,
				"closeHour" => 25,
				"lookbackCandles" => 48,
			),

			"USD_JPY" => array(
				"acceptedLossPerTrade" => .024,
				"tpLevelMax" => 6.00,
				"slLevel" => 1.00,
				"openRatioMin" => .70,
				"openRatioMax" => .90,
				"openHour" => 2,
				"closeHour" => 11,
				"lookbackCandles" => 12,
			),

			"NZD_USD" => array(
				"acceptedLossPerTrade" => .022,  // percentage of NAV acceptable loss
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
				"acceptedLossPerTrade" => .02,  // percentage of NAV acceptable loss
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

				$btNumCandles = 300;

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
				
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['lookbackCandles']; $i--) {
					$distance = ($candleArr[$pairToTrade]->candles[$i]->closeBid - $candleArr[$pairToTrade]->candles[$i]->openBid) * $loop;
					$candleCloseBidArr[] = $candleArr[$pairToTrade]->candles[$i]->closeBid;
					$candleCloseAskArr[] = $candleArr[$pairToTrade]->candles[$i]->closeAsk;
					
					if ($distance < 0) { $downDistance += $distance; }
					else if ($distance > 0) { $upDistance += $distance; }
					$loop--;
				}

				print_r($candleCloseBidArr);
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
					
					} else {
						print "\n\nNo trades taken on $pairToTrade\n";
						print_r($this->settings[$pairToTrade]);
						
					}
					
					
				}  // end: new trade

			}  // end: hour matches settings openHour

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()
	
	
	
}	// END: class

