<?php

require_once __DIR__ . '/handler/class-wemalopluginproducts.php';
require_once __DIR__ . '/handler/class-wemalopluginorders.php';
require_once __DIR__ . '/handler/class-wemalopluginstock.php';
require_once __DIR__ . '/class-wemaloapiconnect.php';
require_once __DIR__ . '/class-wemaloapiexception.php';
require_once __DIR__ . '/class-wemaloapibasic.php';

/**
 * Registers api rest calls
 *
 * @author Patric Eid
 */
class WemaloAPIHandler extends WemaloAPIBasic {
	private WemaloPluginProducts $products;
	private WemaloPluginOrders $orders;
	private WemaloPluginStock $stock;

	/**
	 *
	 */
	public function __construct() {
		$this->products = new WemaloPluginProducts();
		$this->stock    = new WemaloPluginStock();
		$this->orders   = new WemaloPluginOrders();
	}

	/**
	 * setting app actions
	 */
	public function register_events() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * registeres product routes
	 *
	 * @param string $namespace
	 */
	private function registerProductRoutes( string $namespace ) {
		$this->registerRoute( $namespace, '/getall/(?P<timestamp>\d+)', 'get_products' );
		$this->registerRoute( $namespace, '/(?P<post_id>\d+)/', 'get_product_by_id' );
	}

	/**
	 * registers stock routes
	 *
	 * @param string $namespace
	 */
	private function registerStockRoutes( string $namespace ) {
		$this->registerRoute( $namespace, '/alt/set/(?P<post_id>\d+)', 'set_alternative_stock', 'PUT' );
		$this->registerRoute( $namespace, '/set/(?P<post_id>\d+)', 'set_stock', 'PUT' );
		$this->registerRoute( $namespace, '/lot/(?P<post_id>\d+)', 'set_lot', 'PUT' );
	}

