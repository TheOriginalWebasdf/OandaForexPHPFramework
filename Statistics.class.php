<?php
// Statistics.class.php

class Statistics {

	// constructor
	function __construct() {

	}


	/////////////////////////////////////////////////////////////////
	// calculate percentile w/in a numeric array based on the sampleVal
	// what percentile does the $checkVal fall into?
	/////////////////////////////////////////////////////////////////
	public function percentile($arr, $checkVal)
	{
		$arraySize = count($arr);
		sort($arr);
		
		$pIdx = 0;
		
		foreach ($arr as $idx=>$val) {
			if ($val >= $checkVal) {
				$pIdx = $idx + 1;
				break;
			}
		}
		
		if ($arraySize < 1) { return 0; }
		else { return $pIdx / $arraySize; }
	}


	
	
	/////////////////////////////////////////////////////////////////
	// return mean value of an array of values
	/////////////////////////////////////////////////////////////////
	public function mean($arr)
	{
		if (!is_array($arr)) return 0;
	
		$count = count($arr);
		
		if ($count > 0) {
			return array_sum($arr) / $count;
		} else {
			return 0;
		}

	}
	
	
	
	/////////////////////////////////////////////////////////////////
	// return median value of an array of values
	/////////////////////////////////////////////////////////////////
	public function median($arr)
	{
		if (!is_array($arr)) return 0;
	
		$count = count($arr);
		
		sort ($arr, SORT_NUMERIC);
		
		$middleIdx = (count($arr) - 1)/ 2;
		
		// if middleIdx is an integer, return that array's value
		if (is_integer($middleIdx)) {
			return $arr[$middleIdx];
		} else {
			$idx = floor($middleIdx);
			return ($arr[$idx] + $arr[$idx+1]) / 2;
		}
		
	}



	/////////////////////////////////////////////////////////////////
	// return skew of an array of values
	/////////////////////////////////////////////////////////////////
	public function skew($arr)
	{
		$mean = $this->mean($arr);
		$median = $this->median($arr);
		$stddev = $this->standardDeviation($arr);
		
		if ($stddev != 0) {
			return (3 * ($mean - $median)) / $stddev;
		} else {
			return 0;
		}
	}
	
	
	
	/**
	* Calculate variance of array
	* @param (array) $aValues
	*@return float
	*/
	public function variance($aValues, $bSample = false){
		$fMean = $this->mean($aValues);
		$fVariance = 0.0;
		
		foreach ($aValues as $i)
		{
			$fVariance += pow($i - $fMean, 2);
		}

		$fVariance /= ( $bSample ? count($aValues) - 1 : count($aValues) );
		return $fVariance;
	}


	
	
	/**
	* Calculate standard deviation of array, by definition it is square root of variance
	* @param (array) $aValues
	* @return float
	*/
	public function standardDeviation($aValues, $bSample = false)
	{
		$fVariance = $this->variance($aValues, $bSample);
		return (float) sqrt($fVariance);
	}

	
	
}
