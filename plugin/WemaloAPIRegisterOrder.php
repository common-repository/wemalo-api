<?php

require_once __DIR__ . '/../api/WemaloAPIConnect.php';
require_once __DIR__ . '/../api/handler/WemaloPluginOrders.php';
require_once __DIR__ . '/WemaloAPIOrderStatus.php';
require_once __DIR__ . '/WemaloAPIOrderStatusArray.php';
require_once __DIR__ . '/WemaloAPIInstaller.php';
require_once __DIR__ . '/WemaloAPIRegisterAjaxOrder.php';

/**
 * handles filters and actions for orders
 * @author Patric Eid
 *
 */
class WemaloAPIRegisterOrder {

    private ?WemaloAPIOrderStatusArray $orderStatusArray = null;

    /**
     * defines filters and actions
     */
    public function registerEvents() {
        $this->orderStatusArray = new WemaloAPIOrderStatusArray();
        add_filter('wc_order_statuses', array($this, 'getOrderStatuses'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'updateOrder'), 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'addOrderFields'), 10, 3);
        add_action('init', array($this, 'initOrders'), 10, 3);
        //action for loading meta data.. since meta data is displayed automatically,
        //we don't to explicitly show it...
        add_action('woocommerce_before_order_itemmeta', array($this, 'addOrderPositionAddition'), 10, 5);
        add_action('woocommerce_order_status_processing', array($this, 'changedToProcessing'), 10, 1);
        add_action('woocommerce_order_status_return-announced', array($this, 'returnAnnounced'), 10, 1);
        add_action('woocommerce_order_status_reclam-announced', array($this, 'reclamationAnnounced'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'changedToProcessing'), 10, 11);
        //new columns in orders view
        add_filter('woocommerce_shop_order_search_fields', array($this, 'addCustomSearchFields'));
        add_filter("manage_edit-shop_order_sortable_columns", array($this, 'sortCustomFields'));
        add_action('manage_shop_order_posts_custom_column' , array($this, 'loadCustomColumnContent'), 10, 2 );
        add_filter('manage_edit-shop_order_columns', array($this, 'addCustomOrderColumns'), 11);
        add_action('pre_get_posts', array($this, 'orderByMetaFields'));
        //adds a wemalo meta box for showing/storing additional information
        add_action('add_meta_boxes', array($this, 'addWemaloOrderBox'));
        //register ajax events

