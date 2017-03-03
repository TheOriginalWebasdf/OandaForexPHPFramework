<?php
/*
This method is a day trade strategy for eurusd
Stars at 7:00am central time with a sample of the last 7 hrs (covering the london session)

- Analyze existing trades
At 3pm central time close all open trades


- Determine new trade (at 7am only)
if no trades open
	Get min and max of sample (7 hrs)
	
	If more positive bars than negative bars, go long (TP=current quote + (max - min); SL=current quote - ((max - min) * .5))
	Else go short (TP=current quote - (max - min); SL=current quote + ((max - min) * .5))



*/


include_once (__DIR__."/bigBen-h.class.php");
//error_reporting(0);


$btLogBasename = substr(basename(__FILE__), 0, -4);


$configArr = array(
	//"btStartTickTime" => POST_BREXIT,
	"btStartTickTime" => YEAR_2016,

	"btEndTickTime" => YEAR_2018,

	"btLeverage" => 30,
	"riskResultFilename" => "h-risk-results.txt",

	"btAccountId" => "111111",
	"btAccountName" => "myAccount",
	"btOpeningBalance" => 100,
	"btLogFile" => __DIR__."/$btLogBasename.log",
	"btStatsFile" => __DIR__."/$btLogBasename.stats.csv",
	"btCorrelationFile" => __DIR__."/$btLogBasename.correlation.csv",

	"oandaApiKey" => DEMO_API_KEY,
	"oandaAccountId" => 8637930,
);





$b = new BigBenH("Backtest", $configArr);
$b->btRiskResultFileStart();

print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";
print_r($b->acctObj->accountInfo());


while ($b->acctObj->tick()) {

	$b->execute();

}	// end: tick loop



print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";

$b->btRiskResultFileEnd();
