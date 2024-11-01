<?php

require_once __DIR__ . '/class-wemaloapiexception.php';
require_once __DIR__ . '/class-wemaloapibasic.php';
require_once __DIR__ . '/handler/class-wemalopluginproducts.php';
require_once __DIR__ . '/handler/class-wemalopluginorders.php';

/**
 * contains function for connecting against wemalo-connect api
 *
 * @author Patric Eid
 */
class WemaloAPIConnect extends WemaloAPIBasic {

	private string $token              = '';
	private string $shop               = '';
	private bool $debug_mode           = false;
	private bool $order_in_fulfillment = false;
	private array $webhooks            = array();

	function __construct() {
		$this->token = get_option( 'wemalo_plugin_auth_key' );
		$this->shop  = get_option( 'wemalo_plugin_shop_name' );

		$this->webhooks['status_update']        = get_home_url() . '/?rest_route=/rest/v1/wemalo/order/hook/status/';
		$this->webhooks['trackingnumber_added'] = get_home_url() . '/?rest_route=/rest/v1/wemalo/order/hook/track/';

		$this->load_shop_name();
	}

	/**
	 * returns true if last submitted order has been changed to fulfillment
	 *
	 * @return boolean
	 */
	public function is_order_in_fulfillment(): bool {
		return $this->order_in_fulfillment;
	}

	/**
	 * loads shopname from connect
	 *
	 * @return void
	 */
	private function load_shop_name(): void {
		if ( $this->shop === '' && $this->token !== '' ) {
			// load shop name from wemalo-connect
			$result = $this->_GET( 'product/shop' );
			if ( ! empty( $result ) && isset( $result->shop ) ) {
				$this->shop = $result->shop;
				add_option( 'wemalo_plugin_shop_name', $this->shop );
			}
		}
	}

	public function register_webhooks(): string {
		$token = '';
		foreach ( $this->webhooks as $key => $payload ) {
			$token = $this->register_hook( $key, $payload, $token );
			sleep( 1 );
		}

		if ( ! add_option( 'wemalo_hook_token', $token ) ) {
			update_option( 'wemalo_hook_token', $token );
		}

		return is_string( $token ) ? $token : '';
	}

	/**
	 *
	 */
	public function unregister_webhooks() {
		foreach ( $this->webhooks as $key => $payload ) {
			$this->unregister_hook( $key );
		}

		delete_option( 'wemalo_hook_token' );
	}

	/**
	 * Check all webhooks on system and check if they are already added to wemalo,
	 * if not, they will be added manually
	 *
	 * @param bool $new_token
	 */
	private function check_webhooks( bool $new_token = false ) {
		if ( ! $new_token ) {
			if ( get_option( 'wemalo_hook_token' ) ) {
				$hooks  = $this->get_registered_webhooks();
				$events = array();
				$tokens = array();
				if ( isset( $hooks->hooks ) && $hooks->hooks ) {
					foreach ( $hooks->hooks as $hook ) {
						$events[]               = $hook->event;
						$tokens[ $hook->event ] = $hook->token;
					}
				}

				foreach ( $this->webhooks as $key => $payload ) {
					if ( in_array( $key, $events ) ) {
						if ( $tokens[ $key ] !== get_option( 'wemalo_hook_token' ) ) {
							$this->register_hook( $key, $payload, get_option( 'wemalo_hook_token' ) );
						}
					} else {
						$this->register_hook( $key, $payload, get_option( 'wemalo_hook_token' ) );
					}
				}
			} else {
				$this->register_webhooks();
			}
		} else {
			delete_option( 'wemalo_hook_token' );
			$this->register_webhooks();
		}
	}

	/**
	 * @param bool $new_token
	 */
	public function update_webhooks( bool $new_token = false ) {
		$this->check_webhooks( $new_token );
	}

