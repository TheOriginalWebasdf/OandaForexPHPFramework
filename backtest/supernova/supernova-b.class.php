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
				"acceptedLossPerTrade" => .02,
				"trendLookbackCandles" => 6,
				"trendRatioThreshold" => .5,
				"tradeLookbackCandles" => 3,
				"tpMultiplier" => 1,
				"minSL" => .0005,
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

//			print "\n\n TRADE MAINTENANCE \n\n";
//			none-for now

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



				// determine mean candle size, min, max, closing ratios, long/short bias, etc
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
				$downDistance = 0;
				$upDistance = 0;
				$trendMin = 99999999;
				$trendMax = 0;
				$loop = 1;


				// detect trend w/in the "trend window"
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['trendLookbackCandles']; $i--) {

					print "i=$i\n";

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $trendMin) {
						$trendMin = $candleArr[$pairToTrade]->candles[$i]->lowBid;

						print "i=$i\nlow bid=".$candleArr[$pairToTrade]->candles[$i]->lowBid."\ncurrent min=$trendMin\n";
						print "candle\n";
						print "======\n";
						print_r($candleArr[$pairToTrade]->candles[$i]);
						if ($trendMin == 0) { exit; }
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $trendMax) {
						$trendMax = $candleArr[$pairToTrade]->candles[$i]->highAsk;
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

				$trendWindowRange = $trendMax - $trendMin;
				print "trendWindowRange = $trendWindowRange\n";















				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {

					// analyze the "trade window"
					$tradeMin = 99999999;
					$tradeMax = 0;
					$candleSizeArr = array();
	
					// start analysis back one more candle.
					for ($i=$candleArrLastIdx-1; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {
						$candleSizeArr[] = $candleArr[$pairToTrade]->candles[$i]->highAsk - $candleArr[$pairToTrade]->candles[$i]->lowBid;
	
						if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $tradeMin) {
							$tradeMin = $candleArr[$pairToTrade]->candles[$i]->lowBid;
						}
	
						if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $tradeMax) {
							$tradeMax = $candleArr[$pairToTrade]->candles[$i]->highAsk;
						}
					}
					
	
					$meanCandleSize = $this->statObj->mean($candleSizeArr);
					$lastCandleSize = $candle->highAsk - $candle->lowBid;
					$lastCandleBodySize = $candle->closeBid - $candle->openBid;
					$tradeWindowRange = $tradeMax - $tradeMin;
	
					$sizePercentile = $this->statObj->percentile($candleSizeArr, $lastCandleSize);
					if ($sizePercentile > .9) {
						print "sizePercentile > .9\nsizePercentile=$sizePercentile\n";
						print "pair=$pairToTrade\n";
						print "trend bias=$bias\n";
						print date("c", $this->getTickTime())."\n";
					}
	
					$windowClosingRatio = ($candle->closeBid - $tradeMin) / ($tradeMax - $tradeMin);
					
					print "meanCandleSize = $meanCandleSize\n";
					print "lastCandleSize = $lastCandleSize\n";
					print "lastCandleBodySize = $lastCandleBodySize\n";
					print "tradeMin = $tradeMin\n";
					print "tradeMax = $tradeMax\n";
					print "tradeWindowRange = $tradeWindowRange\n";
	
					print "windowClosingRatio = $windowClosingRatio\n";
	
	
					// where is the closing price of the last available candle in the candle array in relation to the tradeWindowRange
					$distanceToClose = $candle->closeMid - $tradeMin;
					print "distanceToClose = $distanceToClose\n";
					
					$closingPriceRatio = $distanceToClose / $tradeWindowRange;
					print "closingPriceRatio = $closingPriceRatio\n";
					
					

					
					if ($bias == "down" && $closingPriceRatio < 0) {

						// short
						// if < 0% and the trend bis as down, go short
						// SL = 10%
						// TP = (sl - open) * 1.2 (tpMultiplier)
						$SL = $tradeMin + ($tradeWindowRange * .9);
						$TP = $candle->closeAsk - abs($candle->closeBid - $SL) * $this->settings[$pairToTrade]['tpMultiplier'];
						
						if (abs($candle->closeAsk - $SL) > $this->settings[$pairToTrade]['minSL']) {
							
							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);

							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";
							
						}

					} else if ($bias == "up" && $closingPriceRatio > 1) {

						// long
						// if > 100% and the trend bias is up, go long
						// SL = 90%
						// TP = (open + sl) * 1.2 (tpMultiplier)
						$SL = $tradeMin + ($tradeWindowRange * .1);
						$TP = $candle->closeBid + abs($candle->closeBid - $SL) * $this->settings[$pairToTrade]['tpMultiplier'];

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
					
					
				}  // end: new trade

			}  // end: check for new trade availability

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()




	
	
}	// END: class

