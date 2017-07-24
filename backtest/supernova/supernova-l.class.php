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
		"EUR_USD",
//		"USD_JPY",
		);

		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .01,
				"tradeLookbackCandles" => 48,
				"slMultiplier" => 1.25,
				"minSL" => .0020,
				"maxSL" => .0050,
				"tpMultiplier" => 3,
				"tradeHour" => array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23),
//				"tradeHour" => array(0,5,7,8,10,15,16,22,23),
//				"tradeHour" => array(5,6,7,0,14),
//				"tradeHour" => array(6,3,8,0,),
//				"tradeHour" => array(0,3,7,8,10,11,16,21,23),
				"tradeDay" => array("Mon", "Tue", "Wed", "Thu", "Fri"),
//				"tradeDay" => array("Mon", "Tue", "Wed", "Thu"),
				"dojiBodyRatio" => .08,
				"dojiWickRatio" => .30,
			),


		);	// END: settings array


		$this->granularity = "H1";
		$this->statObj = new Statistics();


		// if testing only a single hour, set that here.
		if ($configArr['argvTradeHour'] !== NULL) {
			foreach ($this->currencyBasket as $idx=>$pairToTrade) {
				$this->settings[$pairToTrade]['tradeHour'] = array($configArr['argvTradeHour']);
			}
		}



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


		// don't trade on sat or sun
		if (date("w", $this->getTickTime()) == 0 || date("w", $this->getTickTime()) == 6) {
			print "don't trade on Sat or Sun\n";
			return;
		}



		foreach ($this->currencyBasket as $idx=>$pairToTrade) {
			
			// don't trade USDCHF before 1/10/2015
			if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
				continue;
			}



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;





			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0 && in_array(date("H", $this->getTickTime()), $this->settings[$pairToTrade]['tradeHour']) && in_array(date("D", $this->getTickTime()), $this->settings[$pairToTrade]['tradeDay'])) {

				$didSomething = true;

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
				$candleLast = end($candleArr[$pairToTrade]->candles);
				$candleNextToLast = $candleArr[$pairToTrade]->candles[$candleArrLastIdx-1];


				// get current quote
				$q = $this->price($pairToTrade); print_r($q);

				echo 'Price of ' . $pairToTrade . ' is: ' .$q->bid . ' => ' . $q->ask . "\n\n";
				$quote[$pairToTrade]['bid'] = $q->bid;
				$quote[$pairToTrade]['ask'] = $q->ask;
				$quote[$pairToTrade]['mid'] = ($q->bid + $q->ask) / 2;
				$quote[$pairToTrade]['spread'] = $q->ask - $q->bid;


				print_r($candleNextToLast);
				print "\n\n";


				$dojiCandle = false;
				$dojiBias = "none";
				
				if ($this->candleIsDoji($candleNextToLast->openBid, $candleNextToLast->highBid, $candleNextToLast->lowBid, $candleNextToLast->closeBid, $this->settings[$pairToTrade]['dojiBodyRatio'], $this->settings[$pairToTrade]['dojiWickRatio'])) {

					print "doji found.\n";
					$dojiCandle = true;
					
					if ($candleLast->closeBid > $candleNextToLast->highBid) {
						$dojiBias = "up";
					} else if ($candleLast->closeBid < $candleNextToLast->lowBid) {
						$dojiBias = "down";
					}
					
					// loop through candle array and find the min and max values
					$min = 99999999;
					$max = 0;

					// get min and max w/in the trade window
					for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {
	
						if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
							$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
						}
	
						if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
							$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
						}
	
					}


					print "min = $min\n";
					print "max = $max\n";


				}





				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {
										
					
					if ($dojiBias == "down") {

						// short
						$range = abs($quote[$pairToTrade]['ask'] - $candleNextToLast->highAsk);
						$SL = $candle->closeAsk + $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeAsk - $SL);

						$distanceToMin = abs($candle->closeAsk - $min);
						$TP = $candle->closeAsk - ($distanceToMin * $this->settings[$pairToTrade]['tpMultiplier']);
						$distanceToTP = abs($candle->closeAsk - $TP);
						
						if ($range >= $this->settings[$pairToTrade]['minSL'] && $range <= $this->settings[$pairToTrade]['maxSL'] && $distanceToTP > $distanceToSL) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeAsk, $SL, "buy");

							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";

						}

					} else if ($dojiBias == "up") {

						// long
						$range = abs($quote[$pairToTrade]['bid'] - $candleNextToLast->lowBid);
						$SL = $candle->closeBid - $range * $this->settings[$pairToTrade]['slMultiplier'];
						$distanceToSL = abs($candle->closeBid - $SL);

						$distanceToMax = abs($candle->closeBid - $max);
						$TP = $candle->closeBid + ($distanceToMax * $this->settings[$pairToTrade]['tpMultiplier']);
						$distanceToTP = abs($candle->closeBid - $TP);

						if ($range >= $this->settings[$pairToTrade]['minSL'] && $range <= $this->settings[$pairToTrade]['maxSL'] && $distanceToTP > $distanceToSL) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $candle->closeBid, $SL, "sell");
					
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




	// determine if a candle is a doji candle
	function candleIsDoji($open, $high, $low, $close, $bodyPercent=.05, $wickPercent=.40)
	{
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
		
		
		print "bodyPercent=$bodyPercent\n";
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
				return true;
			} else {
				return false;
			}
			
		} else {
			// no divide by 0!
			return false;
		}

	}


	
	
}	// END: class

