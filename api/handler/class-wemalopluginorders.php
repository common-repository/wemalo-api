<?php

require_once __DIR__ . '/class-wemalopluginbase.php';
require_once __DIR__ . '/../../plugin/class-wemaloapiorderstatusarray.php';
require_once __DIR__ . '/../class-wemaloapiconnect.php';

/**
 * handles goods orders
 *
 * @author Patric Eid
 */
class WemaloPluginOrders extends WemaloPluginBase {

	private const TRACKING_SEPARATOR = ',';

	public const WEMALO_DOWNLOAD_ORDER_KEY     = 'wemalo_download';
	public const WEMALO_CANCEL_ORDER_KEY       = 'wemalo_cancelled';
	public const WEMALO_RETURN_ORDER_KEY       = 'wemalo_return_announced';
	public const WEMALO_PARTIALLY_REASON       = 'wemalo_partially_reserved';
	public const WEMALO_SERIAL_NUMBER          = '_wemalo_serialnumber';
	public const WEMALO_LOT                    = '_wemalo_lot';
	public const WEMALO_SKU                    = '_wemalo_sku';
	public const WEMALO_ORDER_NUMBER_FORMATTED = '_wemalo_order_number_formatted';
	public const WEMALO_HOUSE_NUMBER_FIELD     = '_wemalo_house_number_field';
	public const WEMALO_PARENT_ORDER_FIELD     = '_wemalo_parent_order_field';
	public const WEMALO_ORDER_CATEGORY_FIELD   = '_wemalo_order_category_field';

	private ?WemaloAPIConnect $connect = null;

	/**
	 * Serves an array with shipping or billing address
	 *
	 * @param array   $order_meta_array
	 * @param boolean $is_order in case of returns we need information differently
	 * @return array :NULL
	 */
	private function create_address_array( array $order_meta_array, bool $is_order = true ): array {
		$order_address                         = array();
		$delivery_address                      = '_shipping';
		$order_address['name1']                = $this->get_value( $delivery_address . '_first_name', $order_meta_array ) . ' ' .
			$this->get_value( $delivery_address . '_last_name', $order_meta_array );
		$order_address['name2']                = $this->get_value( $delivery_address . '_company', $order_meta_array );
		$order_address['street']               = $this->get_value( $delivery_address . '_address_1', $order_meta_array );
		$order_address['addressAddition']      = $this->get_value( $delivery_address . '_address_2', $order_meta_array );
		$order_address['zip']                  = $this->get_value( $delivery_address . '_postcode', $order_meta_array );
		$order_address['city']                 = $this->get_value( $delivery_address . '_city', $order_meta_array );
		$order_address['stateProvinceCountry'] = $this->get_value( $delivery_address . '_state', $order_meta_array );

		if ( trim( get_option( self::WEMALO_HOUSE_NUMBER_FIELD ) ) !== '' ) {
			$order_address['streetNumber'] = $this->get_value( get_option( self::WEMALO_HOUSE_NUMBER_FIELD ), $order_meta_array );
		}

		if ( $is_order ) {
			$order_address['countryCode2Letter'] = $this->get_value( $delivery_address . '_country', $order_meta_array );
			$order_address['phoneNumber']        = $this->get_value( '_billing_phone', $order_meta_array );
			$order_address['email']              = $this->get_value( '_billing_email', $order_meta_array );
			$order_address['primary']            = true;
		} else {
			$order_address['country'] = $this->get_value( $delivery_address . '_country', $order_meta_array );
			$order_address['phone']   = $this->get_value( '_billing_phone', $order_meta_array );
			$order_address['mail']    = $this->get_value( '_billing_email', $order_meta_array );
		}

		return $order_address;
	}

	/**
	 * Returns the connect client
	 *
	 * @return WemaloAPIConnect|null
	 */
	public function get_wemalo_api_connect(): ?WemaloAPIConnect {
		$this->connect = $this->connect ?? new WemaloAPIConnect();

		return $this->connect;
	}

	/**
	 * Loads a return object by an order
	 *
	 * @param int      $post_id
	 * @param WC_Order $order
	 * @return array|null
	 */
	public function get_return_object( int $post_id, WC_Order $order ): ?array {
		// get meta value if key exists.
		$value = get_post_meta( $post_id, self::WEMALO_RETURN_ORDER_KEY, true );
		if ( ! $value ) {
			return $this->build_order_obj( $post_id, false );
		}
		return null;
	}

	/**
	 * Loads a return object by an order
	 *
	 * @param int      $post_id
	 * @param WC_Order $order
	 * @return array|null
	 */
	public function get_reclamation_object( int $post_id, WC_Order $order ): ?array {
		// get meta value if key exists.
		$value = get_post_meta( $post_id, 'wemalo_reclam_announced', true );
		if ( ! $value ) {
			return $this->build_order_obj( $post_id, false );
		}
		return null;
	}

