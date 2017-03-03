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
				"lookbackHours" => 24*5,
				"ratioThreshold" => .5,
				"windowRatioThresholdSell" => .7,
				"windowRatioThresholdBuy" => .3,
				"tpMultiplier" => 5,
				"slMultiplier" => 3,
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
			
			

			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
			if (count($trades->trades) == 0) {

				$didSomething = true;

				print "=== $pairToTrade Get Candles ===\n";

				// get current candles
				$oRest = array("count"=>$this->settings[$pairToTrade]['lookbackHours'] + 24, "alignmentTimezone"=>"America/Chicago");
				$oGran = $this->granularity;
				$oCandleFormat = "bidask";

				$btNumCandles = $this->settings[$pairToTrade]['lookbackHours'] * 5;

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
				$candleSizeArr = array();
				$loop = 1;

				for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['lookbackHours']; $i--) {
					$candleSizeArr[] = $candleArr[$pairToTrade]->candles[$i]->highAsk - $candleArr[$pairToTrade]->candles[$i]->lowBid;

					if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
						$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
					}

					if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
						$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
					}

					$distance = ($candleArr[$pairToTrade]->candles[$i]->closeBid - $candleArr[$pairToTrade]->candles[$i]->openBid) * $loop;
					
					if ($distance < 0) { $downDistance += $distance; }
					else if ($distance > 0) { $upDistance += $distance; }
					$loop++;
				}

				$meanCandleSize = $this->statObj->mean($candleSizeArr);
				$lastCandleSize = $candle->highAsk - $candle->lowBid;
				$lastCandleBodySize = $candle->closeBid - $candle->openBid;

				$downDistance = abs($downDistance);
				$totalDistance = $upDistance + $downDistance;
				if ($totalDistance > 0) {
					$upRatio = $upDistance / $totalDistance;
					$downRatio = $downDistance / $totalDistance;
				}
				
				$windowClosingRatio = ($candle->closeBid - $min) / ($max - $min);
				
				print "meanCandleSize = $meanCandleSize\n";
				print "lastCandleSize = $lastCandleSize\n";
				print "lastCandleBodySize = $lastCandleBodySize\n";
				print "min = $min\n";
				print "max = $max\n";

				print "upDistance = $upDistance\n";
				print "downDistance = $downDistance\n";
				print "upRatio = $upRatio\n";
				print "downRatio = $downRatio\n";

				print "windowClosingRatio = $windowClosingRatio\n";


				if ($downRatio > $this->settings[$pairToTrade]['ratioThreshold']) {
					$bias = "down";
				} else if ($upRatio > $this->settings[$pairToTrade]['ratioThreshold']) {
					$bias = "up";
				} else {
					$bias = "none";
				}







				print "================================ $pairToTrade New Trades ==================================\n";

				if (count($trades->trades) == 0) {
					
					if ($bias == "down" && $windowClosingRatio > $this->settings[$pairToTrade]['windowRatioThresholdSell']) {

						// short
						$TP = $candle->closeAsk - ($meanCandleSize * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeAsk + ($meanCandleSize * $this->settings[$pairToTrade]['slMultiplier']);
						
						$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");

						$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
						$this->sell_market($calcUnits, $pairToTrade, $rest);

						print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";


					} else if ($bias == "up" && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdBuy']) {

						// long
						$TP = $candle->closeBid + ($meanCandleSize * $this->settings[$pairToTrade]['tpMultiplier']);
						$SL = $candle->closeBid - ($meanCandleSize * $this->settings[$pairToTrade]['slMultiplier']);

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

