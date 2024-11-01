<?php

require_once __DIR__ . '/class-wemaloapiorderstatus.php';

/**
 * holds wemalo order statuses
 *
 * @author Patric Eid
 */
class WemaloAPIOrderStatusArray {
	private array $status_array = [];

	public const STATUS_RETURN_BOOKED             = 'wc-return-booked';
	public const STATUS_RETURN_ANNOUNCED          = 'wc-return-announced';
	public const STATUS_ORDER_CANCELLED           = 'wc-wemalo-cancel';
	public const STATUS_ORDER_FULFILLMENT         = 'wc-wemalo-fulfill';
	public const STATUS_ORDER_FULFILLMENT_BLOCKED = 'wc-wemalo-block';
	public const STATUS_RECLAMATION_ANNOUNCED     = 'wc-reclam-announced';
	public const STATUS_RECLAMATION_BOOKED        = 'wc-reclam-booked';
	public const STATUS_ORDER_INPROCESS           = 'wc-order-inprocess';
	public const STATUS_ORDER_SHIPPED             = 'wc-order-shipped';

	/**
	 * Builds a default order status array
	 *
	 * @return void
	 */
	private function build_array(): void {
		if ( empty( $this->status_array ) ) {
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_RETURN_BOOKED, 'Retoure gebucht' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_RETURN_ANNOUNCED, 'Retoure angemeldet' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_ORDER_CANCELLED, 'Auftrag storniert' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_ORDER_FULFILLMENT, 'Fulfillment' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_ORDER_FULFILLMENT_BLOCKED, 'Fulfillment blocked' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_RECLAMATION_ANNOUNCED, 'Reklamation angemeldet' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_RECLAMATION_BOOKED, 'Reklamation gebucht' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_ORDER_INPROCESS, 'Auftrag wird bearbeitet' );
			$this->status_array[] = WemaloAPIOrderStatus::create( self::STATUS_ORDER_SHIPPED, 'Auftrag ist versendet' );
		}
	}

	/**
	 * Adds a new order status
	 *
	 * @param wpdb   $wpdb
	 * @param int    $id
	 * @param array  $order_statuses
	 * @param string $table_name
	 * @return array
	 */
	public function create_order_status( wpdb $wpdb, int $id, array $order_statuses, string $table_name ): array {
		$order_status = $this->get_by_id( $id );
		if ( $order_status ) {
			$order_statuses[ $order_status->get_key() ] = _x( $order_status->get_value(), 'Order status', 'WooCommerce' );
			$wpdb->query( $wpdb->prepare( "INSERT INTO %i (id, status_display, status_name) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE status_name = %s", $table_name, $id, $order_status->get_value(), $order_status->get_key(), $order_status->get_key() ) );
		}
		return $order_statuses;
	}

	/**
	 * Updates order statuses
	 *
	 * @param wpdb   $wpdb
	 * @param string $table_name
	 * @param array  $order_status
	 * @param array  $new_status
	 */
	public function update_order_status( wpdb $wpdb, string $table_name, array $order_status, array $new_status ) {
		foreach ( $order_status as $key => $status ) {
			$status_display = ( array_key_exists( $key, $new_status ) && $new_status[ $key ] !== '' ?
				$new_status[ $key ] : $status );
			if ( $status !== $status_display ) {
				$update_status = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status_display = %s WHERE status_name = %s;", $table_name, $status_display, $key ) );
				// update array
				$this->set_value( $key, $status_display );
			}
		}
	}

	/**
	 * Updates an array object
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function set_value( string $key, string $value ) {
		foreach ( $this->get_status_array() as $s ) {
			if ( $s->get_key() === $key ) {
				$s->set_value( $value );
				break;
			}
		}
	}

	/**
	 * Registers order statuses
	 */
	public function register_customer_order_statuses() {
		foreach ( $this->get_status_array() as $order_status ) {
			register_post_status(
				$order_status->get_key(),
				array(
					'label'                     => $order_status->get_value(),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop(
						$order_status->get_value() . ' <span class="count">(%s)</span>',
						$order_status->get_value() . ' <span class="count">(%s)</span>'
					),
				)
			);
			add_option( 'wemalo_' . $order_status->get_key(), $order_status->get_value() );
		}
	}

	/**
	 * Loads status array
	 *
	 * @return WemaloAPIOrderStatusArray()
	 */
	public function get_status_array(): array {
		$this->build_array();

		return $this->status_array;
	}

	/**
	 * Loads an entry by its id
	 *
	 * @param int $id
	 * @return WemaloAPIOrderStatus|null
	 */
	public function get_by_id( int $id ): ?WemaloAPIOrderStatus {
		if ( array_key_exists( $id, $this->get_status_array() ) ) {
			return $this->get_status_array()[ $id ];
		}

		return null;
	}
}