	/**
	 * Called for cancelling an order
	 *
	 * @param int           $post_id
	 * @param boolean       $load_data true to load orders data, otherwise the default order object is being returned
	 * @param WC_Order|null $order
	 * @param Boolean       $skip_check
	 * @return Boolean true if order was cancelled
	 * @throws Exception
	 */
	public function cancel_order( int $post_id, bool $load_data = false, ?WC_Order $order = null, bool $skip_check = true ): bool {
		$order = $this->get_order_to_download( $post_id, $load_data, $order, $skip_check );
		if ( $order ) {
			// an order object was returned, so cancel it in Wemalo as well.
			if ( $this->get_wemalo_api_connect()->cancel_order( $post_id ) ) {
				$this->set_order_downloaded( $post_id, self::WEMALO_CANCEL_ORDER_KEY );
				return true;
			}
		}
		return false;
	}

	/**
	 * Loads an order and returns it as array if it should be transmitted to wemalo-connect
	 *
	 * @param int           $post_id
	 * @param boolean       $load_data true to load orders data, otherwise the default order object is being returned
	 * @param WC_Order|null $order
	 * @param boolean       $skip_check
	 * @return WC_Order|array|null
	 */
	public function get_order_to_download( int $post_id, bool $load_data = true, ?WC_Order $order = null, bool $skip_check = false ): WC_Order|array|null {
		$order = $order ?? wc_get_order( $post_id );

		// check if order is in status processing and download key hasn't been set yet.
		if ( $order && ( $skip_check || $this->is_processing( $order ) ) ) {
			if ( $load_data ) {
				// 2017-12-14, Patric Eid: don't check if flag is set since we want to have the possibility updating an order easily
				// 2017-12-20, Patric Eid: we won't check for download flag anymore,
				// but we'll now be checking if a special order not paid flag has been set.
				// In that case the order will not be transmitted.
				if ( ! in_array( 'order_not_paid', get_post_custom_keys( $post_id ) ) ) {
					return $this->build_order_obj( $post_id );
				}
			} else {
				return $order;
			}
		}
		return null;
	}

	/**
	 * Returns true if an order is in status processing or fulfillment
	 *
	 * @param WC_Order $order
	 * @return boolean
	 */
	public function is_processing_or_fulfillment( WC_Order $order ): bool {
		return ( $this->is_processing( $order ) || $this->is_fulfill( $order ) );
	}

	/**
	 * Returns true if order is in status processing
	 *
	 * @param WC_Order $order
	 * @return boolean
	 */
	public function is_processing( WC_Order $order ): bool {
		return ( ! empty( $order ) && ( $order->get_status() === 'processing' || $order->get_status() === 'order-inprocess' ) );
	}

	/**
	 * Returns true if order is in status wemalo-fulfill
	 *
	 * @param WC_Order $order
	 * @return boolean
	 */
	public function is_fulfill( WC_Order $order ): bool {
		return ( ! empty( $order ) && $order->get_status() === 'wemalo-fulfill' );
	}

	/**
	 * Returns true if order was set to status return announced
	 *
	 * @param WC_Order $order
	 * @return boolean
	 */
	public function is_return_announced( WC_Order $order ): bool {
		return ( ! empty( $order ) && $order->get_status() === 'return-announced' );
	}

	/**
	 * Returns true if order was set to status reclam announced
	 *
	 * @param WC_Order $order
	 * @return boolean
	 */
	public function is_reclamation_announced( WC_Order $order ): bool {
		return ( ! empty( $order ) && $order->get_status() === 'reclam-announced' );
	}

