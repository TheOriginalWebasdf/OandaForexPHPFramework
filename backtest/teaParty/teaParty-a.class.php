<?php
require_once __DIR__."/../../Fx.class.php";
require_once __DIR__."/../../Statistics.class.php";

/*
				"acceptedLossPerTrade" => .02,		// how much % loss of balance accepted per trade
				"trendLookbackCandles" => 24*2,		// how many candles to look back for trend detection
				"trendRatioThreshold" => .65,		// after up/down ratio calculated, if it exceeds this threshold, it is determined to be a trend
				"tradeLookbackCandles" => 16,		// how many candles to look back for trade determination
				"tradePercentileThreshold" => .9,
				"tpMultiplier" => 2.75,				// multiply the trade lookback window range to determine TP price
				"slMultiplier" => 2,				// multiply the trade lookback window range to determine SL price

*/





class TeaParty extends Fx {
	
	
	
	// Set up strategry variables
	function __construct($system, $configArr)
	{
		parent::__construct($system);


		$this->currencyBasket = array(
		"EUR_USD",
		//"USD_CAD",
		//"GBP_USD",
		//"AUD_USD",
		//"NZD_USD",
		//"USD_JPY",
		);

		$this->settings = array(

			"EUR_USD" => array(
				"acceptedLossPerTrade" => .015,
				"trendLookbackCandles" => 24*2,
				"trendRatioThreshold" => .65,
				"tradeLookbackCandles" => 24,
				"tradePercentileThresholdMin" => .80,
				"tradePercentileThresholdMax" => .95,
				"tpMultiplier" => 3.2,
				"slMultiplier" => 1.8,
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



		foreach ($this->currencyBasket as $idx=>$pairToTrade) {
			
			// don't trade USDCHF before 1/10/2015
			if ($this->system == "Backtest" && $this->getTickTime() < USDCHF_UNPEGGED && $pairToTrade == "USD_CHF") {
				continue;
			}



			$didSomething = false;
			print "\n\n========================== $pairToTrade =========================\n";



			$trades = $this->trade_pair($pairToTrade, 500);
			$refreshTrades = false;
			

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

				$btNumCandles = $this->settings[$pairToTrade]['trendLookbackCandles'] * 8;
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



				// begin trend detection (long/short bias)
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
					$candleBodySizeArr[] = abs($candleArr[$pairToTrade]->candles[$i]->closeMid - $candleArr[$pairToTrade]->candles[$i]->openMid);

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
						$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
						$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
					}
				}
				

				$meanCandleSize = $this->statObj->mean($candleSizeArr);
				$lastCandleSize = $candle->highAsk - $candle->lowBid;
				$lastCandleBodySize = abs($candle->closeMid - $candle->openMid);
				$tradeWindowRange = $max - $min;
				
				if ($candle->closeMid > $candle->openMid) {
					$lastCandleDirection = "up";
				} else {
					$lastCandleDirection = "down";
				}

				$sizePercentile = $this->statObj->percentile($candleBodySizeArr, $lastCandleBodySize);
				print "sizePercentile=$sizePercentile\n";
				
				print "meanCandleSize = $meanCandleSize\n";
				print "lastCandleSize = $lastCandleSize\n";
				print "lastCandleBodySize = $lastCandleBodySize\n";
				print "min = $min\n";
				print "max = $max\n";
				print "tradeWindowRange = $tradeWindowRange\n";










				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {					
					
					if ($bias == "down" && $lastCandleDirection == "up" && $sizePercentile > $this->settings[$pairToTrade]['tradePercentileThresholdMin'] && $sizePercentile < $this->settings[$pairToTrade]['tradePercentileThresholdMax']) {

						// short
						$TP = $candle->closeAsk - ($lastCandleBodySize * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeAsk + ($lastCandleBodySize * $this->settings[$pairToTrade]['slMultiplier']);
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->sell_market($calcUnits, $pairToTrade, $rest);

						print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";


					} else if ($bias == "up" && $lastCandleDirection == "down" && $sizePercentile > $this->settings[$pairToTrade]['tradePercentileThresholdMin'] && $sizePercentile < $this->settings[$pairToTrade]['tradePercentileThresholdMax']) {

						// long
						$TP = $candle->closeBid + ($lastCandleBodySize * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeBid - ($lastCandleBodySize * $this->settings[$pairToTrade]['slMultiplier']);

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

