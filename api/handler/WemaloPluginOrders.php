<?php

require_once __DIR__.'/WemaloPluginBase.php';
require_once __DIR__.'/../../plugin/WemaloAPIOrderStatusArray.php';
require_once __DIR__.'/../WemaloAPIConnect.php';

/**
 * handles goods orders
 * @author Patric Eid
 *
 */
class WemaloPluginOrders extends WemaloPluginBase {
	private static string  $TRACKING_SEPARATOR = ",";
	
	public static string $WEMALO_DOWNLOAD_ORDERKEY = "wemalo_download";
	public static string  $WEMALO_CANCEL_ORDERKEY = "wemalo_cancelled";
	public static string  $WEMALO_RETURN_ORDERKEY = "wemalo_return_announced";
	public static string  $WEMALO_PARTIALLY_REASON = "wemalo_partially_reserved";
	public static string  $WEMALO_SERIAL_NUMBER = "_wemalo_serialnumber";
	public static string  $WEMALO_LOT = "_wemalo_lot";
	public static string  $WEMALO_SKU = "_wemalo_sku";
	public static string  $WEMALO_ORDER_NUMBER_FORMATTED = "_wemalo_order_number_formatted";
	public static string  $WEMALO_HOUSE_NUMBER_FIELD = "_wemalo_house_number_field";
	public static string  $WEMALO_PARENT_ORDER_FIELD = "_wemalo_parent_order_field";
	public static string  $WEMALO_ORDER_CATEGORY_FIELD = "_wemalo_order_category_field";

	private ?WemaloAPIConnect $connect = null;

    /**
     * serves an array with shipping or billing address
     * @param array $orderMetaArray
     * @param boolean $isOrder in case of returns we need information differently
     * @return array :NULL
     */
	private function createAddressArray(array $orderMetaArray, bool $isOrder = true): array
    {
		$order_address = array();
		$delivery_address = "_shipping";
		$order_address['name1'] = $this->getValue($delivery_address."_first_name", $orderMetaArray).' '.
			$this->getValue($delivery_address."_last_name", $orderMetaArray);
		$order_address['name2'] = $this->getValue($delivery_address."_company", $orderMetaArray);
		$order_address['street'] = $this->getValue($delivery_address."_address_1", $orderMetaArray);
		$order_address['addressAddition'] = $this->getValue($delivery_address."_address_2", $orderMetaArray);
		$order_address['zip'] = $this->getValue($delivery_address."_postcode", $orderMetaArray);
		$order_address['city'] = $this->getValue($delivery_address."_city", $orderMetaArray);
		$order_address['stateProvinceCountry'] = $this->getValue($delivery_address."_state", $orderMetaArray);

		if (trim(get_option(WemaloPluginOrders::$WEMALO_HOUSE_NUMBER_FIELD)) != "") {
			$order_address['streetNumber'] = $this->getValue(get_option(WemaloPluginOrders::$WEMALO_HOUSE_NUMBER_FIELD), $orderMetaArray);
		}

		if ($isOrder) {
			$order_address['countryCode2Letter'] = $this->getValue($delivery_address."_country", $orderMetaArray);
			$order_address['phoneNumber'] = $this->getValue("_billing_phone", $orderMetaArray);
			$order_address['email'] = $this->getValue("_billing_email", $orderMetaArray);
			$order_address['primary'] = true;
		}
		else {
			$order_address['country'] = $this->getValue($delivery_address."_country", $orderMetaArray);
			$order_address['phone'] = $this->getValue("_billing_phone", $orderMetaArray);
			$order_address['mail'] = $this->getValue("_billing_email", $orderMetaArray);
		}

		return $order_address;
	}

    /**
     * returns the connect client
     * @return WemaloAPIConnect|null
     */
	public function getConnect(): ?WemaloAPIConnect
    {
		if ($this->connect == null) {
			$this->connect = new WemaloAPIConnect();
		}
		return $this->connect;
	}
	
	/**
	 * loads a return object by an order
	 * @param int $postId
	 * @param WC_Order $order
	 * @return array|null
	 */
	public function getReturnObject(int $postId, WC_Order $order): ?array
    {
		//get meta value if key exists
		$value = get_post_meta($postId, WemaloPluginOrders::$WEMALO_RETURN_ORDERKEY, true);
		if (!$value) {
			return $this->buildOrderObj($postId, false);
		}
		return null;
	}
    