	/**
	 * Loads an order by its id
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function get_order( int $order_id ): array {
		return $this->build_order_obj( $order_id, true, true );
	}

	/**
	 * Loads order details
	 *
	 * @param int     $post_id
	 * @param boolean $is_order in case of announced return, we need different information
	 * @param boolean $extended_info
	 * @return array
	 */
	private function build_order_obj( int $post_id, bool $is_order = true, bool $extended_info = false ): array {
		$order            = wc_get_order( $post_id );
		$order_meta_array = get_post_meta( $post_id );
		$meta             = array();
		$order_address    = $this->create_address_array( $order_meta_array, $is_order );
		$order_positions  = array();
		$order_items      = $order->get_items();
		$shipping_items   = $order->get_items( 'shipping' );
		if ( $extended_info ) {
			$meta['shipping'] = array();
		}
		$order_shipping_method_id = '';
		$shipping_found           = false;

		foreach ( $shipping_items as $el ) {
			if ( ! $shipping_found ) {
				$order_shipping_method_id = $el['method_id'];
				$shipping_found           = true; // Set found to true immediately
			}

			if ( $extended_info ) {
				$meta['shipping'][] = $el['method_id'];
			} else {
				break; // Break early if extended info is not needed
			}
		}

		$receiver                = array();
		$receiver[]              = $order_address;
		$meta['externalId']      = $post_id;
		$meta['externalIdMatch'] = 'sku';
		$order_number            = $post_id;
		$order_category          = '';

		if ( trim( get_option( self::WEMALO_ORDER_NUMBER_FORMATTED ) ) !== '' ) {
			$order_number = $this->get_order_number_formatted_by_post_id( $post_id );
		}

		if ( trim( get_option( self::WEMALO_ORDER_CATEGORY_FIELD ) ) !== '' ) {
			$order_category = $this->get_order_category_by_post_id( $post_id );
		}

		$create_we_check = array_key_exists( '_wemalo_return_we', $order_meta_array ) && $order_meta_array['_wemalo_return_we'][0] === 'yes';
		$create_wa_check = array_key_exists( '_wemalo_return_wa', $order_meta_array ) && $order_meta_array['_wemalo_return_wa'][0] === 'yes';

		if ( $is_order || $create_wa_check ) {
			$meta['orderNumber']          = $order_number;
			$meta['category']             = $order_category;
			$meta['total']                = $order->get_total();
			$meta['totalInvoice']         = $order->get_total();
			$meta['totalVat']             = $order->get_total_tax();
			$meta['currency']             = ( method_exists( $order, 'get_currency' ) ? $order->get_currency() : 'EUR' );
			$meta['expectedDeliveryDate'] = array_key_exists( '_wemalo_delivery', $order_meta_array ) ? $order_meta_array['_wemalo_delivery'][0] : '';
			$meta['blocked']              = 0;
			if ( array_key_exists( '_wemalo_order_blocked', $order_meta_array ) && $order_meta_array['_wemalo_order_blocked'][0] === 'yes' ) {
				$meta['blocked'] = 1;
			}
			$meta['priority'] = (int) ( array_key_exists( '_wemalo_priority', $order_meta_array ) ? $order_meta_array['_wemalo_priority'][0] : '' );
			if ( $meta['priority'] < 1 ) {
				$meta['priority'] = 3;
			}
			$profile = ( array_key_exists( '_wemalo_dispatcher_product', $order_meta_array ) ? $order_meta_array['_wemalo_dispatcher_product'][0] : '' );
			if ( $profile === '' || $profile === 'auto' ) {
				$payment_method = get_post_meta( $post_id, '_payment_method', true );
				if ( strtolower( $payment_method ) === 'cod' ) {
					$meta['dispatcherProfile'] = $order_shipping_method_id . '_cod';
				} else {
					$meta['dispatcherProfile'] = $order_shipping_method_id;
				}
			} else {
				$meta['dispatcherProfile'] = $profile;
			}
			$meta['pickingInfo']           = array_key_exists( '_wemalo_return_note', $order_meta_array ) ? $order_meta_array['_wemalo_return_note'][0] : '';
			$meta['linkedOrderExternalId'] = array_key_exists( '_wemalo_linked_order', $order_meta_array ) ? $order_meta_array['_wemalo_linked_order'][0] : '';

		}
		if ( ! $is_order || $create_we_check ) {
			$meta['deliveryNumber']      = $post_id;
			$meta['orderNumberCustomer'] = $order_number;
			// check if external id is needed or not
			if ( ! array_key_exists( 'wemalo_ignore_parent_order', $order_meta_array ) ) {
				// get parent order key by option field if set
				$parent_order_key = trim( get_option( self::WEMALO_PARENT_ORDER_FIELD ) );
				if ( empty( $parent_order_key ) ) {
					// if not set, set it to default key
					$parent_order_key = 'parent_order_id';
				}
				$meta['goodsOrderExternalId'] = array_key_exists( $parent_order_key, $order_meta_array ) ? $order_meta_array[ $parent_order_key ][0] : $post_id;
			}
			$meta['picking_notice']        = array_key_exists( '_wemalo_return_note', $order_meta_array ) ? $order_meta_array['_wemalo_return_note'][0] : '';
			$meta['skipSerialNumberCheck'] = array_key_exists( '_wemalo_return_checkserial', $order_meta_array ) && $order_meta_array['_wemalo_return_checkserial'][0] === 'yes';
		}
		$meta['createWECheck'] = $create_we_check;
		$meta['createWACheck'] = $create_wa_check;
		$order_obj             = array();
		if ( $is_order ) {
			$meta['receiver']  = $receiver;
			$order_obj['meta'] = $meta;
			$order_obj['shop'] = $this->get_wemalo_api_connect()->get_shop_name();
		}
		if ( ! $is_order || $create_we_check ) {
			// return orders doesn't have meta.
			$order_obj           = $meta;
			$order_obj['sender'] = $order_address;
			$meta['receiver']    = $receiver;
			$order_obj['meta']   = $meta;
			$order_obj['shop']   = $this->get_wemalo_api_connect()->get_shop_name();
		}
		if ( $extended_info ) {
			$order_obj['fields'] = array();
			foreach ( $order_meta_array as $k => $v ) {
				$order_obj['fields'][ $k ] = ( is_array( $v ) ? $v[0] : $v );
			}
			$order_obj['fields']['status'] = $order->get_status();
		}
		$positions   = array();
		$pos_counter = 0;

		foreach ( $order_items as $position ) {
			$json    = json_decode( $position );
			$product = array();
			// get variation id if set.
			$product['externalId'] = $json->variation_id;
			if ( $product['externalId'] <= 0 ) {
				// otherwise, get product id
				$product['externalId'] = $json->product_id;
			}
			$product['externalPositionIdentifier'] = $json->id;
			$product['name']                       = $this->get_value( 'name', $position );
			$product['quantity']                   = $this->get_value( 'qty', $position );
			if ( ! empty( $product['quantity'] ) ) {
				$product['name']     = $json->name;
				$product['quantity'] = $json->quantity;
			}
			$p = $position->get_product();
			if ( $extended_info ) {
				$product['productData']  = json_decode( $p );
				$product['positionData'] = $json;
			}
			$product['sku']   = $p->get_sku();
			$product['total'] = $json->total;
			if ( is_numeric( $product['total'] ) ) {
				$product['total'] = round( $product['total'] * 100.0 );
			} else {
				$product['total'] = 0;
			}
			$product['tax'] = $json->total_tax;
			if ( is_numeric( $product['tax'] ) ) {
				$product['tax'] = round( $product['tax'] * 100.0 );
			} else {
				$product['tax'] = 0;
			}
			// remember external id but use sku for matching position.
			$product['_externalId'] = $product['externalId'];
			$product['externalId']  = $p->get_sku();
			if ( ! empty( $product['externalId'] ) ) {
				// add position if external id was set.
				if ( ! method_exists( $p, 'get_virtual' ) ) {
					// if method doesn't exist, check in post meta if it's virtual or not.
					$prod_meta = get_post_meta( $product['_externalId'] );
					if ( ! in_array( '_virtual', $prod_meta ) ) {
						$positions[] = $product;
						++$pos_counter;
					}
				} elseif ( ! $p->get_virtual() ) {
						$positions[] = $product;
						++$pos_counter;
				}
			}
		}
		if ( $is_order ) {
			$order_obj['positions'] = $positions;
		}
		if ( ! $is_order || $create_we_check ) {
			$order_obj['items'] = $positions;
			if ( $create_wa_check ) {
				$order_obj['positions'] = $positions;
			}
		}
		$order_obj['posCounter'] = $pos_counter;
		return $order_obj;
	}

