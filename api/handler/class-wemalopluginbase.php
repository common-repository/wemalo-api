<?php

/**
 * base handler class
 *
 * @author Patric Eid
 */
class WemaloPluginBase {

	protected string $wemalo_auth_key = 'wemalo-auth-key';

	/**
	 * returns the wemalo authentication key from the options table
	 *
	 * @return string wemalo token from option table
	 */
	protected function get_wemalo_authentication(): string {
		return get_option( $this->wemalo_auth_key );
	}

	/**
	 * gets current date
	 *
	 * @param string $format
	 * @return string
	 * @throws Exception
	 */
	public function get_current_date( string $format = 'Y-m-d H:i:s' ): string {
		$timezone     = 'Europe/Berlin';
		$userTimezone = new DateTimeZone( $timezone );
		$gmtTimezone  = new DateTimeZone( 'GMT' );
		$myDateTime   = new DateTime( date( 'r' ), $gmtTimezone );
		$offset       = $userTimezone->getOffset( $myDateTime );

		return date( $format, (int) $myDateTime->format( 'U' ) + $offset );
	}

	/**
	 * returns true if a string (<code>$haystack</code>) contains a
	 * substring (<code>$needle</code>)
	 *
	 * @param string  $haystack
	 * @param string  $needle
	 * @param boolean $case
	 * @return boolean
	 */
	protected function starts_with( string $haystack, string $needle, bool $case = true ): bool {
		if ( $case ) {
			return str_starts_with( $haystack, $needle );
		}
		return stripos( $haystack, $needle, 0 ) === 0;
	}

	/**
	 * tries getting a value from an array and if the array key doesn't exist, the default
	 * value will be returned
	 *
	 * @param mixed $key
	 * @param array $array
	 * @param null  $default
	 * @return mixed
	 */
	protected function get_value( mixed $key, mixed $array, mixed $default = null ): mixed {
		try {
			if ( is_array( $array ) ) {
				if ( isset( $array[ $key ] ) ) {
					$tmp = $array[ $key ];
					return ( is_array( $tmp ) ? $tmp[0] : $tmp );
				}
			} elseif ( is_object( $array ) ) {
				return $array->$key ?? $default;
			}
		} catch ( Exception $e ) {
			return $default;
		}

		return $default;
	}

	/**
	 * make timestamp compatible to wp_query
	 *
	 * @param string $time_stamp
	 * @return array string multi-type:string
	 */
	protected function create_date_query( string $time_stamp ): array {
		return array(
			'column' => 'post_modified_gmt',
			'after'  => array(
				'year'   => date( 'Y', $time_stamp ),
				'month'  => date( 'm', $time_stamp ),
				'day'    => date( 'd', $time_stamp ),
				'hour'   => date( 'H', $time_stamp ),
				'minute' => date( 'i', $time_stamp ),
				'second' => date( 's', $time_stamp ),
			),
		);
	}

	/**
	 * builds an array for returning an error message
	 *
	 * @param int    $error_code
	 * @param string $error_message
	 * @return array
	 */
	protected function return_error( int $error_code, string $error_message ): array {
		return array(
			'Error'   => $error_code,
			'Message' => $error_message,
		);
	}
}
