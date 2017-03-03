<?php
require_once __DIR__."/../../Fx.class.php";

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
				"lookbackHours" => 24*1,
				"tpMultiplier" => 3.75,
				"slMultiplier" => 1.25,
				"windowRatioThresholdBuyMin" => .1,
				"windowRatioThresholdBuyMax" => .15,
				"windowRatioThresholdSellMin" => .85,
				"windowRatioThresholdSellMax" => .9,
				"entryPercMin" => .5,

			),
			


			//"USD_JPY" => array(
				//"acceptedLossPerTrade" => .02,
				//"lookbackHours" => 24*5,
				//"tpMultiplier" => 1,
				//"slMultiplier" => 1,
				//"windowRatioThresholdBuyMin" => 0,
				//"windowRatioThresholdBuyMax" => .4,
				//"windowRatioThresholdSellMin" => .6,
				//"windowRatioThresholdSellMax" => 1,

			//),


			//"NZD_USD" => array(
				//"acceptedLossPerTrade" => .02,
				//"lookbackHours" => 24*5,
				//"tpMultiplier" => 1,
				//"slMultiplier" => 1,

			//),

			//"AUD_USD" => array(
				//"acceptedLossPerTrade" => .02,
				//"lookbackHours" => 24*5,
				//"tpMultiplier" => 1,
				//"slMultiplier" => 1,

			//),

		);	// END: settings array


		$this->granularity = "H1";




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


		$this->cotName = array(
								"AUD_USD" => "AUSTRALIAN DOLLAR - CHICAGO MERCANTILE EXCHANGE",
								"GBP_USD" => "BRITISH POUND STERLING - CHICAGO MERCANTILE EXCHANGE",
								"USD_CAD" => "CANADIAN DOLLAR - CHICAGO MERCANTILE EXCHANGE",
								"EUR_USD" => "EURO FX - CHICAGO MERCANTILE EXCHANGE",
								"USD_JPY" => "JAPANESE YEN - CHICAGO MERCANTILE EXCHANGE",
								"USD_MXN" => "MEXICAN PESO - CHICAGO MERCANTILE EXCHANGE",
								"NZD_USD" => "NEW ZEALAND DOLLAR - CHICAGO MERCANTILE EXCHANGE",
								"USD_CHF" => "SWISS FRANC - CHICAGO MERCANTILE EXCHANGE",
								"USD_ZAR" => "SOUTH AFRICAN RAND - CHICAGO MERCANTILE EXCHANGE",
							);

		$this->cotDB = new SQLite3("cot-history.sl3");
		
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
			
			
			

			// if cot bias changes, close the trade
			if (0 && count($trades->trades) > 0) {

				print "=== $pairToTrade Trade Mgmt ===\n";
				$didSomething = true;


				// get the last available COT for this pair
				$cot = $this->getCot($pairToTrade);
				print_r($cot);
				print_r($trades->trades);

				$totalPositions = $cot['Lev_Money_Positions_Long_All'] + $cot['Lev_Money_Positions_Short_All'] + $cot['Lev_Money_Positions_Spread_All'];
				$longPerc = $cot['Lev_Money_Positions_Long_All'] / $totalPositions;
				$shortPerc = $cot['Lev_Money_Positions_Short_All'] / $totalPositions;
				$closeTrades = false;


				print "total long=".$cot['Lev_Money_Positions_Long_All']."\n";
				print "total short=".$cot['Lev_Money_Positions_Short_All']."\n";
				print "total positions=".$totalPositions."\n";
				print "long perc=".$longPerc."\n";
				print "short perc=".$shortPerc."\n";

				if ($longPerc > $this->settings[$pairToTrade]['entryPercMin']) {
					$cotBias = "up";
				} else if ($shortPerc > $this->settings[$pairToTrade]['entryPercMin']) {
					$cotBias = "down";
				} else {
					$cotBias = "none";
				}
				
				// because all COT reports are FOREIGN_USD
				// if pairToTrade is USD_FOREIGN, flip the bias variable
				$exp = explode("_", $pairToTrade);
				if ($exp[0] == "USD") {
					if ($cotBias == "up") { $bias = "down"; }
					else if ($cotBias == "down") { $bias = "up"; }
					else { $bias = "none"; }
				} else {
					$bias = $cotBias;
				}
								
				print "cotBias = $cotBias\n";
				print "bias = $bias\n";


				if ($trades->trades[0]->side == "buy" && $bias != "up") {
					print "longs fell below 60 percent.  Closing all trades.\n";
					$closeTrades = true;
				} else if ($trades->trades[0]->side == "sell" && $bias != "down") {
					print "shorts fell below 60 percent.  Closing all trades.\n";
					$closeTrades = true;
				}

				if ($closeTrades === true) {
					print "Close $pairToTrade\n";

					foreach ($trades->trades as $t) {
						print "=== CLOSE $pairToTrade (".$t->id.") ===\n";
						$this->trade_close($t->id);
						$refreshTrades = true;
					}
				}
												
			}
			

			if ($refreshTrades === true) {
				$trades = $this->trade_pair($pairToTrade, 500);
			}





			// if no trades open for this pair, determine how to trade (or not trade)
			// begin: check for new trade availability