	/**
	 * registers a webhook
	 *
	 * @param string       $event_name
	 * @param string       $payload
	 * @param string token
	 * @return string|boolean
	 */
	public function register_hook( string $event_name, string $payload, string $token = '' ): bool|string {
		if ( $this->shop !== '' ) {
			$post_data            = array();
			$post_data['payload'] = $payload;
			if ( $token === '' ) {
				$token = $this->shop . rand( 10, 100 ) . rand( 10, 100 ) . rand( 10, 100 );
			}
			$post_data['token'] = $token;
			$this->_POST( 'webhook/' . $event_name, $post_data );

			return (string) $post_data['token'];
		} else {
			return false;
		}
	}

	/**
	 * unregister a wemalo connect hook
	 *
	 * @param string $event_name
	 */
	public function unregister_hook( string $event_name ) {
		$post_data = array();
		$this->_DELETE( 'webhook/' . $event_name, $post_data );
	}

	/**
	 * builds a url
	 *
	 * @param string $serviceName
	 * @return string
	 */
	private function build_url( string $serviceName ): string {
		$baseUrl = $this->debug_mode ? 'http://4rhfgdvmilyvsekp.myfritz.net:3700' : 'https://connect.wemalo.com';
		return $baseUrl . '/v1/' . $serviceName;
	}

	/**
	 * Check the header and will wait if x-wemalo-limit-remainingcall is near to 0
	 *
	 * @param mixed $response response of HTTP Request
	 * @param int   $seconds how long do I have to wait
	 */
	private function wait_limit_call( mixed $response, int $seconds = 0 ) {
		if ( ! is_wp_error( $response ) ) {
			$headers = $response['headers']->getAll();
			if ( array_key_exists( 'x-wemalo-limit-maxcall', $headers ) && array_key_exists( 'x-wemalo-limit-remainingcall', $headers ) ) {
				$limit_remaining_call = intval( $headers['x-wemalo-limit-remainingcall'] );

				if ( $limit_remaining_call <= 0 ) {
					sleep( $seconds );
				}
			}
		}
	}

	/**
	 * executes a get call
	 *
	 * @param string $serviceName
	 * @return stdClass
	 */
	private function _GET( string $serviceName ): stdClass {
		return $this->set_return(
			wp_remote_get(
				$this->build_url( $serviceName ),
				array(
					'headers' => $this->get_header(),
					'method'  => 'GET',
					'timeout' => 60,
				)
			)
		);
	}

	/**
	 * executes a POST call
	 *
	 * @param string $serviceName
	 * @param array  $post_data
	 * @return stdClass
	 */
	private function _POST( string $serviceName, array $post_data ): stdClass {
		return $this->set_return(
			wp_remote_post(
				$this->build_url( $serviceName ),
				array(
					'headers' => $this->get_header(),
					'body'    => wp_json_encode( $post_data ),
					'method'  => 'POST',
					'timeout' => 60,
				)
			)
		);
	}

	/**
	 * executes a delete call
	 *
	 * @param string $serviceName
	 * @param array  $post_data
	 * @return stdClass
	 */
	private function _DELETE( string $serviceName, array $post_data ): stdClass {
		return $this->set_return(
			wp_remote_post(
				$this->build_url( $serviceName ),
				array(
					'headers' => $this->get_header(),
					'body'    => wp_json_encode( $post_data ),
					'method'  => 'DELETE',
					'timeout' => 60,
				)
			)
		);
	}

	/**
	 * executes a put call
	 *
	 * @param string $serviceName
	 * @param array  $post_data
	 * @return stdClass
	 */
	private function _PUT( string $serviceName, array $post_data ): stdClass {
		return $this->set_return(
			wp_remote_request(
				$this->build_url( $serviceName ),
				array(
					'headers' => $this->get_header(),
					'body'    => wp_json_encode( $post_data ),
					'method'  => 'PUT',
					'timeout' => 60,
				)
			)
		);
	}

