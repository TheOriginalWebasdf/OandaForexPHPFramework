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
				"slMultiplier" => 2,				// multiply the trade lookback window range to determine SL price

*/





class Supernova extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"EUR_USD",
		);

		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .02,
				"tradeLookbackCandles" => 6*8,  // 24 hrs
				"trendRatioThreshold" => .65,
				"windowRatioThresholdSell" => .85,
				"windowRatioThresholdBuy" => .15,
				"slMultiplier" => .5,
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





			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0) {

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



				// determine min, max, closing ratio, etc
				$candleArrLastIdx = count($candleArr[$pairToTrade]->candles) - 1;
				$min = 99999999;
				$max = 0;
				$downDistance = 0;
				$upDistance = 0;
				$loop = 0;

				// get min and max w/in the trade window
				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['tradeLookbackCandles']; $i--) {

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


				print "min = $min\n";
				print "max = $max\n";

				$tradeWindowRange = $max - $min;
				print "tradeWindowRange = $tradeWindowRange\n";

				$windowClosingRatio = ($candle->closeBid - $min) / ($max - $min);
				print "windowClosingRatio = $windowClosingRatio\n";


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
















				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {
										
					
					if ($bias == "none" && $windowClosingRatio > $this->settings[$pairToTrade]['windowRatioThresholdSell']) {

						// short
						$TP = ($min + $max) / 2;
						$distanceToTP = abs($candle->closeAsk - $TP);
						$SL = $candle->closeAsk + $distanceToTP * $this->settings[$pairToTrade]['slMultiplier'];
//						$SL = $candle->closeAsk + ($tradeWindowRange * $this->settings[$pairToTrade]['slMultiplier']);
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->sell_market($calcUnits, $pairToTrade, $rest);

						print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";


					} else if ($bias == "none" && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdBuy']) {

						// long
						$TP = ($min + $max) / 2;
						$distanceToTP = abs($candle->closeAsk - $TP);
						$SL = $candle->closeBid - $distanceToTP * $this->settings[$pairToTrade]['slMultiplier'];
//						$SL = $candle->closeBid - ($tradeWindowRange * $this->settings[$pairToTrade]['slMultiplier']);

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