    /**
     * loads a return object by an order
     * @param int $postId
     * @param WC_Order $order
     * @return array|null
     */
    public function getReclamationObject(int $postId, WC_Order $order): ?array
    {
        //get meta value if key exists
        $value = get_post_meta($postId, "wemalo_reclam_announced", true);
        if (!$value) {
            return $this->buildOrderObj($postId, false);
        }
        return null;
    }

    /**
     * called for cancelling an order
     * @param int $postId
     * @param boolean $loadData true to load orders data, otherwise the default order object is being returned
     * @param WC_Order|null $order
     * @param Boolean $skipCheck
     * @return Boolean true if order was cancelled
     * @throws Exception
     */
    public function cancelOrder(int $postId, bool $loadData=false, ?WC_Order $order=null, bool $skipCheck=true): bool
    {
        $order = $this->getOrderToDownload($postId, $loadData, $order, $skipCheck);
        if ($order) {
            //an order object was returned, so cancel it in Wemalo as well
            if ($this->getConnect()->cancelOrder($postId)) {
                $this->setOrderDownloaded($postId, WemaloPluginOrders::$WEMALO_CANCEL_ORDERKEY);
                return true;
            }
        }
        return false;
    }

    /**
     * loads an order and returns it as array if it should be transmitted to wemalo-connect
     * @param int $postId
     * @param boolean $loadData true to load orders data, otherwise the default order object is being returned
     * @param WC_Order|null $order
     * @param boolean $skipCheck
     * @return WC_Order|array|null
     */
    public function getOrderToDownload(int $postId, bool $loadData=true, ?WC_Order $order=null, bool $skipCheck=false): WC_Order|array|null
    {
        if ($order == null) {
            $order = wc_get_order($postId);
        }
        //check if order is in status processing and download key hasn't been set yet
        if ($order && ($skipCheck || $this->isProcessing($order))) {
            if ($loadData) {
                // 2017-12-14, Patric Eid: don't check if flag is set since we want to have the possibility updating an order easily
                // 2017-12-20, Patric Eid: we won't checking for download flag anymore,
                // but we'll now be checking if a special order not paid flag has been set.
                // In that case the order will not be transmitted.
                if(!in_array('order_not_paid', get_post_custom_keys($postId))) {
                    return $this->buildOrderObj($postId);
                }
            }
            else {
                return $order;
            }
        }
        return null;
    }
    
    /**
     * returns true if an order is in status processing or fulfillment
     * @param WC_Order $order
     * @return boolean
     */
    public function isProcessingOrFulfillment(WC_Order $order): bool
    {
        return ($this->isProcessing($order) || $this->isFulfill($order));
    }
    
    /**
     * returns true if order is in status processing
     * @param WC_Order $order
     * @return boolean
     */
    public function isProcessing(WC_Order $order): bool
    {
        return (!empty($order) && ( $order->get_status() == 'processing' ||  $order->get_status() == 'order-inprocess') );
    }
    
    /**
     * returns true if order is in status wemalo-fulfill
     * @param WC_Order $order
     * @return boolean
     */
    public function isFulfill(WC_Order $order): bool
    {
        return (!empty($order) && $order->get_status() == 'wemalo-fulfill');
    }
    
    /**
     * returns true if order was set to status return announced
     * @param WC_Order $order
     * @return boolean
     */
    public function isReturnAnnounced(WC_Order $order): bool
    {
        return (!empty($order) && $order->get_status() == 'return-announced');
    }
    
    /**
     * returns true if order was set to status reclam announced
     * @param WC_Order $order
     * @return boolean
     */
    public function isReclamationAnnounced(WC_Order $order): bool
    {
        return (!empty($order) && $order->get_status() == 'reclam-announced');
    }

    /**
     * loads an order by its id
     * @param int $orderId
     * @return array
     */
    public function getOrder(int $orderId): array
    {
        return $this->buildOrderObj($orderId, true, true);
    }

