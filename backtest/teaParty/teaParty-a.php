<?php
/*


*/


include_once (__DIR__."/teaParty-a.class.php");
//error_reporting(0);


$btLogBasename = substr(basename(__FILE__), 0, -4);


$configArr = array(
	"btStartTickTime" => YEAR_2014,
	"btEndTickTime" => YEAR_2018,

	"btLeverage" => 30,
	"riskResultFilename" => $btLogBasename."-risk-results.txt",

	"btAccountId" => "111111",
	"btAccountName" => "myAccount",
	"btOpeningBalance" => 100,
	"btLogFile" => __DIR__."/$btLogBasename.log",
	"btStatsFile" => __DIR__."/$btLogBasename.stats.csv",
	"btCorrelationFile" => __DIR__."/$btLogBasename.correlation.csv",


	"oandaApiKey" => DEMO_API_KEY,
	"oandaAccountId" => 994721,
);





$b = new TeaParty("Backtest", $configArr);
$b->btRiskResultFileStart();

print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";
print_r($b->acctObj->accountInfo());


while ($b->acctObj->tick()) {

	$b->execute();

}	// end: tick loop



print $b->getTickTime()."\t".date("r", $b->getTickTime())."\n\n";

$b->btRiskResultFileEnd();
