#!/usr/bin/php
<?php
require_once (__DIR__."/../Fx.class.php");

$pair = "EUR_USD";
$granularity = "H1";

$argv = array_slice($argv, 1);
// print_r($argv);

if (count($argv) < 1) {

	print "pair=[pair to download...default EUR_USD]\n";
	print "granularity=[granularity...default H1]\n";
	print "start=[unixtime start] OR YEAR[year]\n";

	exit;
}



foreach ($argv as $a) {
	$p = explode("=", $a);
	if (count($p) != 2) { print "parameter error.\n"; exit; }
	
	if ($p[0] == "pair") { $pair = $p[1]; }
	if ($p[0] == "granularity") { $granularity = $p[1]; }
	if ($p[0] == "start") { $start = $p[1]; }
}





// if sqlite db doesn't exist then create it
$db = new SQLite3("candles-".$granularity.".db");

$query = "CREATE TABLE IF NOT EXISTS candles (
	instrument VARCHAR(7),
	granularity VARCHAR(3),
	time INT,
	openBid FLOAT,
	openAsk FLOAT,
	highBid FLOAT,
	highAsk FLOAT,
	lowBid FLOAT,
	lowAsk FLOAT,
	closeBid FLOAT,
	closeAsk FLOAT,
	volume INT,
	complete INT
)";

$db->exec($query);

$query = "CREATE INDEX IF NOT EXISTS instrument ON candles(instrument)";
$db->exec($query);

$query = "CREATE INDEX IF NOT EXISTS time ON candles(time)";
$db->exec($query);

$query = "CREATE INDEX IF NOT EXISTS insTime ON candles(instrument,time)";
$db->exec($query);


if (!isset($start)) {

	// check for latest candle in db that is not complete.
	$query = "SELECT time FROM candles WHERE instrument = '".$pair."' AND complete <> '1'";
	$res = $db->query($query);
	$row = $res->fetchArray(SQLITE3_ASSOC);

	if ($row['time'] != "") {
		// if found, start download of candles there.
		$start = $row['time'];

	} else {

		// if NOT found, default the year and start time to 1/1/2015
		$year = 2015;
		print "year=$year\n";
		$start = mktime(0, 0, 0, 1, 1, $year);
		
	}
	
} else if (isset($start) && strpos($start, "YEAR") !== false) {

	$year = filter_var($start, FILTER_SANITIZE_NUMBER_INT);
	print "year=$year\n";
	$start = mktime(0, 0, 0, 1, 1, $year);
	
}

print "start=$start\n";
print "start=".date("c", $start)."\n\n";





$fx = new Fx("Live");


$fx->oandaApiKey = LIVE_API_KEY;
$fx->oandaAccountId = 188590;

$fx->configureAccount();


// get 1000 candles at a time
$candlesAtATime = 1000;


// get candle history from oanda api
do {

	$oRest = array("count"=>$candlesAtATime, "alignmentTimezone"=>"America/Chicago", "start"=>$start);

	$oGran = $granularity;
	$oCandleFormat = "bidask";

	$candleArr = $fx->candles($pair, $oGran, $oRest, $oCandleFormat);


	// insert data into sqlite db
	foreach ($candleArr->candles as $idx=>$c) {
		// delete existing candles for this pair
		$query = "DELETE FROM candles WHERE instrument='".$pair."' AND time='".$c->time."'";
		$db->exec($query);


		$query = "INSERT INTO candles
					(instrument,
					granularity,
					time,
					openBid, openAsk,
					highBid, highAsk,
					lowBid, lowAsk,
					closeBid, closeAsk,
					volume,
					complete)
				VALUES
					('".$pair."',
					'".$granularity."',
					'".$c->time."',
					'".$c->openBid."', '".$c->openAsk."',
					'".$c->highBid."', '".$c->highAsk."',
					'".$c->lowBid."', '".$c->lowAsk."',
					'".$c->closeBid."', '".$c->closeAsk."',
					'".$c->volume."',
					'".$c->complete."'
					)";
		$db->exec($query);
		
		print $pair."\t".date("c", $c->time)."\topenBid=".$c->openBid."\tcloseBid=".$c->closeBid."\n";

	}


	$c = end($candleArr->candles);
	$start = $c->time;
	
	print "start=".$c->time."\n";
	print "complete=".$c->complete."\n";
	print "count candles=".count($candleArr->candles)."\n";

} while ($c->complete == 1 && count($candleArr->candles) == $candlesAtATime);







exit;










