<?php

require_once __DIR__ . '/../api/handler/class-wemalopluginorders.php';
require_once __DIR__ . '/../api/class-wemaloapiconnect.php';
require_once __DIR__ . '/../../woocommerce/packages/action-scheduler/action-scheduler.php';

/**
 * This class is used for setting basic actions, e.g. adding Wemalo to menu and in later versions this class
 * might be used for upgrading to newer versions
 *
 * @author Patric Eid
 */
class WemaloAPIInstaller {

	/**
	 * It stores the support email of the plugin
	 *
	 * @var string
	 */
	private string $support_mail = 'woo-plugin@wemalo.com';

	/**
	 * Adds actions/filters
	 */
	public function register_events() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_head', array( $this, 'add_wemalo_style' ) );
		add_action( 'wemalo_rate_limiter', array( $this, 'wemalo_rate_limiter' ) );

		$this->register_webhooks_to_wemalo();
	}

	/**
	 * Register webhooks to Wemalo Backend for updates
	 *
	 * @return void
	 */
	private function register_webhooks_to_wemalo() {
		$hook = get_option( 'wemalo_hook_token' );
		if ( ! $hook ) {
			$connect = new WemaloAPIConnect();
			$connect->register_webhooks();
		} elseif ( rand( 1, 10 ) > 7 ) {
				$connect = new WemaloAPIConnect();
				$connect->update_webhooks();
		}
	}

	/**
	 * Unregister webhooks from wemalo after plugin is removed
	 *
	 * @return void
	 */
	private function unregister_webhooks_from_wemalo() {
		delete_option( 'wemalo_hook_token' );
		$connect = new WemaloAPIConnect();
		$connect->unregister_webhooks();
	}

	/**
	 * Update the webhooks in the system to the wemalo, if they should be changed.
	 */
	public function update_webhooks() {
		$connect = new WemaloAPIConnect();
		$connect->update_webhooks();
	}

	/**
	 * Stops cron jobs etc. on deactivation
	 * it will be called, when the plugin is disabled
	 */
	public function tear_down() {
		wp_mail( $this->support_mail, 'Deactivate', 'Plugin deactivated: ' . WEMALO_BASE );
		$this->clear_cron_job();
		$this->unregister_webhooks_from_wemalo();
		$this->delete_rate_limiter_table();

		delete_option( 'wemalo_plugin_shop_name' );
		delete_option( 'wemalo_plugin_auth_key' );
	}

	/**
	 * Deactivates checking cron job
	 */
	private function clear_cron_job() {
		wp_clear_scheduled_hook( 'wemalo_status_hook' );
		wp_clear_scheduled_hook( 'wemalo_webhooks_check' );
		as_unschedule_all_actions( 'wemalo_rate_limiter' );
	}

	/**
	 * Sets up the wemalo plugin
	 */
	public function set_up() {
		wp_mail( $this->support_mail, 'Activate', 'Plugin activated ' . WEMALO_BASE );
		$this->create_tables();
		$this->set_up_cron_job();
	}

	/**
	 * Sets up cronJobs
	 */
	public function set_up_cron_job() {
		if ( ! wp_next_scheduled( 'wemalo_status_hook' ) ) {
			wp_schedule_event( time(), 'hourly', 'wemalo_status_hook' );
		}
		add_action( 'wemalo_status_hook', array( $this, 'check_partially_reserved' ) );

		if ( ! wp_next_scheduled( 'wemalo_webhooks_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'wemalo_webhooks_check' );
		}
		add_action( 'wemalo_webhooks_check', array( $this, 'update_webhooks' ) );

		if ( ! as_next_scheduled_action( 'wemalo_rate_limiter' ) ) {
			as_schedule_recurring_action( time(), 60, 'wemalo_rate_limiter' );
		}
	}

	/**
	 * Checks order status of partially reserved orders
	 */
	public function check_partially_reserved() {
		$handler = new WemaloPluginOrders();
		$offset  = get_option( 'wemalo-check-partially-offset' );
		$ret     = $handler->check_partially_reserved( true, 100, $offset ? $offset : 0 );
		if ( $ret['posts'] > 100 ) {
			add_option( 'wemalo-check-partially-offset', $offset++ );
		} else {
			add_option( 'wemalo-check-partially-offset', 0 );
		}
	}

	/**
	 * Creates database tables
	 */
	public function create_tables() {
		// create table for return-shippment.
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		// table for storing information about returned positions.
		$table_name = $wpdb->prefix . 'wemalo_return_pos';
		$sql        = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			order_id mediumint(9) NOT NULL,
			product_id mediumint(9) NOT NULL,
			quantity mediumint(9) NOT NULL,
			choice VARCHAR(255),
			return_reason VARCHAR(255),
			product_name VARCHAR(255),
			timestamp DATETIME,
			serial_number VARCHAR(255),
			lot VARCHAR(255),
			sku VARCHAR(255),
			PRIMARY KEY id (id)
		) $charset_collate;";
		dbDelta( $sql );

		// table for storing custom order statuses.
		$table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
		$sql        = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL,
			status_display VARCHAR(255) NOT NULL,
			status_name VARCHAR(255) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta( $sql );

		// table for storing wemalo tame limiter.
		$table_name = "{$wpdb->prefix}wemalo_timerate_limiter";
		$sql        = "CREATE TABLE $table_name (
				`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`order_id` INT(20) NOT NULL ,
				`function` VARCHAR (255) NOT NULL,
				`status` INT(20) NOT NULL,
				`text` TEXT NOT NULL COLLATE 'utf8_general_ci',
				PRIMARY KEY USING BTREE (`id`),
                UNIQUE INDEX order_id_function USING BTREE (`order_id`, `function`)
			)$charset_collate;";
		dbDelta( $sql );

		// Add new columns.
		$table_name = $wpdb->prefix . 'wemalo_return_pos';
		$row        = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'lot';", $table_name ) );
		if ( empty( $row ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD lot VARCHAR(255);", $table_name ) );
		}
		$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'sku';", $table_name ) );
		if ( empty( $row ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD sku VARCHAR(255);", $table_name ) );
		}

		// update status table.
		$table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
		$handler    = new WemaloPluginOrders();
		$handler->updateStatus( $wpdb, $table_name );
	}

	/**
	 * Create admin-menue for the plugin
	 */
	public function add_menu() {
		add_menu_page(
			'Wemalo API - das Wordpress-Plugin für Wemalo',
			'Wemalo',
			'manage_options',
			__FILE__,
			'wemalo_plugin_user',
			plugin_dir_url( __FILE__ ) . '../images/wemalo.png'
		);
	}

	/**
	 * Adds font icons in order table etc.
	 */
	public function add_wemalo_style() {
		echo '<style>
				.widefat .column-order_status mark.return-booked:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.return-booked:after{
					content:"\e014";
					color:#e37622;
				}
			
				.widefat .column-order_status mark.return-announced:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.return-announced:after{
					content:"\e001";
					color:#e37622;
				}
                .widefat .column-order_status mark.reclam-booked:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.reclam-booked:after{
					content:"\e014";
					color:#e37622;
				}
			
				.widefat .column-order_status mark.reclam-announced:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.reclam-announced:after{
					content:"\e001";
					color:#e37622;
				}
			
			.widefat .column-order_status mark.wemalo-cancel:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.wemalo-cancel:after{
					content:"\e013";
					color:#e37622;
				}
				
				.widefat .column-order_status mark.wemalo-fulfill:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.wemalo-fulfill:after{
					content:"\e019";
					color:#e37622;
				}
				
				.widefat .column-order_status mark.wemalo-block:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
					opacity: 0.5;
				}
				.widefat .column-order_status mark.wemalo-block:after{
					content:"\e019";
					color:#e37622;
				}
				
				.wemalo-table {
					width:100%;
					max-width:800px;
					margin-top: 50px;
				}
				
				.wemalo-table thead {
				    display: table-header-group;
				    vertical-align: middle;
				    border-color: inherit;
				}
				
				.wemalo-table>tbody>tr>td, .wemalo-table>tbody>tr>th, .wemalo-table>tfoot>tr>td, .wemalo-table>tfoot>tr>th, .wemalo-table>thead>tr>td, .wemalo-table>thead>tr>th {
				    padding: 8px;
				    line-height: 1.42857143;
				    vertical-align: top;
				    border-top: 1px solid #ddd;
				}
				
				.wemalo-table>thead>tr>th {
				    vertical-align: bottom;
				    border-bottom: 2px solid #ddd;
					border-top: 0;
				}
	  </style>';
	}

	/**
	 * Handles ratelimiter of wemalo
	 *
	 * @return string|void
	 * @throws Exception
	 */
	public function wemalo_rate_limiter() {
		global $wpdb;
		$table_name   = $wpdb->prefix . 'wemalo_timerate_limiter';
		$last_records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i LATEST LIMIT 1", $table_name ) );

		if ( ! empty( $last_records ) ) {
			foreach ( $last_records as $record ) {

				// cancel order in wemalo.
				$wemalo_api_connect = new WemaloAPIConnect();
				$wemalo_api_connect->cancel_order( $record->order_id );

				// delete record from db.
				$wpdb->delete( $table_name, array( 'id' => $record->id ) );

				return __FUNCTION__ . ' Wemalo Rate Limiter';
			}
		}

		// delete records from action scheduler table
		$wpdb->delete(
			'wp_actionscheduler_actions',
			array(
				'status' => 'complete',
				'hook'   => 'wemalo_rate_limiter',
			)
		);
	}

	/**
	 * Creates database tables for rate limit
	 *
	 * @return void
	 */
	public function delete_rate_limiter_table() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'wemalo_timerate_limiter';
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );
	}
}
