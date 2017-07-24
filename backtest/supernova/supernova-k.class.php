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
		//"GBP_USD",
		);

		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .02,
				"tradeLookbackCandles" => 6,
				"slMultiplier" => .85,
				"minSL" => .0025,
				"maxSL" => .0040,
				"TPpips" => .0120,
				"tradeHour" => array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23),
				"dojiBodyRatio" => .07,
			),


		);	// END: settings array


		$this->granularity = "H1";
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
			if (count($trades->trades) == 0 && in_array(date("H", $this->getTickTime()), $this->settings[$pairToTrade]['tradeHour'])) {

				$didSomething = true;

				print "=== $pairToTrade Get Candles ===\n";

				// get current candles
				$oRest = array("count"=>$this->settings[$pairToTrade]['tradeLookbackCandles'] + 72, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";

				$btNumCandles = $this->settings[$pairToTrade]['tradeLookbackCandles'] + 72;
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
				$pivotPointCovered = false;
				
				if ($this->candleIsDoji($candleNextToLast->openBid, $candleNextToLast->highBid, $candleNextToLast->lowBid, $candleNextToLast->closeBid, $this->settings[$pairToTrade]['dojiBodyRatio'])) {

					print "doji found.\n";
					$dojiCandle = true;
					
					if ($candleLast->closeBid > $candleNextToLast->highBid) {
						$dojiBias = "up";
					} else if ($candleLast->closeBid < $candleNextToLast->lowBid) {
						$dojiBias = "down";
					}

				}
				
				
				
				if ($dojiBias == "up" || $dojiBias == "down") {
					
					// calculate pivot points from last 24 candles
					// http://www.babypips.com/school/middle-school/pivot-points/how-to-calculate-pivot-points.html
					$low = 9999999999;
					$high = 0;


					// find the start and end indexes for the previous period 17:00 to 17:00
					$ppStartIdx = -1;
					$ppEndIdx = -1;
					
					print "find the start and end indexes for the previous period 17:00 to 17:00\n";
					for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-72; $i--) {

						print "$i - ".date("c", $candleArr[$pairToTrade]->candles[$i]->time)."\n";

						if (date("H", $candleArr[$pairToTrade]->candles[$i]->time) == 17) {
							if ($ppEndIdx == -1) { $ppEndIdx = $i; }
							else if ($ppStartIdx == -1) { $ppStartIdx = $i; }
						}
					}

					print "ppStartIdx=$ppStartIdx\n";
					print "ppEndIdx=$ppEndIdx\n";
					
					if ($ppStartIdx == -1 || $ppEndIdx == -1) {
						print "die\n";
						exit;
					}


					for ($i=$ppStartIdx; $i<$ppEndIdx; $i++) {


						if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $high) {
							$high = $candleArr[$pairToTrade]->candles[$i]->highAsk;
						}
						
						if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $low) {
							$low = $candleArr[$pairToTrade]->candles[$i]->lowBid;
						}
						
					}
					
					$close = $candleArr[$pairToTrade]->candles[$ppEndIdx]->closeBid;
					
					print "high=$high\n";
					print "low=$low\n";
					print "close=$close\n";


					$pp = ($high + $low + $candleLast->closeBid) / 3;
					$r1 = (2 * $pp) - $low;
					$s1 = (2 * $pp) - $high;
					$r2 = $pp + ($high - $low);
					$s2 = $pp - ($high - $low);
					$r3 = $high + 2 * ($pp - $low);
					$s3 = $low - 2 * ($high - $pp);

					print "r3=$r3\n";
					print "r2=$r2\n";
					print "r1=$r1\n";
					print "pp=$pp\n";
					print "s1=$s1\n";
					print "s2=$s2\n";
					print "s3=$s3\n";



					if (($pp < $candleLast->highAsk && $pp > $candleLast->lowBid) ||
						($r1 < $candleLast->highAsk && $r1 > $candleLast->lowBid) ||
						($s1 < $candleLast->highAsk && $s1 > $candleLast->lowBid) ||
						($r2 < $candleLast->highAsk && $r2 > $candleLast->lowBid) ||
						($s2 < $candleLast->highAsk && $s2 > $candleLast->lowBid) ||
						($r3 < $candleLast->highAsk && $r3 > $candleLast->lowBid) ||
						($s3 < $candleLast->highAsk && $s3 > $candleLast->lowBid) || 
						($pp < $candleNextToLast->highAsk && $pp > $candleNextToLast->lowBid) ||
						($r1 < $candleNextToLast->highAsk && $r1 > $candleNextToLast->lowBid) ||
						($s1 < $candleNextToLast->highAsk && $s1 > $candleNextToLast->lowBid) ||
						($r2 < $candleNextToLast->highAsk && $r2 > $candleNextToLast->lowBid) ||
						($s2 < $candleNextToLast->highAsk && $s2 > $candleNextToLast->lowBid) ||
						($r3 < $candleNextToLast->highAsk && $r3 > $candleNextToLast->lowBid) ||
						($s3 < $candleNextToLast->highAsk && $s3 > $candleNextToLast->lowBid)) {
							
						$pivotPointCovered = true;
						
					} 
					
				}





				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {
										
					
					if ($dojiBias == "down" && $pivotPointCovered === true) {

						// short
						$range = abs($quote[$pairToTrade]['ask'] - $candleNextToLast->highAsk);
						$SL = $candle->closeAsk + $range * $this->settings[$pairToTrade]['slMultiplier'];
						$TP = $candle->closeAsk - $this->settings[$pairToTrade]['TPpips'];
						
						if ($range >= $this->settings[$pairToTrade]['minSL'] && $range <= $this->settings[$pairToTrade]['maxSL']) {

							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";

						}

					} else if ($dojiBias == "up" && $pivotPointCovered === true) {

						// long
						$range = abs($quote[$pairToTrade]['bid'] - $candleNextToLast->lowBid);
						$SL = $candle->closeAsk - $range * $this->settings[$pairToTrade]['slMultiplier'];
						$TP = $candle->closeAsk + $this->settings[$pairToTrade]['TPpips'];

						if ($range >= $this->settings[$pairToTrade]['minSL'] && $range <= $this->settings[$pairToTrade]['maxSL']) {

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




	// determine if a candle is a doji candle
	function candleIsDoji($open, $high, $low, $close, $bodyPercent=.05)
	{
		$candleSizeTotal = abs($high - $low);
		$candleSizeBody = abs($open - $close);
		print "bodyPercent=$bodyPercent\n";
		print "candleSizeTotal=$candleSizeTotal\n";
		print "candleSizeBody=$candleSizeBody\n";
		
		if ($candleSizeTotal > 0) {
			
			$ratio = $candleSizeBody / $candleSizeTotal;
			print "ratio=$ratio\n\n";

			if ($ratio <= $bodyPercent) {
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

