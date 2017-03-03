#!/usr/bin/php
<?php
require_once __DIR__."/../../Fx.class.php";


$argv = array_slice($argv, 1);

if (count($argv) < 1) {

	print "REQUIRED PARAMETER: [file name to parse...ie FinComYY.csv]\n";
	exit;
} else {
	$fileToParse = $argv[0];
	print "fileToParse = $fileToParse\n";
}


if (!file_exists("cot-history.sl3")) {
	$createTable = true;
} else {
	$createTable = false;
}


// if sqlite db doesn't exist then create it
$db = new SQLite3("cot-history.sl3");

if ($createTable === true) {
	
	$query = "CREATE TABLE IF NOT EXISTS cot (
		AvailableDate TEXT,
		AvailableDateUnixtime INT,
		Market_and_Exchange_Names TEXT,
		As_of_Date_In_Form_YYMMDD TEXT,
		Report_Date_as_YYYY_MM_DD TEXT,
		CFTC_Contract_Market_Code INT,
		CFTC_Market_Code TEXT,
		CFTC_Region_Code INT,
		CFTC_Commodity_Code INT,
		Open_Interest_All INT,
		Dealer_Positions_Long_All INT,
		Dealer_Positions_Short_All INT,
		Dealer_Positions_Spread_All INT,
		Asset_Mgr_Positions_Long_All INT,
		Asset_Mgr_Positions_Short_All INT,
		Asset_Mgr_Positions_Spread_All INT,
		Lev_Money_Positions_Long_All INT,
		Lev_Money_Positions_Short_All INT,
		Lev_Money_Positions_Spread_All INT,
		Other_Rept_Positions_Long_All INT,
		Other_Rept_Positions_Short_All INT,
		Other_Rept_Positions_Spread_All INT,
		Tot_Rept_Positions_Long_All INT,
		Tot_Rept_Positions_Short_All INT,
		NonRept_Positions_Long_All INT,
		NonRept_Positions_Short_All INT,
		Change_in_Open_Interest_All INT,
		Change_in_Dealer_Long_All INT,
		Change_in_Dealer_Short_All INT,
		Change_in_Dealer_Spread_All INT,
		Change_in_Asset_Mgr_Long_All INT,
		Change_in_Asset_Mgr_Short_All INT,
		Change_in_Asset_Mgr_Spread_All INT,
		Change_in_Lev_Money_Long_All INT,
		Change_in_Lev_Money_Short_All INT,
		Change_in_Lev_Money_Spread_All INT,
		Change_in_Other_Rept_Long_All INT,
		Change_in_Other_Rept_Short_All INT,
		Change_in_Other_Rept_Spread_All INT,
		Change_in_Tot_Rept_Long_All INT,
		Change_in_Tot_Rept_Short_All INT,
		Change_in_NonRept_Long_All INT,
		Change_in_NonRept_Short_All INT,
		Pct_of_Open_Interest_All REAL,
		Pct_of_OI_Dealer_Long_All REAL,
		Pct_of_OI_Dealer_Short_All REAL,
		Pct_of_OI_Dealer_Spread_All REAL,
		Pct_of_OI_Asset_Mgr_Long_All REAL,
		Pct_of_OI_Asset_Mgr_Short_All REAL,
		Pct_of_OI_Asset_Mgr_Spread_All REAL,
		Pct_of_OI_Lev_Money_Long_All REAL,
		Pct_of_OI_Lev_Money_Short_All REAL,
		Pct_of_OI_Lev_Money_Spread_All REAL,
		Pct_of_OI_Other_Rept_Long_All REAL,
		Pct_of_OI_Other_Rept_Short_All REAL,
		Pct_of_OI_Other_Rept_Spread_All REAL,
		Pct_of_OI_Tot_Rept_Long_All REAL,
		Pct_of_OI_Tot_Rept_Short_All REAL,
		Pct_of_OI_NonRept_Long_All REAL,
		Pct_of_OI_NonRept_Short_All REAL,
		Traders_Tot_All INT,
		Traders_Dealer_Long_All INT,
		Traders_Dealer_Short_All INT,
		Traders_Dealer_Spread_All INT,
		Traders_Asset_Mgr_Long_All INT,
		Traders_Asset_Mgr_Short_All INT,
		Traders_Asset_Mgr_Spread_All INT,
		Traders_Lev_Money_Long_All INT,
		Traders_Lev_Money_Short_All INT,
		Traders_Lev_Money_Spread_All INT,
		Traders_Other_Rept_Long_All INT,
		Traders_Other_Rept_Short_All INT,
		Traders_Other_Rept_Spread_All INT,
		Traders_Tot_Rept_Long_All INT,
		Traders_Tot_Rept_Short_All INT,
		Conc_Gross_LE_4_TDR_Long_All REAL,
		Conc_Gross_LE_4_TDR_Short_All REAL,
		Conc_Gross_LE_8_TDR_Long_All REAL,
		Conc_Gross_LE_8_TDR_Short_All REAL,
		Conc_Net_LE_4_TDR_Long_All REAL,
		Conc_Net_LE_4_TDR_Short_All REAL,
		Conc_Net_LE_8_TDR_Long_All REAL,
		Conc_Net_LE_8_TDR_Short_All REAL,
		Contract_Units TEXT,
		CFTC_Contract_Market_Code_Quotes TEXT,
		CFTC_Market_Code_Quotes TEXT,
		CFTC_Commodity_Code_Quotes TEXT,
		CFTC_SubGroup_Code TEXT,
		FutOnly_or_Combined TEXT
	)";

	$db->exec($query);

	$query = "CREATE INDEX IF NOT EXISTS market ON cot(Market_and_Exchange_Names)";
	$db->exec($query);

	$query = "CREATE INDEX IF NOT EXISTS reportDate ON cot(Report_Date_as_YYYY_MM_DD)";
	$db->exec($query);

	$query = "CREATE INDEX IF NOT EXISTS availableDate ON cot(AvailableDate)";
	$db->exec($query);

	$query = "CREATE INDEX IF NOT EXISTS availableDateUnixtime ON cot(AvailableDateUnixtime)";
	$db->exec($query);



}



$fHandle = fopen($fileToParse, "r") or die("Unable to open file!");

while(!feof($fHandle)) {
	$line = fgets($fHandle);	
	$arr = str_getcsv ($line);
	
	if (count($arr) < 2) {
		continue;
	}
	// print_r($arr);
	
//	print "time = ".trim($arr[2])."\n";

	// add 4 days to the date on the csv file so we know when the data became available
	$availableDateUnixtime = strtotime(trim($arr[2]));

	if ($availableDateUnixtime != "") {

		$availableDateUnixtime += 86400*5;
		$availableDate = date("Y-m-d", $availableDateUnixtime);
		//print "availableDate = $availableDate\n";
		//print "availableDate = ".."\n";
		//exit;

	} else {
		continue;
	}


	$query = "DELETE FROM cot WHERE Market_and_Exchange_Names='".trim($arr[0])."' AND Report_Date_as_YYYY_MM_DD='".trim($arr[2])."'";
	$db->exec($query);
	
	// surround each array element with single quotes
	foreach ($arr as $idx=>$a) {
		$arr[$idx] = "'".trim($a)."'";
	}
	
	$query  = "INSERT INTO cot VALUES (";
	$query .= "'".$availableDate."', ";
	$query .= "'".$availableDateUnixtime."', ";
	$query .= implode(", ", $arr);
	$query .= ")";
	
	print $query."\n\n";
	$db->exec($query);

}

fclose($fHandle);



