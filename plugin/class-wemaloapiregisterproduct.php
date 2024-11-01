<?php

require_once __DIR__ . '/../api/class-wemaloapiconnect.php';

/**
 * handles filters and actions for products
 *
 * @author Patric Eid
 */
class WemaloAPIRegisterProduct {

	/**
	 * sets actions
	 */
	public function register_events() {
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'extend_variation_meta_box' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_variation' ), 20, 2 );
		add_action( 'woocommerce_product_options_stock', array( $this, 'add_custom_stock_field' ), 10, 3 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'update_product' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 3 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_wemalo_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'load_wemalo_tab_content' ) );
	}

	/**
	 * loads stock table for main product
	 */
	public function load_wemalo_tab_content() {
		$id = get_the_ID();
		echo '<div id="wemalo_product_data" class="panel woocommerce_options_panel hidden">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_wemalo_ean',
				'label'       => 'EAN',
				'desc_tip'    => 'true',
				'value'       => get_post_meta( $id, '_wemalo_ean', true ),
				'description' => __( 'EAN-Feld verwendet von Wemalo.', 'woocommerce' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => '_wemalo_sn',
				'label'       => 'Seriennummer',
				'desc_tip'    => 'true',
				'value'       => get_post_meta( $id, '_wemalo_sn', true ),
				'description' => __( 'Seriennummer-Verwaltung in Weamlo aktiv?', 'woocommerce' ),
				'options'     => array(
					'i' => 'Nicht aktiviert',
					'a' => 'Aktiviert',
				),
			)
		);

		echo $this->generate_stock_table( $id );
		echo $this->get_last_transmit( $id );
		echo '</div>';
	}

	/**
	 * displays last transmit information
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function get_last_transmit( int $post_id ): string {
		$lastTransmit = get_post_meta( $post_id, '_wemalo_upd_connect' );
		if ( $lastTransmit ) {
			return '<h4>Letzte Übertragung zu Wemalo</h4>' . $lastTransmit[0];
		}
		return '';
	}

	/**
	 * generates stock table by product id
	 *
	 * @param int $post_id
	 * @return string
	 */
	private function generate_stock_table( int $post_id ): string {
		$meta = get_post_meta( $post_id, '_wemalo_stock' );
		if ( $meta && is_array( $meta ) && isset( $meta[0]['rows'] ) &&
			is_array( $meta[0]['rows'] ) && count( $meta[0]['rows'] ) > 0 ) {
			$total_open = 0;
			// The calculation of open / total open has to be done in wemalo-connect, when other values such as "Gesamt" and "Verfügbar" are available.
			$tbl  = '<table class="wemalo-table">';
			$tbl .= '<thead>';
			$tbl .= '<tr>';
			$tbl .= '<th>Charge/SN</th>';
			$tbl .= '<th>Merkmal</th>';
			$tbl .= '<th>Menge</th>';
			$tbl .= '<th>Reserviert</th>';
			$tbl .= '<th>Offen</th>';
			$tbl .= '<th>Verfügbar</th>';
			$tbl .= '</tr>';
			$tbl .= '</thead>';
			$tbl .= '<tbody>';
			foreach ( $meta[0]['rows'] as $row ) {
				$open        = (int) $row['quantity'] - (int) $row['reserved'];
				$total_open += $open;
				$tbl        .= '<tr>';
				$tbl        .= '<td>' . $row['lot'] . '</td>';
				$tbl        .= '<td>' . $row['attr'] . '</td>';
				$tbl        .= '<td style="text-align:right;">' . $row['quantity'] . '</td>';
				$tbl        .= '<td style="text-align:right;">' . $row['reserved'] . '</td>';
				$tbl        .= '<td style="text-align:right;"></td>';
				$tbl        .= '<td style="text-align:right;">' . $open . '</td>';
				$tbl        .= '</tr>';
			}
			$tbl .= '</tbody>';
			if ( array_key_exists( 'totalQuantity', $meta[0] ) ) {
				$product        = wc_get_product( $post_id );
				$currentStock   = $product->get_stock_quantity();
				$tbl           .= '<tfoot>';
				$tbl           .= '<tr>';
				$totalReserved  = ( array_key_exists( 'totalReserved', $meta[0] ) ?
						$meta[0]['totalReserved'] : 0 );
				$tbl           .= '<td colspan="2" style="text-align:right;"><strong>Summe</strong></td>';
				$tbl           .= '<td style="text-align:right;">' . $meta[0]['totalQuantity'] . '</td>';
				$tbl           .= '<td style="text-align:right;">' . $totalReserved . '</td>';
				$not_reserved    = ( array_key_exists( 'totalNotReserved', $meta[0] ) ?
					$meta[0]['totalNotReserved'] : 0 );
				$tbl           .= '<td style="text-align:right;color:#dd0000;">' . $not_reserved . '</td>';
				$availableStock = $meta[0]['totalQuantity'] - $totalReserved;
				if ( $not_reserved > 0 ) {
					$availableStock -= $not_reserved;
				}
				$tbl .= '<td style="text-align:right;">' . $availableStock . '</td>';
				$tbl .= '</tr>';
				$tbl .= '</tfoot>';
			}
			$tbl .= '</table>';
			if ( array_key_exists( 'date', $meta[0] ) ) {
				$tbl .= 'Stand: ' . $meta[0]['date'];
			}
			return $tbl;
		}

		return '';
	}

	/**
	 * adds a wemalo tab
	 *
	 * @param array $tabs
	 * @return array
	 */
	public function add_wemalo_tab( array $tabs ): array {
		$tabs['wemalo_tab'] = array(
			'label'    => 'Wemalo',
			'priority' => 150,
			'class'    => array( 'wemalo_product_data' ),
			'target'   => 'wemalo_product_data',
		);
		return $tabs;
	}

	/**
	 * called when a post is being saved
	 *
	 * @param int     $post_id
	 * @param WP_POST $post
	 * @param boolean $update
	 * @throws Exception
	 */
	public function save_post( int $post_id, WP_POST $post, bool $update ) {
		$connect = new WemaloAPIConnect();
		update_post_meta( $post_id, '_wemalo_upd_connect', $connect->transmit_product_data( $post ) );
	}

	/**
	 * adds a custom field to products stock panel
	 */
	public function add_custom_stock_field() {
		global $woocommerce, $post;
		// b stock
		woocommerce_wp_text_input(
			array(
				'id'          => '_wemalo_bstock',
				'label'       => __( 'B-Warenbestand', 'woocommerce' ),
				'desc_tip'    => 'true',
				'value'       => get_post_meta( $post->ID, '_wemalo_bstock', true ),
				'description' => __( 'B-Warenbestand in Wemalo. Das Textfeld kann auch C-Ware enthalten.', 'woocommerce' ),
			)
		);
	}

	/**
	 * called when updating a product
	 *
	 * @param int $post_id
	 */
	public function update_product( int $post_id ) {
		// ean
		$woocommerce_text_field = sanitize_text_field( $_POST['_wemalo_ean'] );
		if ( ! empty( $woocommerce_text_field ) ) {
			update_post_meta( $post_id, '_wemalo_ean', $woocommerce_text_field );
		}
		// b stock
		$woocommerce_text_field = sanitize_text_field( $_POST['_wemalo_bstock'] );
		if ( ! empty( $woocommerce_text_field ) ) {
			update_post_meta( $post_id, '_wemalo_bstock', $woocommerce_text_field );
		}
		// serial number
		$woocommerce_text_field = sanitize_text_field( $_POST['_wemalo_sn'] );
		if ( ! empty( $woocommerce_text_field ) ) {
			update_post_meta( $post_id, '_wemalo_sn', $woocommerce_text_field );
		}
	}

	/**
	 * Save extra meta info for variable products
	 *
	 * @param int $variation_id
	 * @param int $i
	 * return void
	 * @throws Exception
	 */
	public function save_product_variation( int $variation_id, int $i ) {
		// save b stock
		if ( isset( $_POST['variation_wemalo_bstock'][ $i ] ) ) {
			// sanitize data in way that makes sense for your data type
			$custom_data = ( trim( $_POST['variation_wemalo_bstock'][ $i ] ) === '' ) ? '' : sanitize_title( $_POST['variation_wemalo_bstock'][ $i ] );
			update_post_meta( $variation_id, '_wemalo_bstock', $custom_data );
		}
		// save ean
		if ( isset( $_POST['variation_wemalo_ean'][ $i ] ) ) {
			// sanitize data in way that makes sense for your data type
			$custom_data = ( trim( $_POST['variation_wemalo_ean'][ $i ] ) === '' ) ? '' : sanitize_title( $_POST['variation_wemalo_ean'][ $i ] );
			update_post_meta( $variation_id, '_wemalo_ean', $custom_data );
		}
		if ( isset( $_POST['variation_wemalo_sn'][ $i ] ) ) {
			// sanitize data in way that makes sense for your data type
			$custom_data = ( trim( $_POST['variation_wemalo_sn'][ $i ] ) === '' ) ? '' : sanitize_title( $_POST['variation_wemalo_sn'][ $i ] );
			update_post_meta( $variation_id, '_wemalo_sn', $custom_data );
		}
		$this->save_post( $variation_id, get_post( $variation_id ), true );
	}

	/**
	 * Add new inputs to each variation
	 *
	 * @param string    $loop
	 * @param array     $variation_data
	 * @param $variation
	 */
	public function extend_variation_meta_box( string $loop, array $variation_data, $variation ) {
		$bStock = get_post_meta( $variation->ID, '_wemalo_bstock', true );
		$ean    = get_post_meta( $variation->ID, '_wemalo_ean', true );
		$sn     = get_post_meta( $variation->ID, '_wemalo_sn', true );
		echo '<div class="variable_custom_field">
	            <p class="form-row form-row-first">
	                <label>B-Warenbestand:</label>
	                <input type="text" size="50" name="variation_wemalo_bstock[' . $loop . ']" value="' . sanitize_text_field( $bStock ) . '" />
	            </p>
	        </div>';
		echo '<div class="variable_custom_field">
	            <p class="form-row form-row-first">
	               <label>EAN (Wemalo):</label>
	               <input type="text" size="50" name="variation_wemalo_ean[' . $loop . ']" value="' . sanitize_text_field( $ean ) . '" />
	            </p>
	        </div>';
		echo '<div class="variable_custom_field">
	            <p class="form-row form-row-first">
	               <label>Seriennummer aktiv:</label>
	               <select name="variation_wemalo_sn[' . $loop . ']">
	               <option value="i"' . ( $sn !== 'a' ? ' selected' : '' ) . '>Nicht aktiviert</option>
	               <option value="a"' . ( $sn === 'a' ? ' selected' : '' ) . '>Aktiviert</option>
	               </select>
	            </p>
	        </div>';
		// load stock information
		echo $this->generate_stock_table( $variation->ID );
		echo $this->get_last_transmit( $variation->ID );
	}
}
