<?php

/**
 * holds status key and value
 *
 * @author Patric Eid
 */
class WemaloAPIOrderStatus {
	private string $key;
	private string $value;

	/**
	 * Creates a new object
	 *
	 * @param string $key
	 * @param string $value
	 * @return WemaloAPIOrderStatus
	 */
	public static function create( string $key, string $value ): WemaloAPIOrderStatus {
		$tmp = new WemaloAPIOrderStatus();
		$tmp->set_value( $value );
		$tmp->set_key( $key );
		return $tmp;
	}

	/**
	 * Returns the key
	 *
	 * @return string
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Returns the value
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Sets the key
	 *
	 * @param string $key
	 */
	public function set_key( string $key ) {
		$this->key = $key;
	}

	/**
	 * Sets the value
	 *
	 * @param string $value
	 */
	public function set_value( string $value ) {
		$this->value = $value;
	}
}