	/**
	 * returns headers array
	 *
	 * @return array
	 */
	private function get_header(): array {
		return array(
			'content-type'          => 'application/json',
			'authorization'         => 'JWT ' . $this->token,
			'wemalo-plugin-version' => '',
		);
	}

	/**
	 * evaluates call response and returns an array with response information
	 *
	 * @param  $response
	 * @return stdClass
	 */
	private function set_return( $response ): stdClass {
		$this->wait_limit_call( $response, 3 );

        $ret          = new stdClass();
        if ( is_wp_error( $response ) ) {
			$ret->status  = 500;
			$ret->message = $response->get_error_message();
        } else {
            $ret->status  = 200;
			$return =  json_decode( $response['body'] );
            if(!is_object($return)){
                $ret->message = $return;
            }else{
                $ret = $return;
            }
        }
        return $ret;
    }

	/**
	 * returns shop name loaded from wemalo-connect
	 *
	 * @return string
	 */
	public function get_shop_name(): string {
		return $this->shop;
	}

	/**
	 * checks whether the post type is supported and should be transmitted to wemalo
	 *
	 * @param WP_POST $post
	 * @return string
	 * @throws Exception
	 */
	public function transmit_product_data( WP_POST $post ): string {
		$productHandler = new WemaloPluginProducts();
		if ( $productHandler->handle_post_type( $post->post_type ) ) {
			$ret = $this->transmit_product( $post );
			if ( $ret ) {
				if ( $ret->status === 200 ) {
					return $productHandler->get_current_date();
				}
				// return error message.
				return $productHandler->get_current_date() . ' ERROR: ' . wp_json_encode( $ret );
			}
			// obviously, no product data due to missing SKU.
			return $productHandler->get_current_date() . ' Keine SKU';
		}
		return '';
	}

	/**
	 * transmits a product to wemalo-connect
	 *
	 * @param WP_POST $post
	 * @return stdClass|null
	 */
	private function transmit_product( WP_POST $post ): ?stdClass {
		$products = array();
		// Mohammad Oslani handle to transmit Product viw addMultiple Products.
		$handler   = new WemaloPluginProducts();
		$post_data = $handler->get_product_data( $post );
		if ( ! empty( $post_data ) ) {
			foreach ( $post_data as $value ) {
				$value['shop'] = $this->shop;
				$products[]    = $value;
			}

			return $this->_POST( 'product/addMultiple', array( 'products' => $products ) );
		}

		return null;
	}

	/**
	 * loads current order status from wemalo
	 *
	 * @param int $post_id
	 * @return boolean
	 * @throws Exception
	 */
	public function check_order_status( int $post_id ): bool {
		$handler = new WemaloPluginOrders();
		try {
			update_post_meta( $post_id, 'wemalo-check-status', $handler->get_current_date() );
			$ret = $this->_GET( 'goodsOrder/getStatus/' . $post_id );
			if ( $ret->status === 200 ) {
				$this->handle_order_status( $ret->statusName, $ret, $post_id, true );
				return true;
			}
		} catch ( \Exception $e ) {
			add_post_meta(
				$post_id,
				'wemalo-connect-status',
				$handler->get_current_date() . ' Error: ' . $e->getMessage()
			);
		}
		return false;
	}

	/**
	 * loads available dispatcher profiles
	 *
	 * @return array|string
	 */
	public function get_available_dispatcher_profiles() {
		try {
			$ret = $this->_GET( 'goodsOrder/profiles' );
			if ( $ret->status === 200 && isset( $ret->dispatcher ) ) {
				return $ret->dispatcher;
			}
		} catch ( \Exception $e ) {
			return $e->getMessage();
		}
		return 'Dispatcher profiles not loaded correctly.';
	}

