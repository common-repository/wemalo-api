<?php

/**
 * used for throwing specific api exceptions
 *
 * @author Patric Eid
 */
class WemaloAPIException extends Exception {
	private int $return_code;

	function __construct( $message, $return_code = 500 ) {
		$this->return_code = $return_code;

		parent::__construct( $message );
	}

	/**
	 * returns code
	 *
	 * @return number
	 */
	public function get_return_code(): int {
		return $this->return_code;
	}
}
