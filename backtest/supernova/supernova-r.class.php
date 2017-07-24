<?php
require_once __DIR__."/../../Fx.class.php";
require_once __DIR__."/../../Statistics.class.php";

// https://www.dailyfx.com/forex/education/trading_tips/post_of_the_day/2013/10/03/How_to_Trade_the_Forex_Doji_Breakout.html
class Supernova extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"GBP_USD",
		//"EUR_USD",
		//"USD_JPY",
		);

		$this->settings = array(

			 "GBP_USD" => array(
			 	"acceptedLossPerTrade" => .005,
			 	"tradeLookbackCandles" => 10,
			 	"slMultiplier" => 1.9,
			 	"minSL" => .0100,
			 	"maxSL" => .0500,
			 	"tpMultiplier" => 3.5,
			 	"tradeDay" => array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri"),
			 	"maxTrades" => 5,
			 ),

/*

*/

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

				$tradeLoopCount=0;
				foreach ($trades->trades as $t) {
	
					if ($t->side == "buy") {
	
						$currentProfit = $candle->closeBid - $candle->openBid;
						$oldSL = $t->stopLoss;
						$newSL = $t->stopLoss + $currentProfit;
						
						if ($newSL > $oldSL) {
							print "$pairToTrade LONG - set new stop - oldSL=$oldSL  newSL=$newSL\n";
							$this->trade_set_stop($t->id, $pairToTrade, $newSL);

							// fade in with new trade
							if ($tradeLoopCount == 0 && $totalTrades < $this->settings[$pairToTrade]['maxTrades']) {
								$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeBid, $newSL, "sell");
						
								$rest = array("stopLoss" => $this->forex_round($pairToTrade, $newSL));
								$this->buy_market($calcUnits, $pairToTrade, $rest);
		
								print "MARKET BUY FADE IN $pairToTrade at ".$candle->closeAsk."\n";
							}

							$didSomething = true;
						}
						
					} else if ($t->side == "sell") {

						$currentProfit = $candle->openAsk - $candle->closeAsk;
						$oldSL = $t->stopLoss;
						$newSL = $t->stopLoss - $currentProfit;
						
						if ($newSL < $oldSL) {
							print "$pairToTrade SHORT - set new stop - oldSL=$oldSL  newSL=$newSL\n";
							$this->trade_set_stop($t->id, $pairToTrade, $newSL);
							
							// fade in with new trade
							if ($tradeLoopCount == 0 && $totalTrades < $this->settings[$pairToTrade]['maxTrades']) {
								$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeAsk, $newSL, "buy");
	
								$rest = array("stopLoss" => $this->forex_round($pairToTrade, $newSL));
								$this->sell_market($calcUnits, $pairToTrade, $rest);
	
								print "MARKET SELL FADE IN $pairToTrade at ".$candle->closeBid."\n";
							}
							
							$didSomething = true;
						}
						
					}	// END: buy/sell if block
					
					$tradeLoopCount++;

				}	// END: foreach trade loop
	
			}
	
	
			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}


			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0 && in_array(date("D", $this->getTickTime()), $this->settings[$pairToTrade]['tradeDay'])) {

				$didSomething = true;


				// look at the previous candle
				// if green, buy
				// if red, sell
				if ($candle->openBid > $candle->closeBid) { $candleBias = "down"; }
				else { $candleBias = "up"; }
				print "candleBias = $candleBias\n\n";



					print "================================ $pairToTrade New Trades ==================================\n";


					
					if ($candleBias == "down") {

						// short
						$range = abs($candle->highAsk - $candle->lowAsk);
						$SL = $candle->closeAsk + $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeAsk - $SL);

						$TP = $candle->closeAsk - ($range * $this->settings[$pairToTrade]['tpMultiplier']);
						$distanceToTP = abs($candle->closeAsk - $TP);
						
						print "range=$range\n";
						print "SL=$SL\n";
						print "distanceToSL=$distanceToSL\n";
						//print "TP=$TP\n";
						//print "distanceToTP=$distanceToTP\n";
						print "minSL=".$this->settings[$pairToTrade]['minSL']."\n";
						print "maxSL=".$this->settings[$pairToTrade]['maxSL']."\n";
						
						if ($distanceToSL >= $this->settings[$pairToTrade]['minSL'] && $distanceToSL <= $this->settings[$pairToTrade]['maxSL']) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeAsk, $SL, "buy");

							// $rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$rest = array("stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";

						} else {
							print "minSL & maxSL requirements not met.\nNo trades taken.\n";
						}

					} else if ($candleBias == "up") {

						// long
						$range = abs($candle->highAsk - $candle->lowAsk);
						$SL = $candle->closeBid - $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeBid - $SL);

						$TP = $candle->closeBid + ($range * $this->settings[$pairToTrade]['tpMultiplier']);
						$distanceToTP = abs($candle->closeBid - $TP);

						print "range=$range\n";
						print "SL=$SL\n";
						print "distanceToSL=$distanceToSL\n";
						//print "TP=$TP\n";
						//print "distanceToTP=$distanceToTP\n";
						print "minSL=".$this->settings[$pairToTrade]['minSL']."\n";
						print "maxSL=".$this->settings[$pairToTrade]['maxSL']."\n";
						
						if ($distanceToSL >= $this->settings[$pairToTrade]['minSL'] && $distanceToSL <= $this->settings[$pairToTrade]['maxSL']) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeBid, $SL, "sell");
					
							//$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$rest = array("stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->buy_market($calcUnits, $pairToTrade, $rest);

							print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";

						} else {
							print "minSL & maxSL requirements not met.\nNo trades taken.\n";
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

