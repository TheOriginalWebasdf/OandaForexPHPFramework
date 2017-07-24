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

		$this->doHedge = false;

		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 6 * 8,
				"trendRatioThreshold" => .6,
				"tradeLookbackCandles" => 6 * 4,
				"windowRatioThresholdSell" => .5,
				"windowRatioThresholdBuy" => .5,
				"tpMultiplier" => 1.2,
				"slMultiplier" => 1,
				"minSL" => .0010,
				"hedgeThreshold" => .85,
			),


/*
			"USD_JPY" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*2,
				"trendRatioThreshold" => .65,
				"tradeLookbackCandles" => 16,
				"windowRatioThresholdSell" => .85,
				"windowRatioThresholdBuy" => .15,
				"tpMultiplier" => 2.75,
				"slMultiplier" => 2,
				"hedgeThreshold" => .9,
			),

			"NZD_USD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*1,
				"trendRatioThreshold" => .65,
				"tradeLookbackCandles" => 16,
				"windowRatioThresholdSell" => .9,
				"windowRatioThresholdBuy" => .1,
				"tpMultiplier" => 2.3,
				"slMultiplier" => 1.7,
				"hedgeThreshold" => .8,
			),

			"AUD_USD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*1,
				"trendRatioThreshold" => .7,
				"tradeLookbackCandles" => 16,
				"windowRatioThresholdSell" => .9,
				"windowRatioThresholdBuy" => .1,
				"tpMultiplier" => 2.3,
				"slMultiplier" => 1.7,
				"hedgeThreshold" => .8,
			),



			"USD_CAD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*2,
				"trendRatioThreshold" => .65,
				"tradeLookbackCandles" => 16,
				"windowRatioThresholdSell" => .8,
				"windowRatioThresholdBuy" => .2,
				"tpMultiplier" => 2.5,
				"slMultiplier" => .75,
				"hedgeThreshold" => .8,
			),


			"GBP_USD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*3,
				"trendRatioThreshold" => .65,
				"tradeLookbackCandles" => 16,
				"windowRatioThresholdSell" => .8,
				"windowRatioThresholdBuy" => .2,
				"tpMultiplier" => 3.2,
				"slMultiplier" => 1.1,
				"hedgeThreshold" => .8,
			),
*/



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

			$this->setupHedgeAccount($configArr);
			
		} else {

			$this->oandaApiKey = $configArr['oandaApiKey'];
			$this->oandaAccountId = $configArr['oandaAccountId'];

		}

		$this->configureAccount();


		
	}	// END: __construct()
	
	
	
	
	
	
	// Setup the hedge account
	function setupHedgeAccount($configArr)
	{

		$this->hedgeAcctObj = new Fx($this->system);
		$this->hedgeAcctObj->granularity = $this->granularity;

		if ($this->system == "Backtest") {

			$this->hedgeAcctObj->btStartTickTime = $this->btStartTickTime;
			$this->hedgeAcctObj->riskResultFilename = $this->riskResultFilename.".hedge";

			$this->hedgeAcctObj->btAccountId = 999999;
			$this->hedgeAcctObj->btAccountName = "marcoPolo-hedge";
			$this->hedgeAcctObj->btOpeningBalance = $this->btOpeningBalanceHedge;
			$this->hedgeAcctObj->btLogFile = $this->btLogFile.".hedge";
			$this->hedgeAcctObj->btStatsFile = $this->btStatsFile.".hedge.csv";
			$this->hedgeAcctObj->btCorrelationFile = $this->btCorrelationFile.".hedge.csv";

			if ($this->btEndTickTime != NULL) {
				$this->hedgeAcctObj->endTickTime = $this->btEndTickTime;
			}
			
			if ($this->btLeverage != NULL) {
				$this->hedgeAcctObj->btLeverage = $this->btLeverage;
			}
			
		} else {

			$this->hedgeAcctObj->oandaApiKey = $configArr['oandaApiKey'];
			$this->hedgeAcctObj->oandaAccountId = $configArr['oandaAccountIdHedge'];

		}

		$this->hedgeAcctObj->configureAccount();
		
		

	}  // END:  SET UP HEDGE ACCOUNT

	

	// Execute strategy
	function execute()
	{

		// get current NAV
		$acctInfo = $this->accountInfo();
		$NAV = $acctInfo->NAV;

		print "================================ ".date("c", $this->getTickTime())." ===============================\n";
		print "NAV=".$acctInfo->NAV."\n";


		//if ($this->system == "Backtest") {
			//if ($this->monthChange()) {
				//$this->deposit(10);
			//}
		//}



		foreach ($this->currencyBasket as $idx=>$pairToTrade) {
			
			// don't trade USDCHF before 1/10/2015
			if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
				continue;
			}



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;
			


			if ($this->doHedge === true) {


				$hedgeTrades = $this->hedgeAcctObj->trade_pair($pairToTrade, 500);


				// if trade is in positive territory, check to see if we should hedge
				if (count($trades->trades) > 0 && count($hedgeTrades->trades) == 0) {

					print "=== $pairToTrade Trade Mgmt ===\n";
					$didSomething = true;

					print "Main account trades\n";
					print "===================\n";
					print_r($trades->trades);

					print "Hedge account trades\n";
					print "===================\n";
					print_r($hedgeTrades->trades);


					foreach ($trades->trades as $t) {

						if ($t->takeProfit != "" && $t->stopLoss != "") {

							//$t->id;
							//$t->price;
							//$t->units;
							//$t->takeProfit;
							//$t->stopLoss;

							// get current quote
							$q = $this->price($pairToTrade);
			
							echo 'Price of ' . $pairToTrade . ' is: ' .$q->bid . ' => ' . $q->ask . "\n";
							$q->mid = ($q->bid + $q->ask) / 2;
							$spread = $q->ask - $q->bid;

							// how close are we from open to the take profit value?
							if ($t->side == "buy") {

								$distanceFromOpenToTP = $t->takeProfit - $t->price;
								$distanceFromQuoteToTP = $t->takeProfit - $q->mid;
								
								$tpPercentage = 1 - ($distanceFromQuoteToTP / $distanceFromOpenToTP);

								if ($tpPercentage > $this->settings[$pairToTrade]['hedgeThreshold']) {

									$TP = $t->stopLoss - $spread;
									$SL = $t->takeProfit + $spread;
									$rest = array("stopLoss" => $SL, "takeProfit" => $TP);
									$hedgeUnits = $this->calculateHedgeUnits($pairToTrade, $t->units, $t->price, $q->bid, $TP, "sell");
									$this->hedgeAcctObj->sell_market($hedgeUnits, $pairToTrade, $rest);
								}


							} else if ($t->side == "sell") {

								$distanceFromOpenToTP = $t->price - $t->takeProfit;
								$distanceFromQuoteToTP = $q->mid - $t->takeProfit;
								
								$tpPercentage = 1 - ($distanceFromQuoteToTP / $distanceFromOpenToTP);

								if ($tpPercentage > $this->settings[$pairToTrade]['hedgeThreshold']) {
									$TP = $t->stopLoss + $spread;
									$SL = $t->takeProfit - $spread;
									$rest = array("stopLoss" => $SL, "takeProfit" => $TP);
									$hedgeUnits = $this->calculateHedgeUnits($pairToTrade, $t->units, $t->price, $q->ask, $TP, "buy");
									$this->hedgeAcctObj->buy_market($hedgeUnits, $pairToTrade, $rest);

								}
								
							}	// END: BUY OR SELL SIDE CHECK


						}	// END: TP AND SL BOTH SET


					}	// END: LOOP THROUGH EXISTING TRADES FOR THIS PAIR ON THE MAIN ACCOUNT


					$refreshTrades = true;

													
				}	// END: TRADES IN MAIN ACCOUNT EXIST FOR THIS PAIR
				
				
				// hedge trade is open and no main trades exist
				else if (count($hedgeTrades->trades) > 0 && count($trades->trades) == 0) {
					
					// close trades on hedge acct
					foreach ($hedgeTrades->trades as $t) {
						$this->hedgeAcctObj->trade_close($t->id);
					}
					
				}
				

				// balance main account and hedge account once per month
				if ($this->monthChange()) {
					$acctInfoHedge = $this->hedgeAcctObj->accountInfo();

					$totalBalance = $acctInfo->balance + $acctInfoHedge->balance;
					$newMainBalance = $totalBalance - ($totalBalance * $this->btHedgeBalancePercentageOfTotal);

					$mainDepositAmount = $newMainBalance - $acctInfo->balance;
					if ($mainDepositAmount > 0) {
						$this->hedgeAcctObj->transferOut($mainDepositAmount);
						$this->transferIn($mainDepositAmount);
					} else if ($mainDepositAmount < 0) {
						$this->transferOut(abs($mainDepositAmount));
						$this->hedgeAcctObj->transferIn(abs($mainDepositAmount));
					}
					
					//$balanceEach = $totalBalance * .5;
					
					//if (round($acctInfo->balance, 1) > round($acctInfoHedge->balance, 1)) {
						//$amount = $acctInfo->balance - $balanceEach;
						//$this->transferOut($amount);
						//$this->hedgeAcctObj->transferIn($amount);
					//} else if (round($acctInfo->balance, 1) < round($acctInfoHedge->balance, 1)) {
						//$amount = $acctInfoHedge->balance - $balanceEach;
						//$this->hedgeAcctObj->transferOut($amount);
						//$this->transferIn($amount);
					//}
				}
			

			} 	// END: DO HEDGE
			
			
			




			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}





			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0) {

				$didSomething = true;

				print "=== $pairToTrade Get Candles ===\n";

				// get current candles
				$oRest = array("count"=>$this->settings[$pairToTrade]['trendLookbackCandles'] + 24, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";


				if ($this->system == "Backtest") {
					$daysLookback = 0;

					do {
						$btNumCandles = $this->settings[$pairToTrade]['trendLookbackCandles'] + ($daysLookback * 24 * 6);
						$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, $btNumCandles);
						print "btNumCandles=$btNumCandles\n";
						print "candle count=".count($candleArr[$pairToTrade]->candles)."\n";
						$daysLookback++;
					} while (count($candleArr[$pairToTrade]->candles) <= ($this->settings[$pairToTrade]['trendLookbackCandles']+1));

				} else {
					$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, 0);					
				}


				array_pop($candleArr[$pairToTrade]->candles);
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



				// determine mean candle size, min, max, closing ratios, long/short bias, etc
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
				$downDistance = 0;
				$upDistance = 0;
				$min = 99999999;
				$max = 0;
				$loop = 1;


				// detect trend w/in the "trend window"
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['trendLookbackCandles']; $i--) {

					print "i=$i\n";

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
						$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;

						print "i=$i\nlow bid=".$candleArr[$pairToTrade]->candles[$i]->lowBid."\ncurrent min=$min\n";
						print "candle\n";
						print "======\n";
						print_r($candleArr[$pairToTrade]->candles[$i]);
						if ($min == 0) { exit; }
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
						$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
					}

					$distance = ($candleArr[$pairToTrade]->candles[$i]->closeBid - $candleArr[$pairToTrade]->candles[$i]->openBid) * $loop;

					if ($distance < 0) { $downDistance += abs($distance); }
					else if ($distance > 0) { $upDistance += $distance; }
					$loop++;
				}

				$totalDistance = $upDistance + $downDistance;
				if ($totalDistance > 0) {
					$upRatio = $upDistance / $totalDistance;
					$downRatio = $downDistance / $totalDistance;
				}
				
				print "upDistance = $upDistance\n";
				print "downDistance = $downDistance\n";
				print "upRatio = $upRatio\n";
				print "downRatio = $downRatio\n";

				if ($downRatio > $this->settings[$pairToTrade]['trendRatioThreshold']) {
					$bias = "down";
				} else if ($upRatio > $this->settings[$pairToTrade]['trendRatioThreshold']) {
					$bias = "up";
				} else {
					$bias = "none";
				}

				print "trend bias=$bias\n";

				$trendWindowRange = $max - $min;
				print "trendWindowRange = $trendWindowRange\n";







				// determine where current quote is within the "trade window"
				$min = 99999999;
				$max = 0;
				$candleSizeArr = array();

				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {
					$candleSizeArr[] = $candleArr[$pairToTrade]->candles[$i]->highAsk - $candleArr[$pairToTrade]->candles[$i]->lowBid;

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
						$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
						$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
					}
				}
				

				$meanCandleSize = $this->statObj->mean($candleSizeArr);
				$lastCandleSize = $candle->highAsk - $candle->lowBid;
				$lastCandleBodySize = $candle->closeBid - $candle->openBid;
				$tradeWindowRange = $max - $min;

				$sizePercentile = $this->statObj->percentile($candleSizeArr, $lastCandleSize);
				if ($sizePercentile > .9) {
					print "sizePercentile > .9\nsizePercentile=$sizePercentile\n";
					print "pair=$pairToTrade\n";
					print "trend bias=$bias\n";
					print date("c", $this->getTickTime())."\n";
				}

				$windowClosingRatio = ($candle->closeBid - $min) / ($max - $min);
				
				print "meanCandleSize = $meanCandleSize\n";
				print "lastCandleSize = $lastCandleSize\n";
				print "lastCandleBodySize = $lastCandleBodySize\n";
				print "min = $min\n";
				print "max = $max\n";
				print "tradeWindowRange = $tradeWindowRange\n";

				print "windowClosingRatio = $windowClosingRatio\n";














				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {
					
					//"trendLookbackCandles" => 24*5,	// 120
					//"tradeLookbackCandles" => 24*1,	// 24
					
					$divideBy = $this->settings[$pairToTrade]['trendLookbackCandles'] / $this->settings[$pairToTrade]['tradeLookbackCandles'];
					$avgRange = $trendWindowRange / $divideBy;
					print "avgRange = $avgRange\n";
					
					
					if ($bias == "down" && $windowClosingRatio > $this->settings[$pairToTrade]['windowRatioThresholdSell']) {

						// short
						$TP = $candle->closeAsk - ($avgRange * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeAsk + ($avgRange * $this->settings[$pairToTrade]['slMultiplier']);
						
						if (abs($candle->closeAsk - $SL) > $this->settings[$pairToTrade]['minSL']) {
							
							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";
							
						}

					} else if ($bias == "up" && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdBuy']) {

						// long
						$TP = $candle->closeBid + ($avgRange * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeBid - ($avgRange * $this->settings[$pairToTrade]['slMultiplier']);

						if (abs($candle->closeAsk - $SL) > $this->settings[$pairToTrade]['minSL']) {
							
							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['bid'], $SL, "sell");
					
							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->buy_market($calcUnits, $pairToTrade, $rest);

							print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";
							
						}
								
					} else {
						
						print "\n\nNo trades taken on $pairToTrade\n";
						// print_r($this->settings[$pairToTrade]);
						
					}
					
					
				}  // end: new trade

			}  // end: check for new trade availability

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()




	
	
}	// END: class