//			if (date("D", $this->getTickTime()) == "Mon" && date("H", $this->getTickTime()) == 2 && count($trades->trades) == 0) {
			if (count($trades->trades) == 0) {

				$didSomething = true;


				// get the last available COT for this pair
				$cot = $this->getCot($pairToTrade);
				print_r($cot);


				if ($cot['Market_and_Exchange_Names'] != "") {

					$totalPositions = $cot['Lev_Money_Positions_Long_All'] + $cot['Lev_Money_Positions_Short_All'] + $cot['Lev_Money_Positions_Spread_All'];
					$longPerc = $cot['Lev_Money_Positions_Long_All'] / $totalPositions;
					$shortPerc = $cot['Lev_Money_Positions_Short_All'] / $totalPositions;
					
					if ($longPerc >= $this->settings[$pairToTrade]['entryPercMin'] && $cot['Change_in_Lev_Money_Long_All'] > 0) {
						$cotBias = "up";
					} else if  ($shortPerc >= $this->settings[$pairToTrade]['entryPercMin'] && $cot['Change_in_Lev_Money_Short_All'] > 0) {
						$cotBias = "down";
					} else {
						$cotBias = "none";
					}
										
					print "total long=".$cot['Lev_Money_Positions_Long_All']."\n";
					print "change in longs=".$cot['Change_in_Lev_Money_Long_All']."\n";
					
					print "total short=".$cot['Lev_Money_Positions_Short_All']."\n";
					print "change in shorts=".$cot['Change_in_Lev_Money_Short_All']."\n";
									
					print "total positions=".$totalPositions."\n";
					print "long perc=".$longPerc."\n";
					print "short perc=".$shortPerc."\n";
					
					print "cot bias = $cotBias\n";
					
				} else {
					$cotBias = "none";
				}

				// because all COT reports are FOREIGN_USD
				// if pairToTrade is USD_FOREIGN, flip the bias variable
				$exp = explode("_", $pairToTrade);
				if ($exp[0] == "USD") {
					if ($cotBias == "up") { $bias = "down"; }
					else if ($cotBias == "down") { $bias = "up"; }
					else { $bias = "none"; }
				} else {
					$bias = $cotBias;
				}
								
				print "cotBias = $cotBias\n";
				print "bias = $bias\n";
				


				if ($bias != "none") {

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

					for ($i=$candleArrLastIdx; $i>=$candleArrLastIdx-$this->settings[$pairToTrade]['lookbackHours']; $i--) {
	
						if ($i == $candleArrLastIdx) {
							$open = $candleArr[$pairToTrade]->candles[$i]->openMid;
						} else if ($i == $candleArrLastIdx-$this->settings[$pairToTrade]['lookbackHours']) {
							$close = $candleArr[$pairToTrade]->candles[$i]->closeMid;
						}
	
						$candleSizeArr[] = $candleArr[$pairToTrade]->candles[$i]->highAsk - $candleArr[$pairToTrade]->candles[$i]->lowBid;
	
						if ($candleArr[$pairToTrade]->candles[$i]->lowBid < $min) {
							$min = $candleArr[$pairToTrade]->candles[$i]->lowBid;
						}
	
						if ($candleArr[$pairToTrade]->candles[$i]->highAsk > $max) {
							$max = $candleArr[$pairToTrade]->candles[$i]->highAsk;
						}
	
					}

					$range = $max - $min;
					$windowClosingRatio = ($candle->closeBid - $min) / ($max - $min);
	
					print "open = $open\n";
					print "close = $close\n";
					print "min = $min\n";
					print "max = $max\n";
					print "range = $range\n";


					if ($bias == "up") {
						if ($windowClosingRatio > $this->settings[$pairToTrade]['windowRatioThresholdBuyMin'] && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdBuyMax']) {
							$bias = "up";
						} else {
							$bias = "none";
						}
					} else if ($bias == "down") {
						if ($windowClosingRatio > $this->settings[$pairToTrade]['windowRatioThresholdSellMin'] && $windowClosingRatio < $this->settings[$pairToTrade]['windowRatioThresholdSellMax']) {
							$bias = "down";
						} else {
							$bias = "none";
						}
					}



					print "================================ $pairToTrade New Trades ==================================\n";

					if (count($trades->trades) == 0) {
						
						if ($bias == "down") {

							// short
							$TP = $candle->closeAsk - ($range * $this->settings[$pairToTrade]['tpMultiplier']);
							$SL = $candle->closeAsk + ($range * $this->settings[$pairToTrade]['slMultiplier']);
							
							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['ask'], $SL, "buy");
	
							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->sell_market($calcUnits, $pairToTrade, $rest);
	
							print "MARKET SELL $pairToTrade at ".$candle->closeBid."\n";


						} else if ($bias == "up") {
	
							// long
							$TP = $candle->closeBid + ($range * $this->settings[$pairToTrade]['tpMultiplier']);
							$SL = $candle->closeBid - ($range * $this->settings[$pairToTrade]['slMultiplier']);
	
							$calcUnits = $this->calculateUnits($pairToTrade, $this->settings[$pairToTrade]['acceptedLossPerTrade'], $NAV, $quote[$pairToTrade]['bid'], $SL, "sell");
					
							$rest = array("takeProfit" => $this->forex_round($pairToTrade, $TP), "stopLoss" => $this->forex_round($pairToTrade, $SL));
							$this->buy_market($calcUnits, $pairToTrade, $rest);
	
							print "MARKET BUY $pairToTrade at ".$candle->closeAsk."\n";
									
						} else {
							
							print "\n\nNo trades taken on $pairToTrade\n";
							// print_r($this->settings[$pairToTrade]);
							
						}
						
					
					}  // end: new trade

				}  // end: cot bias != none
	
			}  // end: check for new trade availability

			if ($didSomething === false) {
				print "NOTHING TO DO.\n";
			}

		}  // end: foreach currency basket loop



	}	// END: execute()
	




	/////////////////////////////////////////////////////////////////////////////////////
	// Get COT row for a specific pair and tick time.  If tick time null then use current tick time
	/////////////////////////////////////////////////////////////////////////////////////
	public function getCot($instrument, $tickTime=NULL)
	{
		if ($tickTime == NULL) {
			$tickTime = $this->getTickTime();
		}

		// $cotDate = date("Y-m-d", $tickTime);

		$query = "SELECT * FROM cot WHERE Market_and_Exchange_Names='".$this->cotName[$instrument]."' AND AvailableDateUnixtime <= '".$tickTime."' ORDER BY AvailableDateUnixtime DESC LIMIT 1";
		print "$query\n";
		$res = $this->cotDB->query($query);

		$row = $res->fetchArray(SQLITE3_ASSOC);
		
		return $row;
	}






	
}	// END: class

