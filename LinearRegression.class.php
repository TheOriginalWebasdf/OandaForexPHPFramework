<?php
// LinearRegression.class.php

class LinearRegression {

	private $xarr = array();
	private $yarr = array();
	private $npoints = 0;
	public $slope = NULL;
	public $intercept = NULL;

	// constructor
	function __construct($xarr = NULL, $yarr = NULL) {

		if ($xarr !== NULL && $yarr !== NULL) {
			$this->changeDataset($xarr, $yarr);
		}

	}
	
	
	// change the dataset
	public function changeDataset($xarr, $yarr)
	{
		if (is_array($xarr) && is_array($yarr) && count($xarr) == count($yarr) && count($xarr) > 0) {
			$this->xarr = $xarr;
			$this->yarr = $yarr;
			$this->npoints = count($xarr);
			$this->calculateRegressionLine();
		} else {
			$this->slope = NULL;
			$this->intercept = NULL;
		}
		
		return true;
	}



	/**
	 * Perform linear regression.
	 * @param array $xarr An array of $npoints floating-point values, comprising the X values of
	 * the data points.
	 * @param array $yarr An array of $npoints floating-point values, comprising the Y values of
	 * the data points.
	 * @param int $npoints The number of data points.
	 * @return object An object with slope and intercept floating point attributes.
	 */
	public function calculateRegressionLine() {
		if ($this->npoints < 2) {
			print "At least two data points are required for linear regression\n";
			return false;
		}

		$sumx = 0.0;
		$sumy = 0.0;
		$sumxy = 0.0;
		$sumxx = 0.0;
		$sumyy = 0.0;

		for ($i = 0; $i < $this->npoints; $i++) {
			$x = $this->xarr[$i];
			$y = $this->yarr[$i];

			$sumx += $x;
			$sumy += $y;
			$sumxy += $x*$y;
			$sumxx += $x*$x;
			$sumyy += $y*$y;
		}

		$denominator = (($this->npoints*$sumxx)-($sumx*$sumx));

		$this->slope = (($this->npoints*$sumxy)-($sumx*$sumy)) / $denominator;
		$this->intercept = (($sumy*$sumxx)-($sumx*$sumxy)) / $denominator;
		return true;
	}


	// calculate the standard error of estimate
	public function standardErrorOfEstimate()
	{
		// formula 12-6 - p.442
		
		if ($this->slope === NULL || $this->intercept === NULL) {
			return false;
		}
		
		$numerator = 0;
		for ($i=0; $i<$this->npoints; $i++) {
			$numerator += pow(($this->yarr[$i] - $this->calculateYPrime($this->xarr[$i])), 2);
		}
		
		return sqrt($numerator / ($this->npoints - 2));
	}
	
	
	// coefficient of determination
	// r squared
	public function coefficentOfDetermination()
	{
		// formula 12-10 - p.452

		if ($this->slope === NULL || $this->intercept === NULL) {
			return false;
		}

		$Ybar = $this->mean($this->yarr);
		
		// total variation
		$totalVariation = 0;
		for ($i=0; $i<$this->npoints; $i++) {
			$totalVariation += pow(($this->yarr[$i] - $Ybar), 2);
		}
		
		// unexplained variation
		$unexplainedVariation = 0;
		for ($i=0; $i<$this->npoints; $i++) {
			$unexplainedVariation += pow(($this->yarr[$i] - $this->calculateYPrime($this->xarr[$i])), 2);
		}
		
		return ($totalVariation - $unexplainedVariation) / $totalVariation;
	}
	
	
	
	// calculate Y-prime (the predicted value of Y for a given value X)
	public function calculateYPrime($X)
	{
		// formula 12-3 - p.437

		if ($this->slope === NULL || $this->intercept === NULL) {
			return false;
		}
		
		// Y-prime = intercept + slope * X
		return $this->intercept + ($this->slope * $X);
	}
	
	
	public function calculatePredictedY($X) {
		return $this->calculateYPrime($X);
	}
	
	
	
	// calculate mean of an array's values
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
	


	/**
	 * Calculate the sum of the squared error residuals between a set of [x,y] data points and a
	 * line expressed using the line formula <tt>(y = mx + b)</tt>.
	 * @param array $xarr An array of $npoints floating-point values, comprising the X values of
	 * the data points.
	 * @param array $yarr An array of $npoints floating-point values, comprising the Y values of
	 * the data points.
	 * @param int $npoints The number of data points.
	 * @param double $slope The slope (m) value for the line formula <tt>(y = mx + b)</tt>.
	 * @param double $intercept The intercept (b) value for the line formula <tt>(y = mx + b)</tt>.
	 * @return double The sum of the squared error residuals.
	 */
	public function calcSquaredError($xarr, $yarr, $npoints, $slope, $intercept) {

		if ($this->slope === NULL || $this->intercept === NULL) {
			return false;
		}

		$sqrerr = 0.0;
		for ($i = 0; $i < $this->npoints; $i++) {
			$predy = ($this->slope*$this->xarr[$i])+$this->intercept;
			$err = $predy-$this->yarr[$i];
			$sqrerr += ($err*$err);
		}
		
		return $sqrerr;
	}
	

	
}
