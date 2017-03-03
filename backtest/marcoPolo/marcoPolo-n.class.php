<?php
require_once __DIR__."/../../Fx.class.php";
require_once __DIR__."/../../Statistics.class.php";

/*
	"lookbackHours" => 24;					// how far to look back to detect trend
	"ratioThreshold" => .55;				// ratio to determine up or down trend
	"windowRatioThresholdSell" => .75;		// ratio to determine when to sell...if closing price > this ratio, sell
	"windowRatioThresholdBuy" => .25;		// ratio to determine when to buy....if closing price < this ratio, buy

	"tpMultiplier" = 8;						// multiplier for TP
	"slMultiplier" = 7;						// multiplier for SL

	"acceptedLossPerTrade" = .025;			// how much loss is acceptable per trade
*/





class MarcoPolo extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"USD_CAD",
		//"USD_CHF",
		//"GBP_USD",
		//"USD_JPY",
		//"NZD_USD",
		//"EUR_USD",
		//"AUD_USD",
		);

		$this->settings = array(

			"USD_CAD" => array(
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 24*10,
				"trendRatioThreshold" => .55,
				"tradeLookbackCandles" => 24*1,
				"windowRatioThresholdSell" => .85,
				"windowRatioThresholdBuy" => .15,
				"tpMultiplier" => 11,
				"slMultiplier" => 10,
				"slAdjustPercentage" => .10,
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


			// if trade is in positive territory, move the SL
			if (count($trades->trades) > 0) {

				print "=== $pairToTrade Trade Mgmt ===\n";
				$didSomething = true;

				print_r($trades->trades);

				foreach ($trades->trades as $t) {

					if ($t->takeProfit != "" && $t->stopLoss != "") {

						//$t->id;
						//$t->price;
						//$t->takeProfit;
						//$t->stopLoss;

						// get current quote
						$q = $this->price($pairToTrade);
		
						echo 'Price of ' . $pairToTrade . ' is: ' .$q->bid . ' => ' . $q->ask . "\n";
						$q->mid = ($q->bid + $q->ask) / 2;
						

						// how close are we from open to the take profit value?
						if ($t->side == "buy") {

							$distanceFromOpenToTP = $t->takeProfit - $t->price;
							$distanceFromQuoteToTP = $t->takeProfit - $q->mid;
							
							$tpPercentage = 1 - ($distanceFromQuoteToTP / $distanceFromOpenToTP);

							if ($tpPercentage > $this->settings[$pairToTrade]['slAdjustPercentage']) {
								$originalSL = $t->price - (($distanceFromOpenToTP/$this->settings[$pairToTrade]['tpMultiplier']) * $this->settings[$pairToTrade]['slMultiplier']);
								
								$SL = $originalSL + ($distanceFromOpenToTP * $tpPercentage);
								if ($SL > $t->stopLoss) {
									$this->trade_set_stop($t->id, $t->instrument, $SL);
								}
							}
							
							
							
							$distanceFromOpenToSL = $t->price - $t->stopLoss;
							$distanceFromQuoteToSL = $q->mid - $t->stopLoss;
							
							$slPercentage = 1 - ($distanceFromQuoteToSL / $distanceFromOpenToSL);
							
							if ($slPercentage > $this->settings[$pairToTrade]['slAdjustPercentage']) {
								$originalTP = $t->price + (($distanceFromOpenToSL/$this->settings[$pairToTrade]['slMultiplier']) * $this->settings[$pairToTrade]['tpMultiplier']);
								print "buy side originalTP = $originalTP\n";
								
								$TP = $originalTP - ($distanceFromOpenToSL * $slPercentage);
								if ($TP < $t->takeProfit) {
									
									if ($TP < $q->bid) {
										$this->trade_close($t->id);
									} else {
										$this->trade_set_tp($t->id, $t->instrument, $TP);
									}
									
								}
							}
							


						} else if ($t->side == "sell") {

							$distanceFromOpenToTP = $t->price - $t->takeProfit;
							$distanceFromQuoteToTP = $q->mid - $t->takeProfit;
							
							$tpPercentage = 1 - ($distanceFromQuoteToTP / $distanceFromOpenToTP);

							if ($tpPercentage > $this->settings[$pairToTrade]['slAdjustPercentage']) {
								$originalSL = $t->price + (($distanceFromOpenToTP/$this->settings[$pairToTrade]['tpMultiplier']) * $this->settings[$pairToTrade]['slMultiplier']);
								
								$SL = $originalSL - ($distanceFromOpenToTP * $tpPercentage);
								if ($SL < $t->stopLoss) {
									$this->trade_set_stop($t->id, $t->instrument, $SL);
								}
							}
							

							$distanceFromOpenToSL = $t->stopLoss - $t->price;
							$distanceFromQuoteToSL = $t->stopLoss - $q->mid;
							
							print "sell t->stopLoss = ".$t->stopLoss."\n";
							print "sell t->price = ".$t->price."\n";
							print "sell q->mid = ".$q->mid."\n";
							print "sell distanceFromOpenToSL = $distanceFromOpenToSL\n";
							print "sell distanceFromQuoteToSL = $distanceFromQuoteToSL\n";
							
							$slPercentage = 1 - ($distanceFromQuoteToSL / $distanceFromOpenToSL);
							print "sell slPercentage = $slPercentage\n";
							
							
							if ($slPercentage > $this->settings[$pairToTrade]['slAdjustPercentage']) {
								$originalTP = $t->price - (($distanceFromOpenToSL/$this->settings[$pairToTrade]['slMultiplier']) * $this->settings[$pairToTrade]['tpMultiplier']);
								print "sell side originalTP = $originalTP\n";
								
								$TP = $originalTP + ($distanceFromOpenToSL * $slPercentage);
								if ($TP > $t->takeProfit) {
									
									if ($TP > $q->ask) {
										$this->trade_close($t->id);
									} else {
										$this->trade_set_tp($t->id, $t->instrument, $TP);
									}
									
								}
							}



							
						}



					}


				}


				$refreshTrades = true;

												
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
				$oRest = array("count"=>$this->settings[$pairToTrade]['trendLookbackCandles'] + 24, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";

				$btNumCandles = $this->settings[$pairToTrade]['trendLookbackCandles'] * 5;

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



				// determine mean candle size, min, max, closing ratios, long/short bias, etc
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
				$downDistance = 0;
				$upDistance = 0;
				$min = 99999999;
				$max = 0;
				$loop = 1;


				// detect trend w/in the "trend window"
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['trendLookbackCandles']; $i--) {

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
						$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
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
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->sell_market($calcUnits, $pairToTrade, $rest);

						print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";


					} else if ($bias == "up" && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdBuy']) {

						// long
						$TP = $candle->closeBid + ($avgRange * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeBid - ($avgRange * $this->settings[$pairToTrade]['slMultiplier']);

						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['bid'], $SL, "sell");
				
						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->buy_market($calcUnits, $pairToTrade, $rest);

						print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";
								
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

