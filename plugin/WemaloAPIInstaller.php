<?php

require_once __DIR__ . '/../api/handler/WemaloPluginOrders.php';
require_once __DIR__ . '/../api/WemaloAPIConnect.php';
require_once __DIR__ . '/../../woocommerce/packages/action-scheduler/action-scheduler.php';

/**
 * is used for setting basic actions, e.g. adding Wemalo to menu and in later versions this class
 * might be used for upgrading to newer versions
 * @author Patric Eid
 *
 */
class WemaloAPIInstaller {
	private string $supportMail = "woo-plugin@wemalo.com";
	
	/**
	 * adds actions/filters
	 */	
	public function registerEvents() {
		add_action('admin_menu', array($this, 'addMenu'));
		add_action('admin_head', array($this, 'addWemaloStyle'));
        add_action('wemalo_rate_limiter',[$this,'wemaloRateLimiter']);

		$this->registerWebhooksToWemalo();
	}

	private function registerWebhooksToWemalo(){
	    $hook = get_option("wemalo_hook_token");
	    if (!$hook){
	        $connect = new WemaloAPIConnect();
	        $connect->registerWebHooks();
        }else{
	        if(rand(1, 10) > 7){
                $connect = new WemaloAPIConnect();
                $connect->updateWebHooks();
            }
        }
    }

    private function unregisterWebhooksFromWemalo()
    {
        delete_option("wemalo_hook_token");
        $connect = new WemaloAPIConnect();
        $connect->unregisterWebHooks();
    }

    /**
     * Update the webhooks in the system to the wemalo, if they should be changed.
     *
     */
    public function updateWebhooks(){
        $connect = new WemaloAPIConnect();
        $connect->updateWebHooks();
    }

	/**
	 * stops cron jobs etc. on deactivation
     * it will be called, when the plugin is disabled
	 */
	public function tearDown() {
		//wp_mail($this->supportMail, "Deactivate", "Plugin deactivated: ".WEMALO_BASE);
		$this->clearCronJob();
		//unregister status update webhook
		$this->unregisterWebhooksFromWemalo();

        //delete tables from DB
        $this->deleteRateLimiterTable();

        delete_option("wemalo_plugin_shop_name");
        delete_option("wemalo_plugin_auth_key");
	}
	
	/**
	 * deactivates checking cron job
	 */
	private function clearCronJob() {
        wp_clear_scheduled_hook('wemalo_status_hook');
        wp_clear_scheduled_hook('wemalo_webhooks_check');
        as_unschedule_all_actions('wemalo_rate_limiter');
	}
	
	/**
	 * sets up the wemalo plugin
	 */
	public function setUp() {
		//wp_mail($this->supportMail, "Activate", "Plugin activated ".WEMALO_BASE);
		//create tables
		$this->createTables();
		//set up cron job
		$this->setUpCronJob();
	}
	
	/**
	 * sets up cronJobs
	 */
	public function setUpCronJob() {
		if (!wp_next_scheduled('wemalo_status_hook')) {
			wp_schedule_event(time(), 'hourly', 'wemalo_status_hook');
		}
		add_action('wemalo_status_hook', array($this, 'checkPartiallyReserved'));

        if (!wp_next_scheduled('wemalo_webhooks_check')) {
            wp_schedule_event(time(), 'hourly', 'wemalo_webhooks_check');
        }
        add_action('wemalo_webhooks_check', array($this, 'updateWebhooks'));

        if (!as_next_scheduled_action('wemalo_rate_limiter')) {
	        as_schedule_recurring_action( time(), 60, 'wemalo_rate_limiter' );
        }
	}

	/**
	 * checks order status of partially reserved orders
	 */
	public function checkPartiallyReserved() {
		$handler = new WemaloPluginOrders();
		$offset = get_option("wemalo-check-partially-offset");
		$ret = $handler->checkPartiallyReserved(true, 100, $offset ? $offset : 0);
		if ($ret['posts'] > 100) {
			add_option("wemalo-check-partially-offset", $offset++);
		}
		else {
			add_option("wemalo-check-partially-offset", 0);
		}
	}
	
	/**
	 * creates database tables
	 */
	public function createTables() {
		//create table for return-shippment
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$charset_collate = $wpdb->get_charset_collate();
		//table for storing information about returned positions
		$table_name = $wpdb->prefix."wemalo_return_pos";
		$sql = "CREATE TABLE $table_name (
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
		dbDelta($sql);
		
		//table for storing custom order statuses
		$table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL,
			status_display VARCHAR(255) NOT NULL,
			status_name VARCHAR(255) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
		dbDelta($sql);
		
		//table for storing wemalo tame limiter
		$table_name = "{$wpdb->prefix}wemalo_timerate_limiter";
		$sql = "CREATE TABLE $table_name (
				`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`order_id` INT(20) NOT NULL ,
				`function` VARCHAR (255) NOT NULL,
				`status` INT(20) NOT NULL,
				`text` TEXT NOT NULL COLLATE 'utf8_general_ci',
				PRIMARY KEY USING BTREE (`id`),
                UNIQUE INDEX order_id_function USING BTREE (`order_id`, `function`)
			)$charset_collate;";
		dbDelta($sql);

		// Add new columns
		$table_name = $wpdb->prefix."wemalo_return_pos";
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = 'lot';" );
		if(empty($row)){
		    $wpdb->query("ALTER TABLE $table_name ADD lot VARCHAR(255);");
		}
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = 'sku';" );
		if(empty($row)){
		    $wpdb->query("ALTER TABLE $table_name ADD sku VARCHAR(255);");
		}
		
		// update status table
		$table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
		$handler = new WemaloPluginOrders();
		$handler->updateStatus($wpdb, $table_name);
	}
	
	/**
	 * create admin-menue for the plugin
	 */
	public function addMenu() {
		add_menu_page('Wemalo API - das Wordpress-Plugin für Wemalo', 'Wemalo', 'manage_options', __FILE__,
		'wemalo_plugin_user', plugin_dir_url(__FILE__).'../images/wemalo.png');
	}
	
	/**
	 * adds font icons in order table etc.
	 */
	public function addWemaloStyle() {
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

	public function wemaloRateLimiter() {
		global $wpdb;
        $table_name = $wpdb->prefix.'wemalo_timerate_limiter';
		$lastRecords = $wpdb->get_results("SELECT * FROM " . $table_name . " LATEST LIMIT 1");

		if ($lastRecords !== null){
			foreach ($lastRecords as $record){

				//cancel in wemalo

				$wemaloAPIConnect = new WemaloAPIConnect();
				$wemaloAPIConnect->cancelOrder($record->order_id);

				//delete record from db

				$wpdb->delete($table_name,['id'=>$record->id]);

				return __FUNCTION__." Wemalo Rate Limiter";
			}
		}

		//delete records from action scheduler table
		$wpdb->delete('wp_actionscheduler_actions',['status'=>'complete','hook'=>'wemalo_rate_limiter']);

	}

	public function deleteRateLimiterTable(){

		global $wpdb;

		$table_name = $wpdb->prefix.'wemalo_timerate_limiter';
		$sql = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query($sql);
	}



}
