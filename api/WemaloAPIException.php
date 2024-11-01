<?php

use JetBrains\PhpStorm\Pure;

/**
 * used for throwing specific api exceptions
 * @author Patric Eid
 *
 */
class WemaloAPIException extends Exception {
	private int $returnCode = 500;
	
	#[Pure] function __construct($message, $returnCode) {
		parent::__construct($message);
		$this->returnCode = $returnCode;
	}
	
	/**
	 * returns code
	 * @return number
	 */
	public function getReturnCode(): int
    {
		return $this->returnCode;
	}
}