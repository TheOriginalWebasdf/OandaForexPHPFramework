<?php
require_once __DIR__."/../Fx.class.php";
require_once __DIR__."/../Statistics.class.php";

// https://www.dailyfx.com/forex/education/trading_tips/post_of_the_day/2013/10/03/How_to_Trade_the_Forex_Doji_Breakout.html
class Supernova extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"GBP_USD",
		"EUR_USD",
		"USD_JPY",
		"USD_CAD",
		);

		$this->settings = array(

			 "GBP_USD" => array(
			 	"acceptedLossPerTrade" => .02/5,
			 	"tradeLookbackCandles" => 7,
			 	"slMultiplier" => 1.6,
			 	"minSL" => .0075,
			 	"maxSL" => .0300,
			 	"tpMultiplier" => 3,
			 	"tradeDay" => array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri"),
			 	"dojiBodyRatio" => .30,
			 	"dojiWickRatio" => .15,
			 	"maxTrades" => 5,
				"slAdjustMultiplier" => .7,
			 ),

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .02/3,
				"tradeLookbackCandles" => 7,
				"slMultiplier" => .4,
				"minSL" => .0075,
				"maxSL" => .0200,
				"tpMultiplier" => 1.2,
				"tradeDay" => array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri"),
				"dojiBodyRatio" => .12,
				"dojiWickRatio" => .20,
				"maxTrades" => 3,
				"slAdjustMultiplier" => .4,
			),

			"USD_JPY" => array(
				"acceptedLossPerTrade" => .02/5,
				"tradeLookbackCandles" => 5,
				"slMultiplier" => .5,
				"minSL" => 0.50,
				"maxSL" => 3.50,
				"tpMultiplier" => 3.25,
				"tradeDay" => array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri"),
				"dojiBodyRatio" => .15,
				"dojiWickRatio" => .20,
				"maxTrades" => 5,
				"slAdjustMultiplier" => .5,
			),

			"USD_CAD" => array(
				"acceptedLossPerTrade" => .02/3,
				"tradeLookbackCandles" => 3,
				"slMultiplier" => .3,
				"minSL" => 0.0050,
				"maxSL" => 0.0350,
				"tpMultiplier" => 1.5,
				"tradeDay" => array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri"),
				"dojiBodyRatio" => .12,
				"dojiWickRatio" => .20,
				"maxTrades" => 3,
				"slAdjustMultiplier" => .5,
			),




		);	// END: settings array


		$this->granularity = "D";
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
			
			// // don't trade USDCHF before 1/10/2015
			// if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
			// 	continue;
			// }



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;



			print "=== $pairToTrade Get Candles ===\n";

			// get current candles
			$oRest = array("count"=>$this->settings[$pairToTrade]['tradeLookbackCandles'] + 24, "alignmentTimezone"=>"America/Chicago");
			$oGran = $this->granularity;
			$oCandleFormat = "bidask";

			$btNumCandles = $this->settings[$pairToTrade]['tradeLookbackCandles'] + 24;
			print "btNumCandles=$btNumCandles\n";

			$candleArr[$pairToTrade] = $this->candles($pairToTrade, $oGran, $oRest, $oCandleFormat, $btNumCandles);
			
			array_pop($candleArr[$pairToTrade]->candles);

			$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
			print "candleArrLastIdx=$candleArrLastIdx";

			$candle = end($candleArr[$pairToTrade]->candles);
			$triggerCandle = end($candleArr[$pairToTrade]->candles);
			



			$totalTrades = count($trades->trades);
			if (count($trades->trades) > 0) {

				// get current quote
				$q = $this->price($pairToTrade); print_r($q);

				// check if all trades currently have profit
				$allTradesHaveProfit = false;
				$profitCount = 0;
				foreach ($trades->trades as $t) {
					if ($t->side == "buy") {
						$currentProfit = $candle->closeBid - $t->price;
					}
					
					if ($t->side == "sell") {
						$currentProfit = $t->price - $candle->closeAsk;
					}
					
					if ($currentProfit > 0) {
						$profitCount++;
					}
				}
				
				// fade in and adjust SL
				$tradeLoopCount=0;
				if ($profitCount == $totalTrades) {
					foreach ($trades->trades as $t) {
		
						if ($t->side == "buy") {
		
							// base decision for SL move and fade in based on the first trade for this pair
							if ($tradeLoopCount == 0) {
								$currentProfit = $candle->closeBid - $t->price;
								$oldSL = $t->stopLoss;
								$newSL = $t->stopLoss + $currentProfit * $this->settings[$pairToTrade]['slAdjustMultiplier'];
								$distanceToSL = abs($candle->closeBid - $newSL);
								print "actual distanceToSL=$distanceToSL\n";
								
								// fade in with new trade
								if ($newSL > $oldSL && $totalTrades < $this->settings[$pairToTrade]['maxTrades']) {
									$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeBid, $newSL, "sell");
									$TP = $candle->closeBid + (abs($candle->closeBid - $newSL) * ($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier']));
									$distanceToTP = abs($candle->closeBid - $TP);
							
									print "TP=$TP\n";
									print "actual distanceToTP=$distanceToTP\n";
									print "settings TP / SL=".round($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier'], 2)."\n";
									print "actual distanceToTP/distanceToSL=".round($distanceToTP / $distanceToSL, 2)."\n";

									$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $newSL));
									$this->buy_market($calcUnits, $pairToTrade, $rest);
			
									print "MARKET BUY FADE IN $pairToTrade at ".$candle->closeAsk."\n";
									$didSomething = true;
								}
								
							}

							if ($newSL > $oldSL) {
								print "$pairToTrade LONG - set new stop - oldSL=$oldSL  newSL=$newSL\n";
								$this->trade_set_stop($t->id, $pairToTrade, $newSL);
								$didSomething = true;
							}
							
						} else if ($t->side == "sell") {

							// base decision for SL move and fade in based on the first trade for this pair
							if ($tradeLoopCount == 0) {
								$currentProfit = $t->price - $candle->closeAsk;
								$oldSL = $t->stopLoss;
								$newSL = $t->stopLoss - $currentProfit * $this->settings[$pairToTrade]['slAdjustMultiplier'];
								$distanceToSL = abs($candle->closeAsk - $newSL);
								print "actual distanceToSL=$distanceToSL\n";

								// fade in with new trade
								if ($newSL < $oldSL && $totalTrades < $this->settings[$pairToTrade]['maxTrades']) {
									$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeAsk, $newSL, "buy");
									$TP = $candle->closeAsk - (abs($candle->closeAsk - $newSL) * ($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier']));
									$distanceToTP = abs($candle->closeAsk - $TP);
		
									print "TP=$TP\n";
									print "actual distanceToTP=$distanceToTP\n";
									print "settings TP / SL=".round($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier'], 2)."\n";
									print "actual distanceToTP/distanceToSL=".round($distanceToTP / $distanceToSL, 2)."\n";

									$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $newSL));
									$this->sell_market($calcUnits, $pairToTrade, $rest);
		
									print "MARKET SELL FADE IN $pairToTrade at ".$candle->closeBid."\n";
									$didSomething = true;
								}
								
							}
							
							if ($newSL < $oldSL) {
								print "$pairToTrade SHORT - set new stop - oldSL=$oldSL  newSL=$newSL\n";
								$this->trade_set_stop($t->id, $pairToTrade, $newSL);
								$didSomething = true;
							}
							
						}	// END: buy/sell if block
						
						$tradeLoopCount++;

					}	// END: foreach trade loop

				}   // END: all trades have profit

			}	// END: trades exist for this pair
	
	
			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}


			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0 && in_array(date("D", $this->getTickTime()), $this->settings[$pairToTrade]['tradeDay'])) {

				$didSomething = true;


				// loop through candles to find the most recent doji and
				// the min and max values of the trade window
				$dojiIdx = -1;
				$min = 99999999;
				$max = 0;

				// get min and max w/in the trade window
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {

					$loopCandle = $candleArr[$pairToTrade]->candles[$i];

					if ($loopCandle->lowBid < $min) {
						$min = $loopCandle->lowBid;
					}

					if ($loopCandle->highAsk > $max) {
						$max = $loopCandle->highAsk;
					}
					
					if ($dojiIdx == -1 && in_array(date("D", $loopCandle->time), $this->settings[$pairToTrade]['tradeDay']) && $this->candleIsDoji($loopCandle->openBid, $loopCandle->highBid, $loopCandle->lowBid, $loopCandle->closeBid, $this->settings[$pairToTrade]['dojiBodyRatio'], $this->settings[$pairToTrade]['dojiWickRatio'])) {
						$dojiIdx = $i;
					}

				}

				$lookbackRange = $max - $min;
				
				print "min = $min\n";
				print "max = $max\n";
				print "dojiIdx = $dojiIdx\n";


				if ($dojiIdx > -1) {
					$dojiCandle = $candleArr[$pairToTrade]->candles[$dojiIdx];
					print "doji Tick = ".date("c", $dojiCandle->time)."\n";
					print "trigger Tick = ".date("c", $triggerCandle->time)."\n";

					print "doji O/H/L/C=".$dojiCandle->openBid."/".$dojiCandle->highBid."/".$dojiCandle->lowBid."/".$dojiCandle->closeBid."\n";
					print "trigger O/H/L/C=".$triggerCandle->openBid."/".$triggerCandle->highBid."/".$triggerCandle->lowBid."/".$triggerCandle->closeBid."\n";
					if ($triggerCandle->closeBid > $dojiCandle->highBid) {
						$dojiBias = "up";
					} else if ($triggerCandle->closeBid < $dojiCandle->lowBid) {
						$dojiBias = "down";
					} else {
						$dojiBias = "none";
					}
					
	
					print "dojiBias = $dojiBias\n";




					print "================================ $pairToTrade New Trades ==================================\n";


					
					if ($dojiBias == "down") {

						// short
						$range = abs($candle->closeAsk - $dojiCandle->highAsk);
						$SL = $candle->closeAsk + $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeAsk - $SL);
						
						print "range=$range\n";
						print "SL=$SL\n";
						print "actual distanceToSL=$distanceToSL\n";
						print "minSL=".$this->settings[$pairToTrade]['minSL']."\n";
						print "maxSL=".$this->settings[$pairToTrade]['maxSL']."\n";
						
						if ($distanceToSL >= $this->settings[$pairToTrade]['minSL'] && $distanceToSL <= $this->settings[$pairToTrade]['maxSL']) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeAsk, $SL, "buy");
							$TP = $candle->closeAsk - (abs($candle->closeAsk - $SL) * ($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier']));
							$distanceToTP = abs($candle->closeAsk - $TP);

							print "TP=$TP\n";
							print "actual distanceToTP=$distanceToTP\n";
							print "settings TP / SL=".round($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier'], 2)."\n";
							print "actual distanceToTP/distanceToSL=".round($distanceToTP / $distanceToSL, 2)."\n";

							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";

						} else {
							print "minSL & maxSL requirements not met.\nNo trades taken.";
						}

					} else if ($dojiBias == "up") {

						// long
						$range = abs($candle->closeBid - $dojiCandle->lowBid);
						$SL = $candle->closeBid - $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeBid - $SL);

						print "range=$range\n";
						print "SL=$SL\n";
						print "distanceToSL=$distanceToSL\n";
						print "minSL=".$this->settings[$pairToTrade]['minSL']."\n";
						print "maxSL=".$this->settings[$pairToTrade]['maxSL']."\n";
						
						if ($distanceToSL >= $this->settings[$pairToTrade]['minSL'] && $distanceToSL <= $this->settings[$pairToTrade]['maxSL']) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeBid, $SL, "sell");
							$TP = $candle->closeBid + (abs($candle->closeBid - $SL) * ($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier']));
							$distanceToTP = abs($candle->closeBid - $TP);

							print "TP=$TP\n";
							print "actual distanceToTP=$distanceToTP\n";
							print "settings TP / SL=".round($this->settings[$pairToTrade]['tpMultiplier'] / $this->settings[$pairToTrade]['slMultiplier'], 2)."\n";
							print "actual distanceToTP/distanceToSL=".round($distanceToTP / $distanceToSL, 2)."\n";
					
							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->buy_market($calcUnits, $pairToTrade, $rest);

							print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";

						} else {
							print "minSL & maxSL requirements not met.\nNo trades taken.";
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




	// determine if a candle is a doji candle
	function candleIsDoji($open, $high, $low, $close, $bodyPercent=.05, $wickPercent=.40)
	{
		print "\n\n===== candleIsDoji Start =====\n";
		print "open=$open\n";
		print "high=$high\n";
		print "low=$low\n";
		print "close=$close\n";
		print "bodyPercent=$bodyPercent\n";
		print "wickPercent=$wickPercent\n";

		$candleSizeTotal = abs($high - $low);
		$candleSizeBody = abs($open - $close);
		
		if ($close > $open) {
			// green candle
			$topWickSize = $high - $close;
			$botWickSize = $open - $low;
		} else {
			// red candle
			$topWickSize = $high - $open;
			$botWickSize = $close - $low;
		}
		
		
		print "candleSizeTotal=$candleSizeTotal\n";
		print "candleSizeBody=$candleSizeBody\n";
		
		if ($candleSizeTotal > 0) {
			
			$bodyRatio = $candleSizeBody / $candleSizeTotal;
			print "bodyRatio=$bodyRatio\n\n";

			$topWickRatio = $topWickSize / $candleSizeTotal;
			print "topWickRatio=$topWickRatio\n\n";

			$botWickRatio = $botWickSize / $candleSizeTotal;
			print "botWickRatio=$botWickRatio\n\n";

			if ($bodyRatio <= $bodyPercent && $topWickRatio > $wickPercent && $botWickRatio > $wickPercent) {
				print "FOUND DOJI\nreturn true\n========candleIsDoji END========\n";
				return true;
			} else {
				print "NO DOJI FOUND\n return false\n==========candleIsDoji END===========\n";
				return false;
			}
			
		} else {
			// no divide by 0!
			return false;
		}

	}


	
	
}	// END: class