        $ajaxHandler = new WemaloAPIRegisterAjaxOrder();
        $ajaxHandler->registerEvents();
    }
    
    /**
     * adds a custom wemalo order box
     */
    function addWemaloOrderBox()
    {
        add_meta_box(
            'woocommerce-order-wemalo-orderbox',
            'Wemalo',
            array($this, 'addWemaloOrderBoxContent'),
            'shop_order',
            'side',
            'default'
            );
    }
    
    /**
     * adds content to wemalo custom order box
     * @param stdClass $post
     */
    function addWemaloOrderBoxContent(stdClass $post) {
        //load order
        $order = wc_get_order($post->ID);
        //we need to know the ajax url
        echo '<input type="hidden" name="wemalo_order_id" id="wemalo_order_id" value="'.$post->ID.'" />';
        $handler = new WemaloPluginOrders();
        $orderCreated = true;
        if (!$order || $order->get_date_created() == null) {
            //order not created. But we want to display skipping serial number checks
            $orderCreated = false;
        }
        if ($orderCreated) {
            //TODO: if not processing/fulfillment, some values should not be changable anymore
            //priority
            woocommerce_wp_select(
                array(
                    'id'          => '_wemalo_priority',
                    'label'       => 'Priorität',
                    'class' => 'wemalo-select',
                    'desc_tip'    => 'true',
                    'value'       => get_post_meta($post->ID, '_wemalo_priority', true),
                    'description' => __( 'Priorität des Auftrages in Wemalo', 'woocommerce' ),
                    'options' => array(
                        '3' => "Normal",
                        '2' => "Hoch",
                        '1' => "Sehr hoch"
                    )
                )
                );
            woocommerce_wp_text_input(
                array(
                    'id'          => '_wemalo_linked_order',
                    'label'       => 'Auftrag',
                    'desc_tip'    => 'true',
                    'class' => 'wemalo-select',
                    'value' => get_post_meta($post->ID, '_wemalo_linked_order', true),
                    'description' => __('Verlinkter Auftrag, zum Beispiel für Austausch-Aufträge', 'woocommerce')
                )
                );
            if ($handler->isProcessingOrFulfillment($order)) {
                echo '<button class="wemalo-btn wemalo-btn-default">Speichern</button>';
            }
            if (get_post_meta($post->ID, WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY, true)) {
                if ($handler->isProcessingOrFulfillment($order)) {
                    echo '<hr />';
                    echo '<h4>Dokumenten-Upload</h4>';
                    echo '<form action="" method="POST" class="wemalo_upload_form" enctype="multipart/form-data">';
                    echo '<div id="wemalo_upload_message" class="wemalo-alert" style="display:none;"></div>';
                    echo '<span class="wemalo-btn wemalo-btn-default wemalo-btn-sm" style="display:none;" id="wemalo_upload_order_refresh">Refresh</span>';
                    //upload field if order was already transmitted to wemalo
                    woocommerce_wp_select(
                        array(
                            'id'          => '_wemalo_attachment',
                            'label'       => 'Anhang',
                            'desc_tip'    => 'true',
                            'class' => 'wemalo-select',
                            'description' => __('Upload von Dokumenten nach Wemalo', 'woocommerce'),
                            'options' => array(
                                '' => '',
                                'BILL' => 'Rechnung',
                                'DOCUMENT' => 'Dokument',
                                'GOODSORDERCUSTOMSDOCUMENTS' => 'Zollinhaltserklärung',
                                'GOODSORDERDELIVERYNOTE' => 'Lieferschein'
                            )
                        )
                        );
                    echo '<input style="display:none;" type="file" name="_wemalo_attachment_file" id="_wemalo_attachment_file">';
                    echo '</form>';
                    $celebMail = get_option("_wemalo_order_celeb_email");
                    if ($celebMail != "") {
                        echo '<hr/>';
                        $celebActive = get_post_meta($post->ID, '_wemalo_celeb_active', true);
                        woocommerce_wp_checkbox(
                            array(
                                'id'          => '_wemalo_celeb_active',
                                'label'       => 'Promi',
                                'desc_tip'    => 'true',
                                'value' => $celebActive,
                                'cbvalue' => 'yes',
                                'class' => 'wemalo-select',
                                'description' => __('Promi-Versand', 'woocommerce'),
                            )
                            );
                        echo '<div id="wemalo-celeb-div" '.(
                            $celebActive == "yes" ? "" : ' style="display:none;" '
                            ).'>';
                            woocommerce_wp_textarea_input(
                                array(
                                    'id'          => '_wemalo_celeb',
                                    'label'       => 'Promi-Versand',
                                    'desc_tip'    => 'true',
                                    'description' => __('Text für Promi-Versand an genannte E-Mail-Adresse senden', 'woocommerce'),
                                    'rows' => 10
                                )
                                );
                            echo '<p>Empfänger: '.$celebMail.'</p>';
                            echo '<input type="hidden" id="wemalo_celeb_mail" value="'.$celebMail.'" />';
                            echo '<span class="wemalo-btn wemalo-btn-default" id="wemalo_send_celeb_mail">Senden</span>';
                            echo '</div>';
                            
                            echo '<hr />';
                    }
                }
            }
            echo '<div id="wemalo-dispatcher-div"'.(!$handler->isProcessingOrFulfillment($order) ? " disabled": "").'>';
            woocommerce_wp_select(
                array(
                    'id'          => '_wemalo_dispatcher',
                    'label'       => 'Versand',
                    'desc_tip'    => 'true',
                    'class' => 'wemalo-select',
                    'description' => __('Zeigt alle in Wemalo hinterlegten Versanddienstleister an', 'woocommerce'),
                    'options' => array('' => '')
                )
                );
            woocommerce_wp_select(
                array(
                    'id'          => '_wemalo_dispatcher_product',
                    'label'       => 'Produkt',
                    'desc_tip'    => 'true',
                    'class' => 'wemalo-select',
                    'description' => __('Das zu verwendendende Dienstleisterprodukt', 'woocommerce'),
                    'options' => array('' => '')
                )
                );
            if ($handler->isProcessingOrFulfillment($order)) {
                echo '<button class="wemalo-btn wemalo-btn-default">Speichern</button>';
            }
            else {
                echo '<span>Versanddienstleister nicht änderbar</span>';
            }
            echo '</div>';
            if ($handler->isReturnAnnounced($order) || $handler->isReclamationAnnounced($order)) {
                //return shipment
                echo '<hr />';
                if ($handler->isReclamationAnnounced($order)) {
                    echo '<h4>Reklamation-Upload</h4>';
                } else {
                    echo '<h4>Retouren-Upload</h4>';
                }
                echo '<form action="" method="POST" class="wemalo_upload_form" enctype="multipart/form-data">';
                echo '<div id="wemalo_upload_message" class="wemalo-alert" style="display:none;"></div>';
                echo '<span class="wemalo-btn wemalo-btn-default wemalo-btn-sm" style="display:none;" id="wemalo_upload_order_refresh">Refresh</span>';
                echo '<input type="file" name="_wemalo_attachment_file" id="_wemalo_attachment_file">';
                echo '<small>Upload von Dokumenten und Bildern zu Retouren.</small>';
                echo '<input type="hidden" name="_wemalo_attachment" id="_wemalo_attachment" value="return" />';
                echo '</form>';
                $skipSerialNumberCheck = get_post_meta($post->ID, '_wemalo_return_checkserial', true);
                if ($skipSerialNumberCheck == 'yes') {
                    echo "<p>Seriennummer-Validierung deaktiviert!</p>";
                }
            }
        }
        if ($orderCreated) {
            echo '<hr />';
        }
        echo '<h4>Retoure</h4>';
        $createWECheck = get_post_meta($post->ID, '_wemalo_return_we', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_wemalo_return_we',
                'label'       => 'WE',
                'desc_tip'    => 'true',
                'value' => $createWECheck,
                'class' => 'wemalo-select',
                'description' => __('Retoure auf Wareneingang', 'woocommerce'),
            )
            );
        $createWACheck = get_post_meta($post->ID, '_wemalo_return_wa', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_wemalo_return_wa',
                'label'       => 'WA',
                'desc_tip'    => 'true',
                'value' => $createWACheck,
                'class' => 'wemalo-select',
                'description' => __('Retoure auf Warenausgang', 'woocommerce'),
            )
            );
        $skipSerialNumberCheck = get_post_meta($post->ID, '_wemalo_return_checkserial', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_wemalo_return_checkserial',
                'label'       => 'Kein SN-Check',
                'desc_tip'    => 'true',
                'value' => $skipSerialNumberCheck,
                'cbvalue' => 'yes',
                'class' => 'wemalo-select',
                'description' => __('Verhindert bei Retouren die Prüfung der Seriennummer', 'woocommerce'),
            )
            );
    }

    /**
     * adds a custom search field
     * @param array $search_fields
     * @return array
     */
    public function addCustomSearchFields(array $search_fields): array
    {
        $search_fields[] = WemaloPluginOrders::$WEMALO_PARTIALLY_REASON;
        return $search_fields;
    }

    /**
     * called for sorting custom columns
     * @param array $columns
     * @return array
     */
    function sortCustomFields(array $columns): array
    {
        $columns['wemalo-partially'] = WemaloPluginOrders::$WEMALO_PARTIALLY_REASON;
        $columns['wemalo-download'] = WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY;
        $columns['wemalo-priority'] = '_wemalo_priority';
        $columns['wemalo-fulfill'] = 'fulfillment-blocked';
        return $columns;
    }
    
    /**
     * called for displaying custom fields
     * @param array $column
     * @param int $post_id
     */
    function loadCustomColumnContent(array $column, int $post_id)
    {
        switch ($column)
        {
            case 'wemalo-fulfill':
                echo get_post_meta($post_id, "fulfillment-blocked", true);
                break;
            case 'wemalo-partially':
                echo get_post_meta($post_id, WemaloPluginOrders::$WEMALO_PARTIALLY_REASON, true);
                break;
            case 'wemalo-download':
                echo get_post_meta($post_id, WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY, true);
                break;
            case 'wemalo-priority':
                $prio = get_post_meta($post_id, '_wemalo_priority', true);
                if (is_array($prio)) {
                    if (array_key_exists(0, $prio)) {
                        if (!is_array($prio[0])) {
                            $prio = $prio[0];
                        }
                        else {
                            $prio = 3;
                        }
                    }
                    else {
                        $prio = 3;
                    }
                }
                echo ($prio > 0 && $prio < 4 ? $prio : 3);
                break;
        }
    }
    
    /**
     * allows ordering by meta fields
     * @param $query
     */
    function orderByMetaFields($query) {
        if(!is_admin()) {
            return;
        }
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'fulfillment-blocked':
                $query->set('meta_query', 'fulfillment-blocked');
                $query->set('orderby', 'meta_value');
                break;
            case WemaloPluginOrders::$WEMALO_PARTIALLY_REASON:
                $query->set('meta_query', WemaloPluginOrders::$WEMALO_PARTIALLY_REASON);
                $query->set('orderby', 'meta_value');
                break;
            case WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY:
                $query->set('meta_query', WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY);
                $query->set('orderby', 'meta_value');
                break;
            case '_wemalo_priority':
                $query->set('meta_query', '_wemalo_priority');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    /**
     * adds custom order columns to admin view
     * @param array $columns
     * @return array
     */
    function addCustomOrderColumns(array $columns): array
    {
        $columns['wemalo-priority'] = "Prio";
        $columns['wemalo-partially'] = "Grund";
        $columns['wemalo-fulfill'] = "Blockiert";
        $columns['wemalo-download'] = "Download";
        return $columns;
    }

    /**
     * called when an order was changed to processing
     * @param int $orderId
     * @throws Exception
     */
    public function changedToProcessing(int $orderId) {
        $this->transmitOrder($orderId, new WemaloPluginOrders());
    }

    /**
     * checks whether order status can be changed to fulfillment
     * @param int $post_id
     * @throws Exception
     */
    public function orderLoaded(int $post_id) {
        $order = wc_get_order($post_id);
        if ($order->get_status() == 'processing') {
            //try to reserve goods
            $orders = new WemaloPluginOrders();
            $reload = get_post_meta($post_id, "_wemalo-reload");
            if ($reload && $reload[0] == 1) {
                //re-transmit order
                update_post_meta($post_id, "_wemalo-reload", 0);
                $this->transmitOrder($post_id, $orders, $order);
                echo '<script>location.reload();</script>';
            }
            else if ($orders->isOrderDownloaded($post_id)) {
                $orders->getConnect()->checkOrderStatus($post_id);
                if ($orders->getConnect()->getOrderInFulfillment()) {
                    echo '<script>location.reload();</script>';
                }
            }
            else {
                //if not transmitted yet, try to transmit first
                $this->transmitOrder($post_id, $orders);
            }
        }
    }

    /**
     * adds position additions
     * @param int $item_id
     * @param stdClass $item
     * @param $product
     * @throws Exception
     */
    public function addOrderPositionAddition(int $item_id, stdClass $item, $product) {
        //get quantity
        if ($product != null) {
            echo '<div class="wemalo-position-addition">';
            $quantity = (int) $product->get_stock_quantity();
            echo '<p><small>Verfügbarer Bestand: ';
            if ($quantity > 0) {
                echo '<span style="color:#08c731;">'.$quantity.'</span>';
            }
            else {
                echo '<span style="color:#ec2f10;">'.$quantity.'</span>';
            }
            echo '</small></p>';
            $meta = get_post_meta($product->get_id(), '_wemalo_stock');
            if ($meta && is_array($meta)) {
                if (array_key_exists('totalQuantity', $meta[0])) {
                    echo '<p><small>Reservierbar: ';
                    if ($meta[0]['totalQuantity'] > 0) {
                        echo '<span style="color:#08c731;">Ja</span>';
                    }
                    else {
                        echo '<span style="color:#ec2f10;">Nein</span>';
                    }
                    echo '</small></p>';
                }
            }
            //serial number
            $pluginOrder = new WemaloPluginOrders();
            $sn = $pluginOrder->getSerialNumberByItem($item_id);
            if ($sn) {
                echo '<p><small>Seriennummer: '.$sn.'</small></p>';
            }
            echo '</div>';
        }
    }

    /**
     * adds custom fields to order details
     * @throws Exception
     */
    public function addOrderFields() {
        global $woocommerce, $post;
        //display return info
        $orders = new WemaloPluginOrders();
        if($orders->getReturns(get_the_ID())){
            include dirname(__FILE__).'/../pages/return_template.php';
        }
        //deliverydate
        woocommerce_wp_text_input(
            array(
                'type'        => 'date',
                'id'          => '_wemalo_delivery',
                'label'       => __( 'ETD (Wemalo)', 'woocommerce' ),
                'desc_tip'    => 'true',
                'value'       => get_post_meta( $post->ID, '_wemalo_delivery', true ),
                'description' => __( 'ETD-Feld verwendet von Wemalo.', 'woocommerce' ),
                'input_class' => 'hasDatepicker',
            )
            );
        //notice
        woocommerce_wp_text_input(
            array(
                'id'          => '_wemalo_return_note',
                'label'       => __( 'Notiz (Wemalo)', 'woocommerce' ),
                'desc_tip'    => 'true',
                'value'       => get_post_meta( $post->ID, '_wemalo_return_note', true ),
                'description' => __( 'Notiz-Feld verwendet von Wemalo.', 'woocommerce' )
            )
            );
        //blocked
        $optLbl = get_option("_wemalo_order_blocked_label");
        if ($optLbl == "") {
            $optLbl = "Blockiert";
        }
        woocommerce_wp_checkbox( array(
            'id'    => '_wemalo_order_blocked',
            'type'  => 'checkbox',
            'label' => $optLbl,
            'class' => 'input-checkbox',
            'value' => get_post_meta( $post->ID, '_wemalo_order_blocked', true ),
            'style' => 'width:17px;',
        ));
        $this->orderLoaded($post->ID);
    }

    /**
     * transmitts an order to wemalo
     * @param int $post_id
     * @param WemaloPluginOrders $handler
     * @param WC_Order|null $orderObj
     * @throws Exception
     */
    private function transmitOrder(int $post_id, WemaloPluginOrders $handler, WC_Order $orderObj = null) {
        if (!$handler->transmitOrder($post_id, $orderObj)) {
            //if not in status fulfillment, set up cron job for checking orders status
            $installer = new WemaloAPIInstaller();
            $installer->setUpCronJob();
        }
    }

    /**
     * called when an order is being updated
     * @param int $post_id
     * @throws Exception
     */
	public function updateOrder(int $post_id) {
		$woocommerce_text_field = sanitize_key($_POST['_wemalo_delivery']);
		if( !empty( $woocommerce_text_field ) ) {
			update_post_meta( $post_id, '_wemalo_delivery', $woocommerce_text_field);
		}
		update_post_meta($post_id, '_wemalo_linked_order', sanitize_key($_POST['_wemalo_linked_order']));
		$woocommerce_text_field = sanitize_key($_POST['_wemalo_return_note']);
		if( !empty( $woocommerce_text_field ) ) {
			update_post_meta( $post_id, '_wemalo_return_note', $woocommerce_text_field);
		}
		$woocommerce_text_field = isset( $_POST['_wemalo_order_blocked'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wemalo_order_blocked', $woocommerce_text_field );
		//save priority
		$wasPriority = get_post_meta($post_id, '_wemalo_priority');
		$currentPriority = (int)$_POST['_wemalo_priority'];
		update_post_meta($post_id, '_wemalo_priority', $currentPriority);
		//wareneingang check
		update_post_meta($post_id, '_wemalo_return_we',
			isset($_POST['_wemalo_return_we']) ? sanitize_key($_POST['_wemalo_return_we']) : "");
		//warenausgang check
		update_post_meta($post_id, '_wemalo_return_wa',
			isset($_POST['_wemalo_return_wa']) ? sanitize_key($_POST['_wemalo_return_wa']) : "");
		//skip serial number check
		update_post_meta($post_id, '_wemalo_return_checkserial',
			isset($_POST['_wemalo_return_checkserial']) ? sanitize_key($_POST['_wemalo_return_checkserial']) : "");
		//send to connect?
		$handler = new WemaloPluginOrders();
		$orderObj = wc_get_order($post_id);
		$orderStatus = sanitize_key($_POST['order_status']);
		if ($orderStatus == "wc-processing") {
			//save dispatcher and profile
			if (isset($_POST['_wemalo_dispatcher']) && isset($_POST['_wemalo_dispatcher_product'])) {
				update_post_meta($post_id, '_wemalo_dispatcher', sanitize_key($_POST['_wemalo_dispatcher']));
				update_post_meta($post_id, '_wemalo_dispatcher_product', sanitize_key($_POST['_wemalo_dispatcher_product']));
			}
		}
		else {
			//update priority
			if ($currentPriority != $wasPriority && $handler->isOrderDownloaded($post_id)) {
				if (!$handler->getConnect()->updateOrderPriority($post_id, $currentPriority)) {
					//reset priority on error
					update_post_meta($post_id, '_wemalo_priority', $wasPriority);
				}
			}
		}

		if ($orderStatus == "wc-return-announced" || $orderStatus == "wc-reclam-announced") {
			// 2018-02-09, Patric Eid: handled by separat event
			//check announced returns
			/*$order = $handler->getReturnObject($post_id, $orderObj);
			 if ($order) {
			 //not sent to wemalo yet
			 if ($handler->getConnect()->transmitReturn($order, $post_id)) {
			 $handler->setOrderDownloaded($post_id, WemaloPluginOrders::$WEMALO_RETURN_ORDERKEY);
			 }
			 }*/
		}
		else if ($orderStatus == "wc-cancelled") {
			$this->orderCancelled($post_id);
		}
		else {
			update_post_meta($post_id, "_wemalo-reload", 1);
		}
	}
    
    /**
     * loads order wemalo status
     * @param array $order_statuses
     * @return array
     */
    public function getOrderStatuses(array $order_statuses): array
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
        $getStatusQuery = "SELECT * FROM ".$table_name.";";
        $results = $wpdb->get_results($getStatusQuery);
        $returnAnnounced = false;
        $returnBooked = false;
        $orderCancelled = false;
        $orderFulfillment = false;
        $reclamationAnnounced = false;
        $reclamationBooked = false;
        if (count($results) > 0) {
            foreach ($results as $r) {
                switch ($r->id) {
                    case 0:
                        $returnBooked = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                    case 1:
                        $returnAnnounced = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                    case 2:
                        $orderCancelled = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                    case 3:
                        $orderFulfillment = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                    case 5:
                        $reclamationAnnounced = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                    case 6:
                        $reclamationBooked = true;
                        $this->orderStatusArray->setValue($r->status_name, $r->status_display);
                        break;
                }
            }
        }
        if (!$returnBooked) {
            $order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 0, $order_statuses, $table_name);
        }
        if (!$returnAnnounced) {
            $order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 1, $order_statuses, $table_name);
        }
        if (!$orderCancelled) {
            // 2017-09-11, Patric Eid: wordpress status cancel is being used only
            //$order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 2, $order_statuses, $table_name);
        }
        if (!$orderFulfillment) {
            $order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 3, $order_statuses, $table_name);
        }
        if (!$reclamationAnnounced) {
            $order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 5, $order_statuses, $table_name);
        }
        if (!$reclamationBooked) {
            $order_statuses = $this->orderStatusArray->createOrderStatus($wpdb, 6, $order_statuses, $table_name);
        }
        if (count($results) > 0) {
            foreach ($results as $r) {
                $order_statuses[$r->status_name] = $r->status_display;
            }
        }
        return $order_statuses;
    }
    
    /**
     * called for initializing orders
     */
    public function initOrders() {
        $this->addCustomerOrderStatuses();
    }
    
    /**
     * adds custom order statuses to order list
     */
    public function addCustomerOrderStatuses() {
        $orderStatus = array();
        //update order status
        $orderStatus = $this->getOrderStatuses($orderStatus);
        //register status
        $this->orderStatusArray->registerCustomerOrderStatuses();
        $handler = new WemaloPluginOrders();
        update_option("wemalo_init", $handler->getCurrentDate());
    }

    /**
     * called when an order was set to cancelled and sends a cancel request to wemalo
     * @param int $orderId
     * @throws Exception
     */
    private function orderCancelled(int $orderId): bool
    {
        $handler = new WemaloPluginOrders();
        if( !$handler->cancelOrder($orderId) ){
            sleep(1);
            return $this->orderCancelled($orderId);
        }

        return true;
    }

    /**
     * announcing a return order
     * @param int $post_id
     * @throws Exception
     */
    public function returnAnnounced(int $post_id) {
        $handler = new WemaloPluginOrders();
        $orderObj = wc_get_order($post_id);
        $order = $handler->getReturnObject($post_id, $orderObj);
        if ($order) {
            //not sent to wemalo yet
            if ($handler->getConnect()->transmitReturn($order, $post_id)) {
                $handler->setOrderDownloaded($post_id, WemaloPluginOrders::$WEMALO_RETURN_ORDERKEY);
            }
        }
    }

    /**
     * announcing a reclamation order
     * @param int $post_id
     * @throws Exception
     */
    public function reclamationAnnounced(int $post_id) {
        $handler = new WemaloPluginOrders();
        $orderObj = wc_get_order($post_id);
        $order = $handler->getReclamationObject($post_id, $orderObj);
        if ($order) {
            //not sent to wemalo yet
            if ($handler->getConnect()->transmitReturn($order, $post_id)) {
                $handler->setOrderDownloaded($post_id, "wemalo_reclam_announced");
            }
        }
    }
}