	/**
	 * Serves all orders
	 *
	 * @param $paid_orders
	 * @param int         $max
	 * @return array
	 * @throws Exception
	 */
	public function get_orders( $paid_orders, int $max = 200 ): array {
		$my_order_list = array();
		$order_status  = ( $paid_orders ? array( 'wc-processing' ) : WemaloAPIOrderStatusArray::STATUS_RETURN_ANNOUNCED );
		$meta_key      = ( $paid_orders ? self::WEMALO_DOWNLOAD_ORDER_KEY : self::WEMALO_RETURN_ORDER_KEY );
		$loop          = new WP_Query(
			array(
				'post_type'      => array( 'shop_order' ),
				'post_status'    => $order_status,
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => $meta_key,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		while ( $loop->have_posts() ) :
			$loop->the_post();
			$post_id = get_the_ID();

			$my_order = $this->build_order_obj( $post_id, $paid_orders );
			if ( $my_order['posCounter'] > 0 ) {
				$my_order_list[] = $my_order;
			}

			// set order as downloaded
			$this->set_order_downloaded( $post_id, $meta_key );

			if ( count( $my_order_list ) >= $max ) {
				break;
			}
		endwhile;
		wp_reset_query();
		$order_status = ( $paid_orders ? array( 'wc-processing' ) : WemaloAPIOrderStatusArray::STATUS_RECLAMATION_ANNOUNCED );
		$meta_key     = ( $paid_orders ? self::WEMALO_DOWNLOAD_ORDER_KEY : 'wemalo_reclam_announced' );
		$loop         = new WP_Query(
			array(
				'post_type'      => array( 'shop_order' ),
				'post_status'    => $order_status,
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => $meta_key,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		while ( $loop->have_posts() ) :
			$loop->the_post();
			$post_id = get_the_ID();

			$my_order = $this->build_order_obj( $post_id, $paid_orders );
			if ( $my_order['posCounter'] > 0 ) {
				$my_order_list[] = $my_order;
			}

			// set order as downloaded
			$this->set_order_downloaded( $post_id, $meta_key );

			if ( count( $my_order_list ) >= $max ) {
				break;
			}
		endwhile;
		wp_reset_query();
		return $my_order_list;
	}

	/**
	 * Checks partially reserved orders in wemalo and updates order status accordingly if
	 * order was reserved in the meanwhile
	 *
	 * @param boolean $check_each_reason true if each partially reserved reason should be checked
	 * @param int     $posts_per_page
	 * @param int     $offset
	 * @return array
	 * @throws Exception
	 */
	public function check_partially_reserved( bool $check_each_reason = true, int $posts_per_page = 100, int $offset = 0 ): array {
		$has_partially_reserved = false;
		$order_status           = 'wc-processing';
		$loop                   = new WP_Query(
			array(
				'post_type'      => array( 'shop_order' ),
				'post_status'    => array( $order_status ),
				'posts_per_page' => $posts_per_page + 1,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$counter                = 0;
		$has_more               = false;
		while ( $loop->have_posts() ) :
			$loop->the_post();
			++$counter;
			if ( $counter > $posts_per_page ) {
				$has_more = true;
				break;
			}
			$post_id        = get_the_ID();
			$transmit_order = true;
			// check if already transmitted
			if ( $this->is_order_downloaded( $post_id ) ) {
				$transmit_order = false;
				if ( ! $check_each_reason ) {
					// get reason and in some case re-transmit order
					$reason = get_post_meta( $post_id, self::WEMALO_PARTIALLY_REASON, true );
					switch ( $reason ) {
						case 'UNKNOWN':
						case 'ADDRESS':
							$transmit_order = true;
							break;
						default:
							// check order status
							if ( ! $this->get_wemalo_api_connect()->check_order_status( $post_id ) ) {
								// not changed to open, so still partially reserved
								$has_partially_reserved = true;
							}
							break;
					}
				} elseif ( ! $this->get_wemalo_api_connect()->check_order_status( $post_id ) ) {
					// not changed to open, so still partially reserved
					$has_partially_reserved = true;
				}
			}
			if ( $transmit_order ) {
				if ( ! $this->transmit_order( $post_id ) ) {
					$has_partially_reserved = true;
				}
			}
		endwhile;
		wp_reset_query();

		return array(
			'hasPartiallyReserved' => $has_partially_reserved,
			'posts'                => $counter,
			'hasMore'              => $has_more,
		);
	}

	/**
	 * Changes woocommerce order status depending on current wemalo status
	 *
	 * @param int    $post_id
	 * @param mixed  $ret
	 * @param string $status
	 * @return boolean
	 */
	public function handle_order_status( int $post_id, mixed $ret, string $status ): bool {
		$order_in_fulfillment = false;
		$this->remove_order_notice_flags( $post_id );
		switch ( $status ) {
			case 'OPEN': // reserved and ready for picking
				break;
			case 'INPROCESS': // picking list created
				$this->set_order_status( $post_id, WemaloAPIOrderStatusArray::STATUS_ORDER_INPROCESS );
				break;
			case 'INPICKING': // picking started
				break;
			case 'READYFORSHIPMENT': // picking finished
				break;
			case 'PACKED': // packing finished
				// order was reserved, so change order status accordingly
				$this->set_order_fulfillment( $post_id );
				$order_in_fulfillment = true;
				break;
			case 'BLOCKEDPACKED': // packing finished, but blocked
				$this->set_order_fulfillment_blocked( $post_id, $ret->blockReason );
				$order_in_fulfillment = true;
				break;
			case 'PARTIAL':
				update_post_meta(
					$post_id,
					self::WEMALO_PARTIALLY_REASON,
					$ret->reasonPartiallyReserved
				);
				break;
			case 'CANCELLED':
				$this->set_order_cancelled( $post_id );
				break;
			case 'SHIPPED':
				$this->set_order_status( $post_id, 'wc-completed' );
				break;
		}
		return $order_in_fulfillment;
	}

	/**
	 * Called for setting orders as downloaded
	 *
	 * @param int      $order_id
	 * @param $meta_key
	 * @return boolean
	 * @throws Exception
	 */
	public function set_order_downloaded( int $order_id, $meta_key ): bool {
		if ( ! $meta_key ) {
			$meta_key = self::WEMALO_DOWNLOAD_ORDER_KEY;
		}
		add_post_meta( $order_id, $meta_key, $this->get_current_date(), true );
		return true;
	}

	/**
	 * Checks whether order has been downloaded
	 *
	 * @param int $order_id
	 * @return boolean
	 */
	public function is_order_downloaded( int $order_id ): bool {
		$value = get_post_meta( $order_id, self::WEMALO_DOWNLOAD_ORDER_KEY, true );
		return (bool) $value;
	}

	/**
	 * Removes order flags
	 *
	 * @param int $order_id
	 */
	private function remove_order_notice_flags( int $order_id ) {
		delete_post_meta( $order_id, self::WEMALO_PARTIALLY_REASON );
		delete_post_meta( $order_id, 'fulfillment-blocked' );
	}

	/**
	 * Sets the status of a sales order
	 *
	 * @param int    $order_id
	 * @param string $order_status
	 * @return number|array
	 */
	public function set_order_status( int $order_id, string $order_status ): array|int {
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			// delete flags
			$this->remove_order_notice_flags( $order_id );
			if ( ! empty( $order ) ) {
				$order->update_status( $order_status );
				return 0;
			}
			return $this->return_error( 21, "Order with ID [$order_id] not found!" );
		}
		return $this->return_error( 20, 'No order id given!' );
	}

	/**
	 * Save booked return-shipments in DB
	 *
	 * @param int    $order_id
	 * @param int    $product_id
	 * @param int    $quantity
	 * @param string $choice
	 * @param string $reason
	 * @param string $serial_number
	 * @param string $lot
	 * @param string $sku
	 * @return int|array
	 */
	public function booked_return_shipment(
		int $order_id,
		int $product_id,
		int $quantity,
		string $choice,
		string $reason,
		string $serial_number,
		string $lot,
		string $sku
	): array|int {
			global $wpdb;
			$table_name   = $wpdb->prefix . 'posts';
			$result       = $wpdb->get_row( $wpdb->prepare( "SELECT post_title FROM %i WHERE ID=%d;", $table_name, $product_id ) );
			$product_name = $result->post_title;
			$sql          = $wpdb->prepare(
				'INSERT INTO ' . $wpdb->prefix . 'wemalo_return_pos (order_id, product_id, quantity, choice, return_reason, product_name, timestamp, serial_number, lot, sku) ' . 'VALUES (%d,%d,%d,%s,%s,%s,CURRENT_TIMESTAMP,%s,%s,%s)',
				$order_id,
				$product_id,
				$quantity,
				$choice,
				$reason,
				$product_name,
				$serial_number,
				$lot,
				$sku
			);
		if ( $wpdb->query( $sql ) ) {
			$order = wc_get_order( $order_id );
			if ( ! empty( $order ) ) {
				$order->update_status( WemaloAPIOrderStatusArray::STATUS_RETURN_BOOKED );
			}
			return 1;
		} else {
			return $this->return_error( 500, $wpdb->last_error );
		}
	}

	/**
	 * Sets an order as cancelled (will be called when cancelled in Wemalo!)
	 *
	 * @param int $order_id
	 * @return array|int
	 */
	public function set_order_cancelled( int $order_id ): array|int {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			// order can be cancelled if in status in processing order wemalo fulfillment only
			if ( $this->is_processing( $order ) || $this->is_fulfill( $order ) ) {
				$order->update_status( WemaloAPIOrderStatusArray::STATUS_ORDER_CANCELLED );
				return 0;
			}
			return $this->return_error(
				500,
				'order status [' .
				$order->get_status() . "] can't be cancelled."
			);
		} else {
			return $this->return_error( 404, 'order not found' );
		}
	}

	/**
	 * Sets order status fulfillment
	 *
	 * @param int $order_id
	 * @return int|array
	 */
	public function set_order_fulfillment( int $order_id ): array|int {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			delete_post_meta( $order_id, 'fulfillment-blocked' );
			$order->update_status( WemaloAPIOrderStatusArray::STATUS_ORDER_FULFILLMENT );
			return 0;
		} else {
			return $this->return_error( 404, 'order not found' );
		}
	}

	/**
	 * Sets order status to fulfillment blocked
	 *
	 * @param int    $order_id
	 * @param string $reason
	 * @return int|array
	 */
	public function set_order_fulfillment_blocked( int $order_id, string $reason ): array|int {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) ) {
			$order->update_status( WemaloAPIOrderStatusArray::STATUS_ORDER_FULFILLMENT_BLOCKED );
			update_post_meta( $order_id, 'fulfillment-blocked', $reason );
			return 0;
		} else {
			return $this->return_error( 404, 'order not found' );
		}
	}

	/**
	 * Serves booked returnshipments for single Order if available
	 *
	 * @param int $order_id
	 * @return mixed
	 */
	public function get_returns( int $order_id ): mixed {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wemalo_return_pos';
		$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE order_id=%d;", $table_name, $order_id ) );
		if ( $result ) {
			return $result;
		} else {
			return 0;
		}
	}

	/**
	 * Adds a serial number to an order position
	 *
	 * @param int         $item_id
	 * @param string|null $serial_number
	 * @return int
	 * @throws Exception
	 */
	public function add_serial_number( int $item_id, ?string $serial_number = null ): int {
		if ( $serial_number ) {
			$sn = $this->get_serial_number_by_item( $item_id );
			if ( $sn ) {
				// if a serial number was already added, check that the new one is not
				// existing yet and add it if not
				$str    = explode( ',', $sn );
				$exists = false;
				foreach ( $str as $tmp ) {
					if ( $tmp === $serial_number ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					wc_update_order_item_meta( $item_id, self::WEMALO_SERIAL_NUMBER, $sn . ',' . $serial_number );
				}
			} else {
				wc_add_order_item_meta( $item_id, self::WEMALO_SERIAL_NUMBER, $serial_number );
			}
		}
		return 0;
	}

	/**
	 * Add a (batch) lot to an order position
	 *
	 * @param int         $item_id
	 * @param string|null $lot
	 * @return int
	 * @throws Exception
	 */
	public function add_lot( int $item_id, string $lot = null ): int {
		if ( $lot ) {
			$old_lot = $this->get_lot_by_item( $item_id );
			if ( $old_lot ) {
				// if a (batch) lot was already added, check that the new one is not
				// existing yet and add it if not
				$str    = explode( ',', $old_lot );
				$exists = false;
				foreach ( $str as $tmp ) {
					if ( $tmp === $lot ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					wc_update_order_item_meta( $item_id, self::WEMALO_LOT, $old_lot . ',' . $lot );
				}
			} else {
				wc_add_order_item_meta( $item_id, self::WEMALO_LOT, $lot );
			}
		}
		return 0;
	}

	/**
	 * Add a (meta) sku to an order position
	 *
	 * @param int         $item_id
	 * @param string|null $sku
	 * @return int
	 * @throws Exception
	 */
	public function add_meta_sku( int $item_id, ?string $sku = null ): int {
		if ( $sku ) {
			$old_sku = $this->get_meta_sku_by_item( $item_id );
			if ( $old_sku ) {
				// if a (meta) sku was already added, check that the new one is not
				// existing yet and add it if not
				$str    = explode( ',', $old_sku );
				$exists = false;
				foreach ( $str as $tmp ) {
					if ( $tmp === $sku ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					wc_update_order_item_meta( $item_id, self::WEMALO_SKU, $old_sku . ',' . $sku );
				}
			} else {
				wc_add_order_item_meta( $item_id, self::WEMALO_SKU, $sku );
			}
		}
		return 0;
	}

	/**
	 * Returns serial number by position id
	 *
	 * @param int $item_id
	 * @return mixed
	 * @throws Exception
	 */
	public function get_serial_number_by_item( int $item_id ): mixed {
		return wc_get_order_item_meta( $item_id, self::WEMALO_SERIAL_NUMBER );
	}

	/**
	 * returns (batch) lot by position id
	 *
	 * @param int $item_id
	 * @return mixed
	 * @throws Exception
	 */
	public function get_lot_by_item( int $item_id ): mixed {
		return wc_get_order_item_meta( $item_id, self::WEMALO_LOT );
	}

	/**
	 * Returns (meta) sku by position id
	 *
	 * @param int $item_id
	 * @return mixed
	 * @throws Exception
	 */
	public function get_meta_sku_by_item( int $item_id ): mixed {
		return wc_get_order_item_meta( $item_id, self::WEMALO_SKU );
	}

	/**
	 * Gets formatted order number by post id
	 *
	 * @param int $post_id
	 * @return mixed
	 */
	public function get_order_number_formatted_by_post_id( int $post_id ): mixed {
		return get_post_meta( $post_id, get_option( self::WEMALO_ORDER_NUMBER_FORMATTED ), true );
	}

	/**
	 * @param int $post_id
	 * @return mixed
	 */
	public function get_order_category_by_post_id( int $post_id ): mixed {
		return get_post_meta( $post_id, get_option( self::WEMALO_ORDER_CATEGORY_FIELD ), true );
	}

	/**
	 * Transmits an order to wemalo
	 *
	 * @param int           $post_id
	 * @param WC_Order|null $order_obj
	 * @return boolean
	 * @throws Exception
	 */
	public function transmit_order( int $post_id, ?WC_Order $order_obj = null ): bool {
		$order_obj = $order_obj ?? wc_get_order( $post_id );
		$order     = $this->get_order_to_download( $post_id, true, $order_obj, true );
		if ( $order && $order['posCounter'] > 0 ) {
			if ( $this->get_wemalo_api_connect()->transmit_order( $order, $post_id ) ) {
				$this->set_order_downloaded( $post_id, self::WEMALO_DOWNLOAD_ORDER_KEY );
				if ( $this->get_wemalo_api_connect()->is_order_in_fulfillment() ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * If a tracking number was not added before, it will be added now. Otherwise, if it already
	 * exists <code>false</code> will be returned.
	 *
	 * @param string $tracking_number
	 * @param string $current_tracking_numbers
	 * @return string|boolean
	 */
	private function getTrackingNumberEntryIfNotExistsYet( string $tracking_number, string $current_tracking_numbers ): bool|string {
		$current_tracking_number_parts = ( $current_tracking_numbers !== '' ? explode( self::TRACKING_SEPARATOR, $current_tracking_numbers ) : array() );
		if ( ! in_array( $tracking_number, $current_tracking_number_parts ) ) {
			if ( count( $current_tracking_number_parts ) > 0 ) {
				return implode( self::TRACKING_SEPARATOR, $current_tracking_number_parts ) . self::TRACKING_SEPARATOR . $tracking_number;
			} else {
				return $tracking_number;
			}
		}
		return false;
	}

	/**
	 * Adds tracking information to an order
	 *
	 * @param int    $order_id
	 * @param string $tracking_message
	 * @param string $carrier
	 * @return number|array
	 */
	public function add_tracking_info( int $order_id, string $tracking_message, string $carrier = '' ): array|int {
		if ( $tracking_message !== '' ) {
			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );
				if ( ! empty( $order ) ) {
					delete_post_meta( $order_id, 'fulfillment-blocked' );
					$order_meta_array        = get_post_meta( $order_id );
					$current_tracking_number = ( array_key_exists( 'tracking_number', $order_meta_array ) ? $this->get_value( 'tracking_number', $order_meta_array ) : '' );
					$tn                      = $this->getTrackingNumberEntryIfNotExistsYet( $tracking_message, $current_tracking_number );
					if ( $tn ) {
						// add tracking number if not added yet.
						$order->add_order_note( $tracking_message, 0, false );
						update_post_meta( $order_id, 'tracking_number', $tn );
						update_post_meta( $order_id, 'carrier', $carrier );
						$wp_plugin = array_key_exists( '_created_via', $order_meta_array ) ?
						$this->get_value( '_created_via', $order_meta_array ) : false;
						if ( $wp_plugin ) {
							$tn_key      = false;
							$carrier_key = false;
							// get plugin specific keys for tracking number and carrier.
							switch ( $wp_plugin ) {
								case 'amazon':
									$tn_key      = '_wpla_tracking_number';
									$carrier_key = '_wpla_tracking_provider';
									break;
								case 'ebay':
									$tn_key      = '_wpl_tracking_number';
									$carrier_key = '_wpl_tracking_provider';
									break;
							}
							// add tracking number.
							if ( $tn_key ) {
								if ( array_key_exists( $tn_key, $order_meta_array ) ) {
									update_post_meta( $order_id, $tn_key, $tn );
								} else {
									add_post_meta( $order_id, $tn_key, $tn, true );
								}
							}
							// add carrier.
							if ( $carrier_key ) {
								if ( array_key_exists( $carrier_key, $order_meta_array ) ) {
									update_post_meta( $order_id, $carrier_key, $tn );
								} else {
									add_post_meta( $order_id, $carrier_key, $carrier, true );
								}
							}
						}
					}
					return 0;
				}
				return $this->return_error( 31, "Order with ID [$order_id] not found!" );
			}
			return $this->return_error( 30, 'No order id given!' );
		}
		return $this->return_error( 32, 'No tracking code provided!' );
	}

	/**
	 * Update status table
	 *
	 * @param wpdb       $wpdb
	 * @param $table_name
	 */
	public function updateStatus( wpdb $wpdb, $table_name ) {
		$status_array = new WemaloAPIOrderStatusArray();
		foreach ( $status_array->get_status_array() as $status ) {
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET status_display = %s WHERE status_name = %s;", $table_name, $status->get_value(), $status->get_key() ) );
		}
	}
}
