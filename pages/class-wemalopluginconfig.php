<?php

require_once __DIR__ . '/../plugin/class-wemaloapiorderstatus.php';
require_once __DIR__ . '/../plugin/class-wemaloapiorderstatusarray.php';

/**
 * Class to hold the configuration fields of plugin
 */
class WemaloPluginConfig {

	/**
	 * Adds an input field for saving user auth key
	 */
	private function set_user_auth_key() {
		$auth_key_editor_id = 'authkeyarea';
		$option             = 'wemalo_plugin_auth_key';

		if ( isset( $_REQUEST['generate_new_token'] ) ) {
			$value = isset( $_REQUEST[ $auth_key_editor_id ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $auth_key_editor_id ] ) ) : '';
			if ( ! add_option( $option, $value ) ) {
				update_option( $option, $value );
			}
			$api = new WemaloAPIConnect();
			$api->update_webhooks( true );

		} elseif ( isset( $_REQUEST[ $auth_key_editor_id ] ) && get_option( $option ) !== sanitize_text_field( wp_unslash( $_REQUEST[ $auth_key_editor_id ] ) ) ) {
			$value = sanitize_text_field( wp_unslash( $_REQUEST[ $auth_key_editor_id ] ) );
			if ( ! add_option( $option, $value ) ) {
				update_option( $option, $value );
			}

			$api = new WemaloAPIConnect();
			$api->update_webhooks( false );
		}

		$content = get_option( $option );

		echo '<p class="wemalo-config"><input required style="width:750px;" type="text" name="' . esc_attr( $auth_key_editor_id ) . '" id="' . esc_attr( $auth_key_editor_id ) .
			'" class="form-control" aria-describedby="' . esc_attr( $auth_key_editor_id ) . '_help" value="' . esc_attr( $content ) . '" placeholder="Bitte Authkey eintragen"></p>';
		$hook = get_option( 'wemalo_hook_token' );
		if ( $hook ) {
			echo '<p>Webhook-Token: ' . esc_html( $hook ) . '</p>';
		}
	}


	/**
	 * Adds a label for setting an order as blocked
	 */
	private function set_check_label() {
		$option_name = '_wemalo_order_blocked_label';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Blockiert</label>';
		echo '<input required type="text" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
			'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) . '" placeholder="Bitte Bezeichnung eintragen"></p>';
	}

	/**
	 * Displays an email field for entering the receiver mail for celebrity mails
	 */
	private function set_celeb_email() {
		global $wpdb;
		$option_name = '_wemalo_order_celeb_email';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Promi-Versand</label>';
		echo '<input type="email" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
		'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) . '" placeholder="Bitte E-Mail-Adresse eintragen"></p>';
	}

	/**
	 * Displays an input field for entering the formatted order number
	 */
	private function set_formatted_order_number() {
		global $wpdb;
		$option_name = '_wemalo_order_number_formatted';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Auftragsnummer</label>';
		echo '<input type="text" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
			'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) . '" placeholder="Bitte das Feld für Auftragsnummer eintragen"></p>';
	}

	/**
	 * Displays an input field for setting a custom house number field
	 */
	private function set_house_number_field() {
		global $wpdb;
		$option_name = '_wemalo_house_number_field';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Hausnummer</label>';
		echo '<input type="text" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
			'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) . '" placeholder="Bitte das Feld für Hausnummer eintragen"></p>';
	}

	/**
	 * Displays a field for setting a parent order field
	 */
	private function set_parent_order_field() {
		global $wpdb;
		$option_name = '_wemalo_parent_order_field';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Parent-Order (Retoure)</label>';
		echo '<input type="text" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
			'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) .
			'" title="Beinhaltet ein Matching-Feld zum Hinterlegen eines Custom Fields für Vater-Aufträge" placeholder="Auftrag-ID"></p>';
	}

	/**
	 * Display a field for setting the category
	 */
	private function set_order_category_field() {
		$option_name = '_wemalo_order_category_field';
		$old_value   = get_option( $option_name );
		$new_value   = array_key_exists( $option_name, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) ) : $old_value;
		update_option( $option_name, $new_value );
		echo '<p class="wemalo-config"><label for="' . esc_attr( $option_name ) . '">Category</label>';
		echo '<input type="text" name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $option_name ) .
		'" class="form-control" value="' . esc_attr( get_option( $option_name ) ) .
		'" title="Beinhaltet ein Matching-Feld zum Hinterlegen eines Custom Fields für Auftragskategorie" placeholder="Kategorie"></p>';
	}

	/**
	 * Generates form elements
	 */
	private function set_status_strings() {
		global $wpdb;

		$order_status = wc_get_order_statuses();
		$table_name   = "{$wpdb->prefix}wemalo_custom_orderstatus";
		$new_status   = array();
		$status_array = new WemaloAPIOrderStatusArray();

		// Sanitize request parameters.
		foreach ( $status_array->get_status_array() as $status ) {
			$key                = $status->get_key();
			$new_status[ $key ] = array_key_exists( $key, $_REQUEST ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
		}

		// Define statuses to check.
		$status_checks = array(
			WemaloAPIOrderStatusArray::STATUS_ORDER_CANCELLED,
			WemaloAPIOrderStatusArray::STATUS_RETURN_ANNOUNCED,
			WemaloAPIOrderStatusArray::STATUS_RETURN_BOOKED,
			WemaloAPIOrderStatusArray::STATUS_ORDER_FULFILLMENT,
			WemaloAPIOrderStatusArray::STATUS_ORDER_FULFILLMENT_BLOCKED,
			WemaloAPIOrderStatusArray::STATUS_RECLAMATION_ANNOUNCED,
			WemaloAPIOrderStatusArray::STATUS_RECLAMATION_BOOKED,
			WemaloAPIOrderStatusArray::STATUS_ORDER_INPROCESS,
			WemaloAPIOrderStatusArray::STATUS_ORDER_SHIPPED,
		);

		$found_statuses = array_fill_keys( $status_checks, false );

		// Check existing order statuses.
		foreach ( $order_status as $key => $status ) {
			if ( array_key_exists( $key, $found_statuses ) ) {
				$found_statuses[ $key ] = true;
				$status_array->set_value( $key, $status );
			}
		}

		// Create missing statuses.
		$reload_status = false;
		foreach ( $found_statuses as $key => $found ) {
			if ( ! $found ) {
				$index         = array_search( $key, $status_checks, true );
				$order_status  = $status_array->create_order_status( $wpdb, $index, $order_status, $table_name );
				$reload_status = true;
			}
		}

		if ( $reload_status ) {
			// Reload the order statuses.
			$order_status = wc_get_order_statuses();
		}

		// Update database and register statuses.
		$status_array->update_order_status( $wpdb, $table_name, $order_status, $new_status );
		$status_array->register_customer_order_statuses();

		// Display the status fields.
		foreach ( $status_array->get_status_array() as $order_status ) {
			echo '<p class="wemalo-config"><label for="' . esc_attr( $order_status->get_key() ) . '">' . esc_html( $order_status->get_key() ) . '</label>';
			echo '<input required type="text" name="' . esc_attr( $order_status->get_key() ) . '" id="' . esc_attr( $order_status->get_key() ) . '" class="form-control" value="' . esc_attr( $order_status->get_value() ) . '" placeholder="Bitte Bezeichnung eintragen"></p>';
		}
	}

	/**
	 * Loads the markup html
	 *
	 * @return void
	 */
	public function build_markup() {
		include __DIR__ . '/wemaloplugin-config-html.php';
	}
}
