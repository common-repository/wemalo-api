<?php

/**
 * holds status key and value
 * @author Patric Eid
 *
 */
class WemaloAPIOrderStatus {
	private string $key;
	private string $value;
	
	/**
	 * creates a new object
	 * @param string $key
	 * @param string $value
	 * @return WemaloAPIOrderStatus
	 */
	public static function create(string $key, string $value): WemaloAPIOrderStatus
    {
		$tmp = new WemaloAPIOrderStatus();
		$tmp->setValue($value);
		$tmp->setKey($key);
		return $tmp;
	} 
	
	/**
	 * returns the key
	 * @return string
	 */
	public function getKey(): string
    {
		return $this->key;
	}
	
	/**
	 * returns the value
	 * @return string
	 */
	public function getValue(): string
    {
		return $this->value;
	}
	
	/**
	 * sets the key
	 * @param string $key
	 */
	public function setKey(string $key) {
		$this->key = $key;
	}
	
	/**
	 * sets the value
	 * @param string $value
	 */
	public function setValue(string $value) {
		$this->value = $value;
	}
}