	/**
	 * registers a route
	 *
	 * @param string $namespace
	 * @param string $route
	 * @param string $callback
	 * @param string $method
	 */
	private function registerRoute( string $namespace, string $route, string $callback, string $method = 'GET' ) {
		register_rest_route(
			$namespace,
			$route,
			array(
				'methods'             => $method,
				'callback'            => array( $this, $callback ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * registers order routes
	 *
	 * @param String $namespace
	 */
	private function registerOrderRoutes( string $namespace ) {
		$this->registerRoute( $namespace, '/all/(?P<max>\d+)/', 'get_orders' );
		$this->registerRoute( $namespace, '/(?P<order_id>\d+)', 'get_order' );
		$this->registerRoute( $namespace, '/return/announced/(?P<max>\d+)', 'get_returns' );
		$this->registerRoute( $namespace, '/completed/(?P<order_id>\d+)', 'set_order_completed', 'PUT' );
		$this->registerRoute( $namespace, '/downloaded/(?P<order_id>\d+)', 'set_order_downloaded', 'PUT' );
		$this->registerRoute( $namespace, '/track/(?P<order_id>\d+)', 'add_tracking_info', 'PUT' );
		$this->registerRoute( $namespace, '/return/booked/(?P<order_id>\d+)', 'return_order_booked', 'PUT' );
		$this->registerRoute( $namespace, '/cancel/(?P<order_id>\d+)', 'cancel_order', 'PUT' );
		$this->registerRoute( $namespace, '/fulfillment/(?P<order_id>\d+)', 'fulfillment', 'PUT' );
		$this->registerRoute( $namespace, '/add_item_information_in_order/(?P<order_id>\d+)', 'add_item_information_in_order', 'PUT' );
		$this->registerRoute( $namespace, '/check_in_process', 'check_in_process', 'GET' );
		$this->registerRoute( $namespace, '/fulfillment_blocked/(?P<order_id>\d+)', 'fulfillment_blocked', 'PUT' );
		$this->registerRoute( $namespace, '/transmit/(?P<order_id>\d+)', 'transmit_order', 'PUT' );

		$this->registerRoute( $namespace, '/hook/status', 'statusHook', 'POST' );
		$this->registerRoute( $namespace, '/hook/track', 'tracking_number_hook', 'POST' );
	}

	/**
	 * called when status updates where submitted by wemalo backend
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function statusHook( WP_REST_Request $request ): WP_REST_Response {
		try {
			// check if hook is activated
			$hook   = get_option( 'wemalo_hook_token' );
			$params = $request->get_params();
			if ( $hook ) {
				if ( $hook === $params['token'] ) {
					$event_data = $params['eventData'];
					$status     = $event_data['statusName'];
					if ( $status === 'CANCELLED' ) {
						return $this->generate_output( 'skip ' . $status );
					}
					$order_id = $event_data['externalId'];
					$this->orders->handle_order_status( $order_id, json_decode( wp_json_encode( $event_data ) ), $status );
					return $this->generate_output( 'order:' . $order_id . ' status: ' . $status );
				} else {
					return $this->generate_output( 'token not valid ! ', 'Webhook', false, 403, $params['token'] );
				}
			}
			return $this->generate_output( 'no hook !', 'Webhook', false, 404 );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * adds tracking information to an order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function tracking_number_hook( WP_REST_Request $request ): WP_REST_Response {
		try {
			// check if hook is activated
			$hook   = get_option( 'wemalo_hook_token' );
			$params = $request->get_params();
			if ( $hook ) {
				if ( $hook === $params['token'] ) {
					$params = $params['eventData'];

					$connect = new WemaloAPIConnect();
					$json    = $connect->get_tracking_number( $params['goodsOrderParcelId'] );
					if ( $json !== false ) {
						if ( array_key_exists( 'goodsOrderExternalId', $params ) ) {
							$external_id     = $params['goodsOrderExternalId'];
							$carrier         = $json->carrier;
							$tracking_number = $json->trackingNumber;

							return $this->generate_output( $this->orders->add_tracking_info( $external_id, $tracking_number, $carrier ) );
						} else {
							return $this->generate_output( 'goodsOrderExternalId Not found', 'Webhook', false, 403, $params['token'] );
						}
					} else {
						return $this->generate_output( 'No tracking number found', 'Webhook', false, 403, $params['token'] );
					}
				} else {
					return $this->generate_output( 'token not valid ! ', 'Webhook', false, 403, $params['token'] );
				}
			}
			return $this->generate_output( 'no hook !', 'Webhook', false, 404 );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * transmits an order and returns true if order was changed to fulfillment
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function transmit_order( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->transmit_order( $params['order_id'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * checks orders in status in process and transmits orders if not transmitted yet
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function check_in_process( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output(
				$this->orders->check_partially_reserved(
					false,
					array_key_exists( 'perPage', $params ) ? $params['perPage'] : 100,
					array_key_exists( 'offset', $params ) ? $params['offset'] : 0
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * loads orders that were set to status return announced
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_returns( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->get_orders( false, $params['max'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * loads all orders since time
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_orders( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->get_orders( true, $params['max'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * loads order details by order id
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_order( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->get_order( $params['order_id'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets an order as completed
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function set_order_completed( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->set_order_status( $params['order_id'], 'wc-completed' ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets an order as downloaded
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function set_order_downloaded( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->set_order_downloaded( $params['order_id'], null ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * adds tracking information to an order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function add_tracking_info( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params              = $request->get_params();
						$carrier = '';
			if ( array_key_exists( 'carrier', $params ) ) {
				$carrier = $params['carrier'];
			} else {
				$carrier = 'Other';
			}
			return $this->generate_output(
				$this->orders->add_tracking_info(
					$params['order_id'],
					$params['trackingcode'],
					$carrier
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * adds item information to an order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function add_item_information_in_order( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			if ( ! key_exists( 'order_id', $params ) || ! $params['order_id'] ) {
				return $this->generate_output( null, 'order ID is required', false, 500 );
			}
			if ( ! key_exists( 'posId', $params ) || ! $params['posId'] ) {
				return $this->generate_output( null, 'item ID (posId) is required', false, 500 );
			}
			$order = wc_get_order( $params['order_id'] );
			if ( ! $order ) {
				return $this->generate_output( null, 'order ' . $params['order_id'] . ' not found', false, 500 );
			}

			$has_item = false;
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( $item_id === $params['posId'] ) {
					$has_item = true;
					break;
				}
			}
			if ( ! $has_item ) {
				return $this->generate_output( null, 'order ' . $params['order_id'] . ' has no item ' . $params['posId'], false, 500 );
			}

			$this->orders->add_serial_number( $params['posId'], $params['serialNumber'] );
			$this->orders->add_lot( $params['posId'], $params['lot'] );
			$this->orders->add_meta_sku( $params['posId'], $params['sku'] );
			return $this->generate_output( 0 );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * books a returned order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function return_order_booked( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output(
				$this->orders->booked_return_shipment(
					$params['order_id'],
					$params['product_id'],
					$params['quantity'],
					$params['choice'],
					$params['reason'],
					$params['serialNumber'],
					$params['lot'],
					$params['sku']
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * cancels an order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function cancel_order( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->set_order_cancelled( $params['order_id'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets order status fulfillment
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function fulfillment( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output( $this->orders->set_order_fulfillment( $params['order_id'] ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets an order as fulfillment blocked (e.g. shipping label couldn't be created successfully)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function fulfillment_blocked( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			return $this->generate_output(
				$this->orders->set_order_fulfillment(
					$params['order_id']
				),
				$params['reason']
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets stock
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function set_stock( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params    = $request->get_params();
			$post_id   = $params['post_id'];
			$new_stock = $params['quantity'];
			return $this->generate_output( $this->stock->newStock( $post_id, $new_stock ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * sets alternative stock
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function set_alternative_stock( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params    = $request->get_params();
			$post_id   = $params['post_id'];
			$new_stock = $params['quantity'];
			return $this->generate_output( $this->stock->setBStock( $post_id, $new_stock ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * adds lot information
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function set_lot( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params  = $request->get_params();
			$post_id = $params['post_id'];
			$data    = $params['data'];
			return $this->generate_output(
				$this->stock->addLotInformation(
					$post_id,
					$data,
					$params['notReserved']
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * registers methods
	 */
	public function register() {
		$namespace = 'rest/v1/wemalo';
		$this->registerProductRoutes( $namespace . '/product' );
		$this->registerOrderRoutes( $namespace . '/order' );
		$this->registerStockRoutes( $namespace . '/stock' );
		$this->registerRoute( $namespace, '/info', 'get_plugin_data' );
		$this->registerRoute( $namespace, '/resetHook', 'reset_webhooks', 'POST' );
	}

	/**
	 * re-registers to webhooks
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function reset_webhooks( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$data          = array();
			$connect       = new WemaloAPIConnect();
			$data['token'] = $connect->register_webhooks();
			return $this->generate_output( $data );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * reads current plugin data (e.g. version number) and returns it
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_plugin_data( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$plugin_data = array();
			if ( function_exists( 'get_plugin_data' ) ) {
				$plugin_data = get_plugin_data( __DIR__ . '/../WemaloAPI.php' );
			}
			$plugin_data['wemalo_hook_token'] = get_option( 'wemalo_hook_token' );
			$plugin_data['shop']              = get_option( 'wemalo_plugin_shop_name' );
			return $this->generate_output( $plugin_data );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * returns product data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_product_by_id( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params = $request->get_params();
			$post   = get_post( $params['post_id'] );
			return $this->generate_output( $this->products->get_product_data( $post, true ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	/**
	 * loads products
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response {
		try {
			$this->check_authentication( $request );
			$params    = $request->get_params();
			$timestamp = '';
			if ( array_key_exists( 'timestamp', $params ) ) {
				$timestamp = $params['timestamp'];
			}
			return $this->generate_output( $this->products->get_all_products( $timestamp ) );
		} catch ( Exception $e ) {
			return $this->handle_error( $e );
		}
	}

	public function custom_amazon_shipping_providers(): array {
		$connect            = new WemaloAPIConnect();
		$wemalo_dispatchers = $connect->get_available_dispatcher_profiles();
		$arr                = array();

		foreach ( $wemalo_dispatchers as $dispatcher ) {
			foreach ( $dispatcher->profiles as $profile ) {
				if ( $profile->product !== '2' && $profile->product !== 'O' ) {
					if ( $profile->product === 'V01PAK' || $profile->product === 'dhl_express' ) {
						$arr[] = 'DHL';
					} elseif ( $profile->product === 'V62WP' ) {
						$arr[] = 'Warenpost';
					} elseif ( $profile->product === '10247' ) {
						$arr[] = 'Deutsch Post Internetmarke';
					} else {
						$arr[] = $profile->product;
					}
				}
			}
		}

		return array_unique( $arr );
	}
}
