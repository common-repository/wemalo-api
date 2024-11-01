<?php

require_once __DIR__ . '/class-wemalopluginbase.php';

/**
 * handles setting stock changes
 *
 * @author Patric Eid
 */
class WemaloPluginStock extends WemaloPluginBase {
	/**
	 * sets stock value for a product
	 *
	 * @param int $post_id
	 * @param int $wemalo_new_stock
	 * @return array
	 */
	public function set_stock( int $post_id, int $wemalo_new_stock ): array {
		if ( $post_id > 0 ) {
			$product = wc_get_product( $post_id );
			if ( ! empty( $product ) ) {
				$new_stock = max( 0, (int) $wemalo_new_stock );
				wc_update_product_stock( $product, $new_stock );
			} else {
				return $this->return_error( 12, "Product [$post_id] not found!" );
			}
		}
		return $this->return_error( 10, 'No post id given!' );
	}

	/**
	 * sets absolute stock value for a product
	 *
	 * @param int $post_id
	 * @param int $wemalo_new_stock
	 * @return number|array
	 */
	public function newStock( int $post_id, int $wemalo_new_stock ): array|int {
		if ( $post_id > 0 ) {
			$product = wc_get_product( $post_id );
			if ( ! empty( $product ) ) {
				$new_stock = (int) $wemalo_new_stock;
				wc_update_product_stock( $product, $new_stock, 'set' );
				return 0;
			} else {
				return $this->return_error( 12, "Product [$post_id] not found!" );
			}
		}
		return $this->return_error( 10, 'No post id given!' );
	}

	/**
	 * adds stock information to post meta
	 *
	 * @param int   $post_id
	 * @param array $data
	 * @param int   $not_reserved
	 * @return int|bool
	 * @throws Exception
	 */
	public function addLotInformation( int $post_id, array $data, int $not_reserved = 0 ): int|bool {
		$meta                     = array();
		$meta['totalQuantity']    = 0;
		$meta['totalReserved']    = 0;
		$meta['totalNotReserved'] = $not_reserved;
		$meta['date']             = $this->get_current_date();
		if ( count( $data ) > 0 ) {
			$meta['rows'] = array();
			foreach ( $data as $item ) {
				// create a row per lot and attribute
				$lot = array_key_exists( 'lot', $item ) ? $item['lot'] : '-';
				if ( array_key_exists( 'attributes', $item ) && ! empty( $item['attributes'] ) ) {
					foreach ( $item['attributes'] as $attr ) {
						$row                    = array();
						$row['lot']             = $lot;
						$row['attr']            = array_key_exists( 'attribute', $attr ) ? $attr['attribute'] : '';
						$row['quantity']        = array_key_exists( 'quantity', $attr ) ? $attr['quantity'] : 0;
						$row['reserved']        = array_key_exists( 'reserved', $attr ) ? $attr['reserved'] : 0;
						$meta['rows'][]         = $row;
						$meta['totalQuantity'] += $row['quantity'];
						$meta['totalReserved'] += $row['reserved'];
					}
				}
			}
		}
		return update_post_meta( $post_id, '_wemalo_stock', $meta );
	}

	/**
	 * sets absolute B-stock-value for a product
	 *
	 * @param int $post_id
	 * @param int $wemalo_new_stock
	 * @return number|array
	 */
	public function setBStock( int $post_id, int $wemalo_new_stock ): array|int {
		if ( $post_id > 0 ) {
			$product = wc_get_product( $post_id );
			if ( ! empty( $product ) ) {
				update_post_meta( $post_id, '_wemalo_bstock', $wemalo_new_stock );
				return 0;

			} else {
				return $this->return_error( 12, "Product [$post_id] not found!" );
			}
		}
		return $this->return_error( 10, 'No post id given!' );
	}
}
