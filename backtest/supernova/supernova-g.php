<?php
/*


*/


include_once (__DIR__."/supernova-g.class.php");
//error_reporting(0);


$btLogBasename = substr(basename(__FILE__), 0, -4);


$configArr = array(
	"btStartTickTime" => YEAR_2016 + 86400 * 14,
	// "btStartTickTime" => 1473025200,
	// "btStartTickTime" => mktime(17, 0, 0, 1, 10, 2016),
	"btEndTickTime" => YEAR_2017,

	"btLeverage" => 50,
	"riskResultFilename" => $btLogBasename."-risk-results.txt",

	"btAccountId" => "111111",
	"btAccountName" => "myAccount",
	"btOpeningBalance" => 100,
	"btLogFile" => __DIR__."/$btLogBasename.log",
	"btStatsFile" => __DIR__."/$btLogBasename.stats.csv",
	"btCorrelationFile" => __DIR__."/$btLogBasename.correlation.csv",

	"oandaApiKey" => LIVE_API_KEY,
	"oandaAccountId" => 994721,
);





$b = new Supernova("Backtest", $configArr);
$b->btRiskResultFileStart();

print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";
print_r($b->acctObj->accountInfo());


while ($b->acctObj->tick()) {

	$b->execute();

}	// end: tick loop



print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";

$b->btRiskResultFileEnd();