    /**
     * loads order details
     * @param int $postid
     * @param boolean $isOrder in case of announced return, we need different information
     * @param boolean $extendedInfo
     * @return array
     */
    private function buildOrderObj(int $postid, bool $isOrder=true, bool $extendedInfo=false): array
    {
        $order = wc_get_order($postid);
        $orderMetaArray = get_post_meta($postid);
        $meta = array();
        $order_address = $this->createAddressArray($orderMetaArray, $isOrder);
        $order_positions = array();
        $orderItems = $order->get_items();
        $shipping_items = $order->get_items('shipping');
        if ($extendedInfo) {
            $meta['shipping'] = array();
        }
        $order_shipping_method_id = "";
        $shippingFound = false;
        foreach($shipping_items as $el) {
            if (!$shippingFound) {
                $order_shipping_method_id = $el['method_id'];
            }
            if ($order_shipping_method_id) {
                $shippingFound = true;
                if (!$extendedInfo) {
                    break;
                }
            }
            if ($extendedInfo) {
                $meta['shipping'][] = $el['method_id'];
            }
        }
        $receiver = array();
        $receiver[] = $order_address;
        $meta['externalId'] = $postid;
        $meta['externalIdMatch'] = "sku";
        $orderNumber = $postid;
        $orderCategory = '';
        
        if (trim(get_option(WemaloPluginOrders::$WEMALO_ORDER_NUMBER_FORMATTED)) != '') {
            $orderNumber = $this->getOrderNumberFormattedByPostId($postid);
        }
        
        if (trim(get_option(WemaloPluginOrders::$WEMALO_ORDER_CATEGORY_FIELD)) != '') {
            $orderCategory = $this->getOrderCategoryByPostId($postid);
        }
        
        $createWECheck = array_key_exists("_wemalo_return_we", $orderMetaArray) && $orderMetaArray['_wemalo_return_we'][0] == 'yes';
        $createWACheck = array_key_exists("_wemalo_return_wa", $orderMetaArray) && $orderMetaArray['_wemalo_return_wa'][0] == 'yes';
        
        if ($isOrder || $createWACheck) {
            $meta['orderNumber'] = $orderNumber;
            $meta['category'] = $orderCategory;
            $meta['total'] = $order->get_total();
            $meta['totalInvoice'] = $order->get_total();
            $meta['totalVat'] = $order->get_total_tax();
            $meta['currency'] = (method_exists($order, "get_currency") ? $order->get_currency() : "EUR");
            $meta['expectedDeliveryDate'] = array_key_exists("_wemalo_delivery", $orderMetaArray) ? $orderMetaArray['_wemalo_delivery'][0] : "";
            $meta['blocked'] = 0;
            if (array_key_exists("_wemalo_order_blocked", $orderMetaArray) && $orderMetaArray['_wemalo_order_blocked'][0] == "yes") {
                $meta['blocked'] = 1;
            }
            $meta['priority'] = (int)(array_key_exists("_wemalo_priority", $orderMetaArray) ? $orderMetaArray['_wemalo_priority'][0] : "");
            if ($meta['priority'] < 1) {
                $meta['priority'] = 3;
            }
            $profile = (array_key_exists("_wemalo_dispatcher_product", $orderMetaArray) ? $orderMetaArray['_wemalo_dispatcher_product'][0] : "");
            if ($profile == "" || $profile == "auto") {
                $payment_method = get_post_meta($postid, '_payment_method', true);
                if (strtolower($payment_method)=="cod"){
                    $meta['dispatcherProfile'] = $order_shipping_method_id."_cod";
                } else {
                    $meta['dispatcherProfile'] = $order_shipping_method_id;
                }
            }
            else {
                $meta['dispatcherProfile'] = $profile;
            }
            $meta['pickingInfo'] = array_key_exists("_wemalo_return_note", $orderMetaArray) ? $orderMetaArray['_wemalo_return_note'][0] : "";
            $meta['linkedOrderExternalId'] = array_key_exists("_wemalo_linked_order", $orderMetaArray) ? $orderMetaArray['_wemalo_linked_order'][0] : "";
            
        }
        if (!$isOrder || $createWECheck) {
            $meta['deliveryNumber'] = $postid;
            $meta['orderNumberCustomer'] = $orderNumber;
            //check if external id is needed or not
            if (!array_key_exists("wemalo_ignore_parent_order", $orderMetaArray)) {
                //get parent order key by option field if set
                $parentOrderKey = trim(get_option(WemaloPluginOrders::$WEMALO_PARENT_ORDER_FIELD));
                if ($parentOrderKey == '') {
                    //if not set, set it to default key
                    $parentOrderKey = "parent_order_id";
                }
                $meta['goodsOrderExternalId'] = array_key_exists($parentOrderKey, $orderMetaArray) ? $orderMetaArray[$parentOrderKey][0] : $postid;
            }
            $meta['picking_notice'] = array_key_exists("_wemalo_return_note", $orderMetaArray) ? $orderMetaArray['_wemalo_return_note'][0] : "";
            $meta['skipSerialNumberCheck'] = array_key_exists("_wemalo_return_checkserial", $orderMetaArray) && $orderMetaArray['_wemalo_return_checkserial'][0] == 'yes';
        }
        $meta['createWECheck'] = $createWECheck;
        $meta['createWACheck'] = $createWACheck;
        $orderObj = array();
        if ($isOrder) {
            $meta['receiver'] = $receiver;
            $orderObj['meta'] = $meta;
            $orderObj['shop'] = $this->getConnect()->getShopName();
        }
        if (!$isOrder || $createWECheck) {
            //return orders doesn't have meta
            $orderObj = $meta;
            $orderObj['sender'] = $order_address;
            $meta['receiver'] = $receiver;
            $orderObj['meta'] = $meta;
            $orderObj['shop'] = $this->getConnect()->getShopName();
        }
        if ($extendedInfo) {
            $orderObj['fields'] = array();
            foreach ($orderMetaArray as $k=>$v) {
                $orderObj['fields'][$k] = (is_array($v) ? $v[0] : $v);
            }
            $orderObj['fields']['status'] = $order->get_status();
        }
        $positions = array();
        $posCounter = 0;
        $externalPositionIdentifier = 1;

        foreach ($orderItems as $position) {
            $json = json_decode($position);
            $product = array();
            //get variation id if set
            $product['externalId'] = $json->variation_id;
            if ($product['externalId'] <= 0) {
                //otherwise, get product id
                $product['externalId'] = $json->product_id;
            }
            $product['externalPositionIdentifier'] = $json->id;
            $product['name'] = $this->getValue('name', $position);
            $product['quantity'] = $this->getValue('qty', $position);
            if ($product['quantity'] == "") {
                $product['name'] = $json->name;
                $product['quantity'] = $json->quantity;
            }
            $p = $position->get_product();
            if ($extendedInfo) {
                $product['productData'] = json_decode($p);
                $product['positionData'] = $json;
            }
            $product['sku'] = $p->get_sku();
            $product['total'] = $json->total;
            if (is_numeric($product['total'])) {
                $product['total'] = round($product['total'] * 100.0);
            }
            else {
                $product['total'] = 0;
            }
            $product['tax'] = $json->total_tax;
            if (is_numeric($product['tax'])) {
                $product['tax'] = round($product['tax'] * 100.0);
            }
            else {
                $product['tax'] = 0;
            }
            //remember external id but use sku for matching position
            $product['_externalId'] = $product['externalId'];
            $product['externalId'] = $p->get_sku();
            if ($product['externalId'] != "") {
                //add position if external id was set
                if (!method_exists($p, "get_virtual")) {
                    //if method doesn't exist, check in post meta if it's virtual or not
                    $prod_meta = get_post_meta($product['_externalId']);
                    if(!in_array("_virtual", $prod_meta)) {
                        $positions[] = $product;
                        $posCounter++;
                    }
                } else {
                    if(!$p->get_virtual()){
                        $positions[] = $product;
                        $posCounter++;
                    }
                }
            }
        }
        if ($isOrder) {
            $orderObj['positions'] = $positions;
        }
        if (!$isOrder || $createWECheck) {
            $orderObj['items'] = $positions;
            if ($createWACheck) {
                $orderObj['positions'] = $positions;
            }
        }
        $orderObj["posCounter"] = $posCounter;
        return $orderObj;
    }