	/**
	 * handles order status returned from wemalo: if partially reserved, the reason will
	 * be added as property. If reserved status will be changed to wemalo-fulfill and if
	 * cancelled in wemalo, status will be changed to cancelled in WordPress as well.
	 *
	 * @param string   $status
	 * @param stdClass $ret
	 * @param int      $post_id
	 * @param boolean  $checkCancelled
	 */
	private function handle_order_status( string $status, stdClass $ret, int $post_id, bool $checkCancelled = false ) {
		$handler                    = new WemaloPluginOrders();
		$this->order_in_fulfillment = $handler->handle_order_status( $post_id, $ret, $ret->statusName );
	}

	/**
	 * updates order status in wemalo
	 *
	 * @param int $order_id
	 * @param int $priority
	 * @return boolean
	 * @throws Exception
	 */
	public function update_order_priority( int $order_id, int $priority ): bool {
		$handler = new WemaloPluginOrders();

		try {
			update_post_meta(
				$order_id,
				'wemalo-connect-priority',
				$handler->get_current_date() . ' set priority ' . $priority
			);
			$data             = array();
			$data['shop']     = $this->shop;
			$data['priority'] = $priority;
			$ret              = $this->_PUT( 'goodsOrder/changePriority/' . $order_id, $data );
			if ( $ret->status === 200 ) {
				return true;
			} else {
					update_post_meta(
						$order_id,
						'wemalo-connect-priority',
						$handler->get_current_date() . ' Error: ' . wp_json_encode( $ret )
					);
			}
			return false;
		} catch ( Exception $e ) {
			add_post_meta(
				$order_id,
				'wemalo-connect-priority',
				$handler->get_current_date() . ' Error: ' . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * uploads an order document to wemalo
	 *
	 * @param int      $post_id
	 * @param string   $docType
	 * @param string   $docName
	 * @param string   $base64data
	 * @param WC_Order $order_obj
	 * @return string
	 */
	public function transmit_order_document(
		int $post_id,
		string $docType,
		string $docName,
		string $base64data,
		WC_Order $order_obj
	): string {
		try {
			$post_data               = array();
			$post_data['docType']    = $docType;
			$post_data['docName']    = $docName;
			$post_data['base64data'] = $base64data;
			$ret                     = $this->_POST( 'goodsOrder/addDocument/' . $post_id, $post_data );
			if ( $ret->status === 200 ) {
				$order_obj->add_order_note( $docName . ' uploaded', 0, true );
				return '';
			} else {
				return wp_json_encode( $ret );
			}
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * uploads a document to an announced return shipment
	 *
	 * @param int      $post_id
	 * @param string   $docName
	 * @param string   $base64data
	 * @param WC_Order $order_obj
	 * @return string
	 */
	public function transmit_return_document( int $post_id, string $docName, string $base64data, WC_Order $order_obj ): string {
		try {
			$post_data               = array();
			$post_data['docType']    = 'DOCUMENT';
			$post_data['docName']    = $docName;
			$post_data['base64data'] = $base64data;
			$ret                     = $this->_POST( 'returns/document/' . $post_id, $post_data );
			if ( $ret->status === 200 ) {
				$order_obj->add_order_note( $docName . ' uploaded', 0, true );
				return '';
			} else {
				return wp_json_encode( $ret );
			}
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * sends an order to wemalo-connect
	 *
	 * @param array $order
	 * @param int   $post_id
	 * @return boolean
	 * @throws Exception
	 */
	public function transmit_order( array $order, int $post_id ): bool {
		$handler = new WemaloPluginOrders();
		try {
			$ret = $this->_POST( 'goodsOrder/addConditional', $order );
			if ( $ret->status === 200 ) {
				$this->handle_order_status( $ret->orderStatus, $ret, $post_id );
				return true;
			} else {
				if ( $ret->status === 419 ) {
					$this->cancel_limit( $order, __FUNCTION__, $ret );
				}
				update_post_meta(
					$post_id,
					'wemalo-connect-transmit',
					$handler->get_current_date() . ' Error: ' . wp_json_encode( $ret )
				);

				return false;
			}
		} catch ( Exception $e ) {
			add_post_meta(
				$post_id,
				'wemalo-connect-transmit',
				$handler->get_current_date() . ' Error: ' . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * transmits a return to wemalo
	 *
	 * @param array $order
	 * @param int   $post_id
	 * @return boolean
	 * @throws Exception
	 */
	public function transmit_return_order( array $order, int $post_id ): bool {
		$handler = new WemaloPluginOrders();
		try {
			$ret = $this->_POST( 'returns/announceConditional', $order );
			if ( $ret->status === 200 ) {
				return true;
			} else {
				if ( $ret->status === 419 ) {
					$this->cancel_limit( $order, __FUNCTION__, $ret );
				}
				update_post_meta(
					$post_id,
					'wemalo-connect-return',
					$handler->get_current_date() . ' Error: ' . wp_json_encode( $ret )
				);
				return false;
			}
		} catch ( Exception $e ) {
			add_post_meta(
				$post_id,
				'wemalo-connect-return',
				$handler->get_current_date() . ' Error: ' . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * called for cancelling an order in wemalo
	 *
	 * @param int $order_id
	 * @return boolean
	 * @throws Exception
	 */
	public function cancel_order( int $order_id ): bool {
		$handler = new WemaloPluginOrders();
		try {
			$data           = array();
			$data['reason'] = 'Cancelled in WP';
			$data['shop']   = $this->shop;
			$ret            = $this->_PUT( 'goodsOrder/cancel/' . $order_id, $data );
			if ( $ret->status === 200 ) {
				return true;
			} else {
				if ( $ret->status === 419 ) {
					$this->cancel_limit( $order_id, __FUNCTION__, $ret );
				}
				update_post_meta(
					$order_id,
					'wemalo-connect-cancel',
					$handler->get_current_date() . ' Error: ' . wp_json_encode( $ret )
				);
				return false;
			}
		} catch ( Exception $e ) {
			add_post_meta(
				$order_id,
				'wemalo-connect-cancel',
				$handler->get_current_date() . ' Error: ' . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * adds a note to an order
	 *
	 * @param int    $order_id
	 * @param string $note
	 * @param int    $notesField
	 */
	public function add_order_note( int $order_id, string $note, int $notesField = 1 ) {
		$data                = array();
		$data['shop']        = $this->shop;
		$data['notices']     = $note;
		$data['noticeField'] = $notesField;
		$this->_PUT( 'goodsOrder/note/' . $order_id, $data );
	}

	/**
	 * loads current order status
	 *
	 * @param number $order_id
	 * @return mixed|boolean status result or false if call failed
	 */
	public function get_current_status( $order_id ) {
		$ret = $this->_GET( 'goodsOrder/getStatus/' . $order_id );
		if ( $ret->status === 200 ) {
			return $ret->orderStatus;
		}
		return false;
	}

	/**
	 * Get the tracking number from Wemalo Connect
	 *
	 * @param $goodsOrderParcelId
	 * @return array|boolean status result or false if call failed
	 */
	public function get_tracking_number( $goodsOrderParcelId ) {
		$ret = $this->_GET( 'goodsOrder/package/' . $goodsOrderParcelId . '/trackingNumber' );
		if ( $ret->status === 200 ) {
			return $ret;
		}
		return false;
	}

	/**
	 * Get the registered webhooks from Wemalo Connect
	 *
	 * @return array|boolean status result or false if call failed
	 */
	public function get_registered_webhooks() {
		return $this->_GET( 'webhook' );
	}

	/**
	 * @param $order_id
	 * @param $function
	 * @param $ret
	 * @return bool
	 */
	public function cancel_limit( int $order_id, string $function, $ret = null ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wemalo_timerate_limiter';

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE order_id = %d AND status = %s", $table_name, $order_id, $ret->status ) );
		if ( count( $result ) > 0 ) {
			return false;
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'order_id' => $order_id,
					'function' => $function,
					'status'   => $ret->status,
					'text'     => wp_json_encode( $ret ),
				)
			);
		}
		return true;
	}
}
