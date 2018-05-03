<?php
include_once (__DIR__."/supernova-s.class.php");
date_default_timezone_set("America/Chicago");
//error_reporting(0);

$btLogBasename = substr(basename(__FILE__), 0, -4);


$configArr = array(
	"btStartTickTime" => mktime(17, 0, 0, 1, 1, 2013),
	"btEndTickTime" => mktime(17, 0, 0, 1, 1, 2018),

	"btLeverage" => 50,
	"riskResultFilename" => $btLogBasename."-risk-results.txt",

	"btAccountId" => "111111",
	"btAccountName" => "myAccount",
	"btOpeningBalance" => 100,
	"btLogFile" => __DIR__."/$btLogBasename.log",
	"btStatsFile" => __DIR__."/$btLogBasename.stats.csv",
	"btCorrelationFile" => __DIR__."/$btLogBasename.correlation.csv",

	"oandaApiKey" => DEMO_API_KEY,
	"oandaAccountId" => 7897447,
);



$b = new Supernova("Demo", $configArr);

print "\n\n\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print "*************************************************************************\n";
print date("c")."\n\n\n";

print "Sleeping 10 seconds before beginning program logic\n";
sleep(10);


$b->execute();