    /**
     * serves all orders
     * @param $payedOrders
     * @param int $max
     * @return array
     * @throws Exception
     */
    public function getOrders($payedOrders, int $max=200): array
    {
        $my_order_list = array();
        $order_status = ($payedOrders ? array('wc-processing') : WemaloAPIOrderStatusArray::$STATUS_RETURN_ANNOUNCED);
        $metaKey = ($payedOrders ? WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY : WemaloPluginOrders::$WEMALO_RETURN_ORDERKEY);
        $loop = new WP_Query(
            array(
                'post_type' => array('shop_order'),
                'post_status' => $order_status,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key'     => $metaKey,
                        'compare' => 'NOT EXISTS'
                    ),
                )
            ));
        while ($loop->have_posts()): $loop->the_post();
        $postid = get_the_ID();
        
        $myorder = $this->buildOrderObj($postid, $payedOrders);
        if ($myorder["posCounter"] > 0) {
            $my_order_list[] = $myorder;
        }
        
        //set order as downloaded
        $this->setOrderDownloaded($postid, $metaKey);
        
        if (count($my_order_list) >= $max) {
            break;
        }
        endwhile;
        wp_reset_query();
        $order_status = ($payedOrders ? array('wc-processing') : WemaloAPIOrderStatusArray::$STATUS_RECLAMATION_ANNOUNCED);
        $metaKey = ($payedOrders ? WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY : "wemalo_reclam_announced");
        $loop = new WP_Query(
            array(
                'post_type' => array('shop_order'),
                'post_status' => $order_status,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key'     => $metaKey,
                        'compare' => 'NOT EXISTS'
                    ),
                )
            ));
        while ($loop->have_posts()): $loop->the_post();
        $postid = get_the_ID();
        
        $myorder = $this->buildOrderObj($postid, $payedOrders);
        if ($myorder["posCounter"] > 0) {
            $my_order_list[] = $myorder;
        }
        
        //set order as downloaded
        $this->setOrderDownloaded($postid, $metaKey);
        
        if (count($my_order_list) >= $max) {
            break;
        }
        endwhile;
        wp_reset_query();
        return $my_order_list;
    }

    /**
     * checks partially reserved orders in wemalo and updates order status accordingly if
     * order was reserved in the meanwhile
     * @param boolean $checkEachReason true if each partially reserved reason should be checked
     * @param int $postsPerPage
     * @param int $offset
     * @return array true if partially reserved was found
     * @throws Exception
     */
    public function checkPartiallyReserved(bool $checkEachReason = true, int $postsPerPage = 100, int $offset = 0): array
    {
        $hasPartiallyReserved = false;
        $order_status = 'wc-processing';
        $loop = new WP_Query(
            array(
                'post_type' => array('shop_order'),
                'post_status' => array($order_status),
                'posts_per_page' => $postsPerPage+1,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'DESC'
            ));
        $counter = 0;
        $hasMore = false;
        while ($loop->have_posts()): $loop->the_post();
        $counter++;
        if ($counter > $postsPerPage) {
            $hasMore = true;
            break;
        }
        $postid = get_the_ID();
        $transmitOrder = true;
        //check if already transmitted
        if ($this->isOrderDownloaded($postid)) {
            $transmitOrder = false;
            if (!$checkEachReason) {
                //get reason and in some case re-transmit order
                $reason = get_post_meta($postid, WemaloPluginOrders::$WEMALO_PARTIALLY_REASON, true);
                switch ($reason) {
                    case "UNKNOWN":
                    case "ADDRESS":
                        $transmitOrder = true;
                        break;
                    default:
                        //check order status
                        if (!$this->getConnect()->checkOrderStatus($postid)) {
                            //not changed to open, so still partially reserved
                            $hasPartiallyReserved = true;
                        }
                        break;
                }
            }
            else {
                //check order status
                if (!$this->getConnect()->checkOrderStatus($postid)) {
                    //not changed to open, so still partially reserved
                    $hasPartiallyReserved = true;
                }
            }
        }
        if ($transmitOrder) {
            if (!$this->transmitOrder($postid)) {
                $hasPartiallyReserved = true;
            }
        }
        endwhile;
        wp_reset_query();
        return array(
            'hasPartiallyReserved'=>$hasPartiallyReserved,
            'posts'=>$counter,
            'hasMore'=>$hasMore);
    }
    
    /**
     * changes woocommerce order status depending on current wemalo status
     * @param int $postId
     * @param mixed $ret
     * @param string $status
     * @return boolean
     */
    public function handleOrderStatus(int $postId, mixed $ret, string $status): bool
    {
        $orderInFulfillment = false;
        $this->removeOrderNoticeFlags($postId);
        switch ($status) {
            case "OPEN": //reserved and ready for picking
                break;
            case "INPROCESS": //picking list created
                $this->setOrderStatus($postId, WemaloAPIOrderStatusArray::$STATUS_ORDER_INPROCESS);
                break;
            case "INPICKING": //picking started
                break;
            case "READYFORSHIPMENT": //picking finished
                break;
            case "PACKED": //packing finished
                //order was reserved, so change order status accordingly
                $this->setOrderFulfillment($postId);
                $orderInFulfillment = true;
                break;
            case "BLOCKEDPACKED": //packing finished, but blocked
                $this->setOrderFulfillmentBlocked($postId, $ret->blockReason);
                $orderInFulfillment = true;
                break;
            case "PARTIAL":
                update_post_meta($postId, WemaloPluginOrders::$WEMALO_PARTIALLY_REASON,
                $ret->reasonPartiallyReserved);
                break;
            case "CANCELLED":
                $this->setOrderCancelled($postId);
                break;
            case "SHIPPED":
                $this->setOrderStatus($postId, 'wc-completed');
                //$this->setOrderStatus($postId, WemaloAPIOrderStatusArray::$STATUS_ORDER_SHIPPED);
                break;
        }
        return $orderInFulfillment;
    }

    /**
     * called for setting orders as downloaded
     * @param int $order_id
     * @param $meta_key
     * @return boolean
     * @throws Exception
     */
    public function setOrderDownloaded(int $order_id, $meta_key): bool
    {
        if (!$meta_key) {
            $meta_key = WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY;
        }
        add_post_meta($order_id, $meta_key, $this->getCurrentDate(), true);
        return true;
    }
    
    /**
     * checks whether order has been downloaded
     * @param int $order_id
     * @return boolean
     */
    public function isOrderDownloaded(int $order_id): bool
    {
        $value = get_post_meta($order_id, WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY, true);
        if ($value) {
            return true;
        }
        return false;
    }
    
    /**
     * removes order flags
     * @param int $orderId
     */
    private function removeOrderNoticeFlags(int $orderId) {
        delete_post_meta($orderId, WemaloPluginOrders::$WEMALO_PARTIALLY_REASON);
        delete_post_meta($orderId, "fulfillment-blocked");
    }
    
    /**
     * sets the status of a sales order
     * @param int $order_id
     * @param string $order_status
     * @return number|array
     */
    public function setOrderStatus(int $order_id, string $order_status): array|int
    {
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            //delete flags
            $this->removeOrderNoticeFlags($order_id);
            if ($order != null) {
                $order->update_status($order_status);
                return 0;
            }
            return $this->returnError(21, "Order with ID [$order_id] not found!");
        }
        return $this->returnError(20, "No order id given!");
    }
    
    /**
     * save booked return-shipments in DB
     * @param int $order_id
     * @param int $product_id
     * @param int $quantity
     * @param string $choice
     * @param string $reason
     * @param string $serialNumber
     * @param string $lot
     * @param string $sku
     * @return int|array
     */
    public function bookedReturnShipment(int    $order_id, int $product_id, int $quantity, string $choice, string $reason,
                                         string $serialNumber, string $lot, string $sku): array|int
    {
            global $wpdb;
            $table_name = $wpdb->prefix."posts";
            $sql = "SELECT post_title FROM ".$table_name." WHERE ID=".$product_id.";";
            $result = $wpdb->get_row($sql);
            $product_name = $result->post_title;
            $sql = "INSERT INTO ".$wpdb->prefix."wemalo_return_pos
			(order_id, product_id, quantity, choice, return_reason,
			product_name, timestamp, serial_number, lot, sku) ".
			"VALUES (%d,%d,%d,%s,%s,%s,CURRENT_TIMESTAMP,%s,%s,%s)";
            $sql = $wpdb->prepare($sql, $order_id, $product_id, $quantity, $choice,
                $reason, $product_name, $serialNumber, $lot, $sku);
            if($wpdb->query($sql)){
                $order = wc_get_order($order_id);
                if (!empty($order)) {
                    $order->update_status(WemaloAPIOrderStatusArray::$STATUS_RETURN_BOOKED);
                }
                return 1;
            } else {
                return $this->returnError(500, $wpdb->last_error);
            }
    }

    /**
     * sets an order as cancelled (will be called when cancelled in Wemalo!)
     * @param int $order_id
     * @return array|int
     */
    public function setOrderCancelled(int $order_id): array|int
    {
        $order = wc_get_order($order_id);
        if (!empty($order)) {
            //order can be cancelled if in status in processing order wemalo fulfillment only
            if ($this->isProcessing($order) || $this->isFulfill($order)) {
                $order->update_status(WemaloAPIOrderStatusArray::$STATUS_ORDER_CANCELLED);
                return 0;
            }
            return $this->returnError(500, "order status [".
                $order->get_status()."] can't be cancelled.");
        }
        else {
            return $this->returnError(404, "order not found");
        }
    }
    
    /**
     * sets order status fulfillment
     * @param int $order_id
     * @return int|array
     */
    public function setOrderFulfillment(int $order_id): array|int
    {
        $order = wc_get_order($order_id);
        if (!empty($order)) {
            delete_post_meta($order_id, "fulfillment-blocked");
            $order->update_status(WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT);
            return 0;
        }
        else {
            return $this->returnError(404, "order not found");
        }
    }
    
    /**
     * sets order status to fulfillment blocked
     * @param int $order_id
     * @param string $reason
     * @return int|array
     */
    public function setOrderFulfillmentBlocked(int $order_id, string $reason): array|int
    {
        $order = wc_get_order($order_id);
        if (!empty($order)) {
            $order->update_status(WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT_BLOCKED);
            update_post_meta($order_id, "fulfillment-blocked", $reason);
            return 0;
        }
        else {
            return $this->returnError(404, "order not found");
        }
    }
    
    /**
     * serves booked returnshipments for single Order if available
     * @param int $order_id
     * @return mixed
     */
    public function getReturns(int $order_id): mixed
    {
        global $wpdb;
        $table_name = $wpdb->prefix."wemalo_return_pos";
        $sql = "SELECT * FROM ".$table_name." WHERE order_id=".$order_id.";";
        $result = $wpdb->get_results($sql);
        if($result){
            return $result;
        }
        else
            return 0;
    }

    /**
     * adds a serial number to an order position
     * @param int $itemId
     * @param string|null $serialNumber
     * @return int
     * @throws Exception
     */
    public function addSerialNumber(int $itemId, ?string $serialNumber = null): int
    {
        if ($serialNumber) {
            $sn = $this->getSerialNumberByItem($itemId);
            if ($sn) {
                //if a serial number was already added, check that the new one is not
                //existing yet and add it if not
                $str = explode(",", $sn);
                $exists = false;
                foreach ($str as $tmp) {
                    if ($tmp == $serialNumber) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    wc_update_order_item_meta($itemId, WemaloPluginOrders::$WEMALO_SERIAL_NUMBER, $sn . ',' . $serialNumber);
                }
            } else {
                wc_add_order_item_meta($itemId, WemaloPluginOrders::$WEMALO_SERIAL_NUMBER, $serialNumber);
            }
        }
        return 0;
    }

    /**
     * add a (batch) lot to an order position
     * @param int $itemId
     * @param string|null $lot
     * @return int
     * @throws Exception
     */
    public function addLot(int $itemId, string $lot=null): int
    {
        if ($lot) {
            $oldLot = $this->getLotByItem($itemId);
            if ($oldLot) {
                //if a (batch) lot was already added, check that the new one is not
                //existing yet and add it if not
                $str = explode(",", $oldLot);
                $exists = false;
                foreach ($str as $tmp) {
                    if ($tmp == $lot) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    wc_update_order_item_meta($itemId, self::$WEMALO_LOT, $oldLot.','.$lot);
                }
            }
            else {
                wc_add_order_item_meta($itemId, self::$WEMALO_LOT, $lot);
            }
        }
        return 0;
    }

    /**
     * add a (meta) sku to an order position
     * @param int $itemId
     * @param string|null $sku
     * @return int
     * @throws Exception
     */
    public function addMetaSku(int $itemId, ?string $sku=null): int
    {
        if ($sku) {
            $oldSku = $this->getMetaSkuByItem($itemId);
            if ($oldSku) {
                //if a (meta) sku was already added, check that the new one is not
                //existing yet and add it if not
                $str = explode(",", $oldSku);
                $exists = false;
                foreach ($str as $tmp) {
                    if ($tmp == $sku) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    wc_update_order_item_meta($itemId, self::$WEMALO_SKU, $oldSku.','.$sku);
                }
            }
            else {
                wc_add_order_item_meta($itemId, self::$WEMALO_SKU, $sku);
            }
        }
        return 0;
    }

    /**
     * returns serial number by position id
     * @param int $itemId
     * @return mixed
     * @throws Exception
     */
    public function getSerialNumberByItem(int $itemId): mixed
    {
        return wc_get_order_item_meta($itemId, WemaloPluginOrders::$WEMALO_SERIAL_NUMBER);
    }

    /**
     * returns (batch) lot by position id
     * @param int $itemId
     * @return mixed
     * @throws Exception
     */
    public function getLotByItem(int $itemId): mixed
    {
        return wc_get_order_item_meta($itemId, self::$WEMALO_LOT);
    }

    /**
     * returns (meta) sku by position id
     * @param int $itemId
     * @return mixed
     * @throws Exception
     */
    public function getMetaSkuByItem(int $itemId): mixed
    {
        return wc_get_order_item_meta($itemId, self::$WEMALO_SKU);
    }
    
    /**
     * Gets formatted order number by post id
     * @param int $postId
     * @return mixed
     */
    public function getOrderNumberFormattedByPostId(int $postId): mixed
    {
        return get_post_meta( $postId, get_option(WemaloPluginOrders::$WEMALO_ORDER_NUMBER_FORMATTED) , true);
    }

    /**
     * @param int $postId
     * @return mixed
     */
    public function getOrderCategoryByPostId(int $postId): mixed
    {
        return get_post_meta( $postId, get_option(WemaloPluginOrders::$WEMALO_ORDER_CATEGORY_FIELD) , true);
    }

    /**
     * transmitts an order to wemalo
     * @param int $postId
     * @param WC_Order|null $orderObj
     * @return boolean
     * @throws Exception
     */
    public function transmitOrder(int $postId, ?WC_Order $orderObj = null): bool
    {
        if ($orderObj == null) {
            $orderObj = wc_get_order($postId);
        }
        $order = $this->getOrderToDownload($postId, true, $orderObj, true);
        if ($order && $order["posCounter"] > 0) {
            if ($this->getConnect()->transmitOrder($order, $postId)) {
                $this->setOrderDownloaded($postId, WemaloPluginOrders::$WEMALO_DOWNLOAD_ORDERKEY);
                if ($this->getConnect()->getOrderInFulfillment()) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * if a tracking number was not added before, it will be added now. Otherwise, if it already
     * exists <code>false</code> will be returned.
     * @param string $trackingNumber
     * @param string $currentTrackingNumbers
     * @return string|boolean
     */
    private function getTrackingNumberEntryIfNotExistsYet(string $trackingNumber, string $currentTrackingNumbers): bool|string
    {
        $currentTrackingNumberParts = ($currentTrackingNumbers != "" ? explode(WemaloPluginOrders::$TRACKING_SEPARATOR, $currentTrackingNumbers) : array());
        if (!in_array($trackingNumber, $currentTrackingNumberParts)) {
            if (count($currentTrackingNumberParts) > 0) {
                return implode(WemaloPluginOrders::$TRACKING_SEPARATOR, $currentTrackingNumberParts).WemaloPluginOrders::$TRACKING_SEPARATOR.$trackingNumber;
            }
            else {
                return $trackingNumber;
            }
        }
        return false;
    }
    
    /**
     * adds tracking information to an order
     * @param int $order_id
     * @param string $trackingMsg
     * @param string $carrier
     * @return number|array
     */
    public function addTrackingInfo(int $order_id, string $trackingMsg, string $carrier = ""): array|int
    {
        if ($trackingMsg != "") {
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order != null) {
                    delete_post_meta($order_id, "fulfillment-blocked");
                    $orderMetaArray = get_post_meta($order_id);
                    $currentTrackingNumber = (array_key_exists("tracking_number", $orderMetaArray) ?
                        $this->getValue("tracking_number", $orderMetaArray) : "");
                    $tn = $this->getTrackingNumberEntryIfNotExistsYet($trackingMsg, $currentTrackingNumber);
                    if ($tn) {
                        //add tracking number if not added yet
                        $order->add_order_note($trackingMsg,0, false);
                        update_post_meta($order_id, "tracking_number", $tn);
                        update_post_meta($order_id, "carrier", $carrier);
                        $wpPlugin = array_key_exists("_created_via", $orderMetaArray) ?
                        $this->getValue("_created_via", $orderMetaArray) : false;
                        if ($wpPlugin) {
                            $tnKey = false;
                            $carrierKey = false;
                            //get plugin specific keys for tracking number and carrier
                            switch ($wpPlugin) {
                                case "amazon":
                                    $tnKey = '_wpla_tracking_number';
                                    $carrierKey = '_wpla_tracking_provider';
                                    break;
                                case "ebay":
                                    $tnKey = '_wpl_tracking_number';
                                    $carrierKey = '_wpl_tracking_provider';
                                    break;
                            }
                            //add tracking number
                            if ($tnKey) {
                                if (array_key_exists($tnKey, $orderMetaArray)) {
                                    update_post_meta($order_id, $tnKey, $tn);
                                }
                                else {
                                    add_post_meta($order_id, $tnKey, $tn, true);
                                }
                            }
                            //add carrier
                            if ($carrierKey) {
                                if (array_key_exists($carrierKey, $orderMetaArray)) {
                                    update_post_meta($order_id, $carrierKey, $tn);
                                }
                                else {
                                    add_post_meta($order_id, $carrierKey, $carrier, true);
                                }
                            }
                        }
                    }
                    return 0;
                }
                return $this->returnError(31, "Order with ID [$order_id] not found!");
            }
            return $this->returnError(30, "No order id given!");
        }
        return $this->returnError(32, "No tracking code provided!");
    }

    /**
     * update status table
     * @param wpdb $wpdb
     * @param $table_name
     */
    public function updateStatus(wpdb $wpdb, $table_name) {
        $statusArray = new WemaloAPIOrderStatusArray();
        foreach ($statusArray->getStatusArray() as $status) {
            $sql = "UPDATE ".$table_name." SET status_display = '".$status->getValue()."' WHERE status_name = '".$status->getKey()."';";
            $wpdb->query($sql);
        }
    }
}
