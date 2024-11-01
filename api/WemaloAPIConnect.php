<?php

require_once __DIR__.'/WemaloAPIException.php';
require_once __DIR__.'/WemaloAPIBasic.php';
require_once __DIR__.'/handler/WemaloPluginProducts.php';
require_once __DIR__.'/handler/WemaloPluginOrders.php';

/**
 * contains function for connecting against wemalo-connect api
 * @author Patric Eid
 *
 */
class WemaloAPIConnect extends WemaloAPIBasic
{

	private string $token = "";
	private string $shop = "";
	private bool $debugMode = false;
	private bool $orderInFulfillment = false;
	private string $shopName = "";
	private array $webhooks = [];
	
	function __construct() {
		$this->token = get_option('wemalo_plugin_auth_key');
		$this->shop = get_option('wemalo_plugin_shop_name');

        $this->webhooks["status_update"]        =  get_home_url().'/?rest_route=/rest/v1/wemalo/order/hook/status/';
        $this->webhooks["trackingnumber_added"] =  get_home_url().'/?rest_route=/rest/v1/wemalo/order/hook/track/';

		$this->loadShopName();
	}

    /**
     * returns true if last submitted order has been changed to fulfillment
     * @return boolean
     */
    public function getOrderInFulfillment(): bool
    {
        return $this->orderInFulfillment;
    }

	/**
	 * loads shopname from connect
	 * @return void
     */
	private function loadShopName(): void
    {
		if ($this->shop == "" && $this->token != "") {
			//load shop name from wemalo-connect
			$result = $this->_GET("product/shop");
			if ($result != null && isset($result->shop)) {
				$this->shop = $result->shop;
				add_option('wemalo_plugin_shop_name', $this->shop);
			}
		}
    }

    public function registerWebHooks(): string
    {
        $token = "";
        foreach ($this->webhooks as $key => $payload){
            $token = $this->registerHook($key, $payload, $token);
            sleep(1);
        }

        if ( !add_option("wemalo_hook_token", $token) ){
            update_option("wemalo_hook_token", $token);
        }

        return is_string($token) ? $token : '';
    }

    /**
     *
     */
    public function unregisterWebHooks(){
        foreach ($this->webhooks as $key => $payload){
            $this->unregisterHook($key);
        }

        delete_option("wemalo_hook_token");
    }

    /**
     * Check all webhooks on system and check if they are already added to wemalo,
     * if not, they will be added manually
     * @param bool $newToken
     */
    private function checkWebHooks(bool $newToken = false){
        if (!$newToken){
            if ( get_option("wemalo_hook_token") ){
                $hooks = $this->getRegisteredWebHooks();
                $events = [];
                $tokens = [];
                if ( isset($hooks->hooks) && $hooks->hooks ){
                    foreach( $hooks->hooks as $hook ){
                        $events[] = $hook->event;
                        $tokens[$hook->event] = $hook->token;
                    }
                }

                foreach ($this->webhooks as $key => $payload){
                    if ( in_array($key, $events) )
                    {
                        if ( $tokens[$key] != get_option("wemalo_hook_token"))
                            $this->registerHook($key, $payload, get_option("wemalo_hook_token"));
                    }else{
                        $this->registerHook($key, $payload, get_option("wemalo_hook_token"));
                    }
                }
            }else{
                $this->registerWebHooks();
            }
        }else{
            delete_option("wemalo_hook_token");
            $this->registerWebHooks();
        }
    }

    /**
     * @param bool $newtoken
     */
    public function updateWebHooks(bool $newtoken = false){
        $this->checkWebHooks($newtoken);
    }


	/**
	 * registers a webhook
	 * @param string $eventName
	 * @param string $payload
	 * @param string token
	 * @return string|boolean
	 */
	public function registerHook(string $eventName, string $payload, string $token = ""): bool|string
    {
	    if ($this->shop != "")
        {
            $postData = array();
            $postData['payload'] = $payload;
            if ($token == "") {
                $token = $this->shop.rand(10,100).rand(10,100).rand(10,100);
            }
            $postData['token'] = $token;
            $this->_POST("webhook/".$eventName, $postData);

            return (string)$postData['token'];
        }else
            return false;
	}
	
	/**
	 * unregister a wemalo connect hook
	 * @param string $eventName
	 */
	public function unregisterHook(string $eventName) {
		$postData = array();
		$this->_DELETE("webhook/".$eventName, $postData);
	}
	
	/**
	 * builds a url
	 * @param string $serviceName
	 * @return string
	 */
	private function buildUrl(string $serviceName): string
    {
		$baseUrl = $this->debugMode ? 'http://4rhfgdvmilyvsekp.myfritz.net:3700' : 'https://connect.wemalo.com';
		return $baseUrl.'/v1/'.$serviceName;
	}

    /**
     * Check the header and will wait if x-wemalo-limit-remainingcall is near to 0
     * @param mixed $response response of HTTP Request
     * @param int $seconds how long do I have to wait
     */
    private function waitLimitCall(mixed $response, int $seconds = 0){
        if ( !is_wp_error($response)){
            $headers = $response["headers"]->getAll();
            if (array_key_exists("x-wemalo-limit-maxcall", $headers) && array_key_exists("x-wemalo-limit-remainingcall", $headers))
            {
                $limit_maxcall = intval($headers["x-wemalo-limit-maxcall"]);
                $limit_remainingcall = intval($headers["x-wemalo-limit-remainingcall"]);

                if ($limit_remainingcall <= 0){
                    sleep($seconds);
                }
            }
        }
    }

    /**
     * executes a get call
     * @param string $serviceName
     * @return stdClass
     */
	private function _GET(string $serviceName): stdClass
    {
        return $this->setReturn(wp_remote_get(
            $this->buildUrl($serviceName),
            array('headers'=>$this->getHeader(),
                'method'=>'GET', 'timeout'=>60)
        ));
    }

    /**
     * executes a POST call
     * @param string $serviceName
     * @param array $postData
     * @return stdClass
     */
	private function _POST(string $serviceName, array $postData): stdClass
    {
        return $this->setReturn(wp_remote_post(
            $this->buildUrl($serviceName),
            array('headers'=>$this->getHeader(), 'body' => json_encode($postData),
                'method'=>'POST', 'timeout'=>60)
        ));
	}

    /**
     * executes a delete call
     * @param string $serviceName
     * @param array $postData
     * @return stdClass
     */
	private function _DELETE(string $serviceName, array $postData): stdClass
    {
		return $this->setReturn(wp_remote_post(
			$this->buildUrl($serviceName),
			array('headers'=>$this->getHeader(), 'body' => json_encode($postData), 'method'=>'DELETE', 'timeout'=>60)
		));
	}

    /**
     * executes a put call
     * @param string $serviceName
     * @param array $postData
     * @return stdClass
     */
	private function _PUT(string $serviceName, array $postData): stdClass
    {
		return $this->setReturn(wp_remote_request(
				$this->buildUrl($serviceName),
				array('headers'=>$this->getHeader(), 'body' => json_encode($postData),
						'method'=>'PUT', 'timeout'=>60)
		));
	}
	
	/**
	 * returns headers array
	 * @return array
	 */
    private function getHeader(): array
    {
		return array(
			'content-type'=> 'application/json',
			'authorization'=> 'JWT '.$this->token,
            'wemalo-plugin-version' => ''
		);
	}

    /**
     * evaluates call response and returns an array with response information
     * @param  $response
     * @return stdClass
     */
    private function setReturn($response): stdClass
    {
        $this->waitLimitCall($response, 3);

        if (is_wp_error($response)) {
            $ret = new stdClass();
            $ret->status = 500;
            $ret->message = $response->get_error_message();
            return $ret;
        }else {
            return json_decode($response['body']);
        }
    }
	
	/**
	 * returns shop name loaded from wemalo-connect
	 * @return string
	 */
	public function getShopName(): string
    {
		return $this->shop;
	}

    /**
     * checks whether the post type is supported and should be transmitted to wemalo
     * @param WP_POST $post
     * @return string
     * @throws Exception
     */
	public function transmitProductData(WP_POST $post): string
    {
		$productHandler = new WemaloPluginProducts();
		if ($productHandler->handlePostType($post->post_type)) {
			$ret = $this->transmitProduct($post);
			if ($ret) {
				if ($ret->status == 200) {
					//ok
					return $productHandler->getCurrentDate();
				}
				//return error message
				return $productHandler->getCurrentDate().' ERROR: '.json_encode($ret);
			}
			//obviously, no product data due to missing SKU
			return $productHandler->getCurrentDate()." Keine SKU";
		}
		return "";
	}

    /**
     * transmits a product to wemalo-connect
     * @param WP_POST $post
     * @return stdClass|null
     */
	private function transmitProduct(WP_POST $post): ?stdClass
    {
	    $products = [];
	    // Mohammad Oslani handle the transmit Product viw addMultiple Products
		$handler = new WemaloPluginProducts();
		$postData = $handler->getProductData($post);
		if ($postData != null) {
		    foreach ($postData as $value){
		        $value['shop'] = $this->shop;
		        $products[] = $value;
            }

			return $this->_POST("product/addMultiple", ['products' => $products]);
		}

        return null;
	}

    /**
     * loads current order status from wemalo
     * @param int $postId
     * @return boolean
     * @throws Exception
     */
	public function checkOrderStatus(int $postId): bool
    {
		$handler = new WemaloPluginOrders();
		try {
			update_post_meta($postId, "wemalo-check-status", $handler->getCurrentDate());
			$ret = $this->_GET("goodsOrder/getStatus/".$postId);
			if ($ret && $ret->status == 200) {
				$this->handleOrderStatus($ret->statusName, $ret, $postId, true);
				return true;
			}
		}
		catch (\Exception $e) {
			add_post_meta($postId, "wemalo-connect-status", 
				$handler->getCurrentDate()." Error: ".$e->getMessage());
		}
		return false;
	}
	
	/**
	 * loads available dispatcher profiles
	 * @return array|string
	 */
	public function getAvailableDispatcherProfiles() {
		try {
			$ret = $this->_GET("goodsOrder/profiles");
			if ($ret && isset($ret->status) && $ret->status == 200 && isset($ret->dispatcher)) {
				return $ret->dispatcher;
			}
		}
		catch (\Exception $e) {
			return $e->getMessage();
		}
		return "Dispatcher profiles not loaded correctly.";
	}
	
	/**
	 * handles order status returned from wemalo: if partially reserved, the reason will
	 * be added as property. If reserved status will be changed to wemalo-fulfill and if
	 * cancelled in wemalo, status will be changed to cancelled in Wordpress as well.
	 * @param string $status
	 * @param stdClass $ret
	 * @param int $postId
	 * @param boolean $checkCancelled
	 */
	private function handleOrderStatus(string $status, stdClass $ret, int $postId, bool $checkCancelled = false) {
		$handler = new WemaloPluginOrders();
		$this->orderInFulfillment = $handler->handleOrderStatus($postId, $ret, $ret->statusName);
	}

    /**
     * updates order status in wemalo
     * @param int $orderId
     * @param int $priority
     * @return boolean
     * @throws Exception
     */
	public function updateOrderPriority(int $orderId, int $priority): bool
    {
        $handler = new WemaloPluginOrders();

        try {
			update_post_meta($orderId, "wemalo-connect-priority",
				$handler->getCurrentDate()." set priority ".$priority);
			$data = array();
			$data['shop'] = $this->shop;
			$data['priority'] = (int)$priority;
			$ret = $this->_PUT("goodsOrder/changePriority/".$orderId, $data);
			if ($ret && $ret->status == 200) {
				return true;
			}
			else {
				if ($ret) {
				    update_post_meta($orderId, "wemalo-connect-priority",
						$handler->getCurrentDate()." Error: ".json_encode($ret));
				}
			}
			return false;
		}
		catch (Exception $e) {
		    add_post_meta($orderId, "wemalo-connect-priority",
				$handler->getCurrentDate()." Error: ".$e->getMessage());
			return false;
		}
	}
	
	/**
	 * uploads an order document to wemalo
	 * @param int $postId
	 * @param string $docType
	 * @param string $docName
	 * @param string $base64data
	 * @param WC_Order $orderObj
	 * @return string
	 */
	public function transmitOrderDocument(int    $postId, string $docType,
                                          string $docName, string $base64data, WC_Order $orderObj): string
    {
		try {
			$postData = array();
			$postData['docType'] = $docType;
			$postData['docName'] = $docName;
			$postData['base64data'] = $base64data;
			$ret = $this->_POST("goodsOrder/addDocument/".$postId, $postData);
			if ($ret && $ret->status == 200) {
				$orderObj->add_order_note($docName." uploaded", 0, true);
				return "";
			}
			else if ($ret) {
				return json_encode($ret);
			}
			else {
				return "Connect didn't accept it for some reason.";
			}
		}
		catch (Exception $e) {
			return $e->getMessage();
		}
	}
	
	/**
	 * uploads a document to an announced return shipment
	 * @param int $postId
	 * @param string $docName
	 * @param string $base64data
	 * @param WC_Order $orderObj
	 * @return string
	 */
	public function transmitReturnsDocument(int $postId, string $docName, string $base64data, WC_Order $orderObj): string
    {
		try {
			$postData = array();
			$postData['docType'] = "DOCUMENT";
			$postData['docName'] = $docName;
			$postData['base64data'] = $base64data;
			$ret = $this->_POST("returns/document/".$postId, $postData);
			if ($ret && $ret->status == 200) {
				$orderObj->add_order_note($docName." uploaded", 0, true);
				return "";
			}
			else if ($ret) {
				return json_encode($ret);
			}
			else {
				return "Connect didn't accept it for some reason.";
			}
		}
		catch (Exception $e) {
			return $e->getMessage();
		}
	}

    /**
     * sends an order to wemalo-connect
     * @param array $order
     * @param int $postId
     * @return boolean
     * @throws Exception
     */
	public function transmitOrder(array $order, int $postId): bool
    {
		$handler = new WemaloPluginOrders();
		try {
			$ret = $this->_POST("goodsOrder/addConditional", $order);
			if ($ret && isset($ret->status) && $ret->status == 200) {
				$this->handleOrderStatus($ret->orderStatus, $ret, $postId);
				return true;
			}
			else {
				if (isset($ret->status) && $ret->status === 419){
					$this->cancelLimit($order, __FUNCTION__, $ret);
				}
				if ($ret) {
					update_post_meta($postId, "wemalo-connect-transmit", 
						$handler->getCurrentDate()." Error: ".json_encode($ret));
				}
				return false;
			}
		}
		catch (Exception $e) {
			add_post_meta($postId, "wemalo-connect-transmit", 
				$handler->getCurrentDate()." Error: ".$e->getMessage());
			return false;
		}
	}

    /**
     * transmits a return to wemalo
     * @param array $order
     * @param int $postId
     * @return boolean
     * @throws Exception
     */
	public function transmitReturn(array $order, int $postId): bool
    {
		$handler = new WemaloPluginOrders();
		try {
			$ret = $this->_POST("returns/announceConditional", $order);
			if ($ret && $ret->status == 200) {
				return true;
			}
			else {
				if ($ret->status === 419){
					$this->cancelLimit($order, __FUNCTION__, $ret);
				}
				if ($ret) {
					update_post_meta($postId, "wemalo-connect-return",
						$handler->getCurrentDate()." Error: ".json_encode($ret));
				}
				return false;
			}
		}
		catch (Exception $e) {
			add_post_meta($postId, "wemalo-connect-return", 
				$handler->getCurrentDate()." Error: ".$e->getMessage());
			return false;
		}
	}

    /**
     * called for cancelling an order in wemalo
     * @param int $orderId
     * @return boolean
     * @throws Exception
     */
	public function cancelOrder($orderId) {
		$handler = new WemaloPluginOrders();
		try {
			$data = array();
			$data['reason'] = "Cancelled in WP";
			$data['shop'] = $this->shop;
			$ret = $this->_PUT("goodsOrder/cancel/".$orderId, $data);
			if ($ret && $ret->status == 200) {
				return true;
			}
			else {
				if ($ret->status === 419){
					$this->cancelLimit($orderId, __FUNCTION__, $ret);
				}
				if ($ret) {
					update_post_meta($orderId,"wemalo-connect-cancel",
						$handler->getCurrentDate()." Error: ".json_encode($ret));
				}
				return false;
			}
		}
		catch (Exception $e) {
			add_post_meta($orderId, "wemalo-connect-cancel", 
				$handler->getCurrentDate()." Error: ".$e->getMessage());
			return false;
		}
	}
	
	/**
	 * adds a note to an order
	 * @param int $orderId
	 * @param int $note
	 * @param int $notesField
	 */
	public function addOrderNote($orderId, $note, $notesField=1) {
		$data = array();
		$data['shop'] = $this->shop;
		$data['notices'] = $note;
		$data['noticeField'] = $notesField;
		$this->_PUT("goodsOrder/note/".$orderId, $data);
	}
	
	/**
	 * loads current order status
	 * @param number $orderId
	 * @return array|boolean status result or false if call failed
	 */
	public function getCurrentStatus($orderId) {
		$ret= $this->_GET("goodsOrder/getStatus/".$orderId);
		if ($ret && $ret->status == 200) {
			return $ret;
		}
		return false;
	}

    /**
     * Get the tracking number from Wemalo Connect
     * @param $goodsOrderParcelId
     * @return array|boolean status result or false if call failed
     */
    public function getTrackingNumber($goodsOrderParcelId) {
        $ret = $this->_GET("goodsOrder/package/".$goodsOrderParcelId."/trackingNumber");
        if ($ret && $ret->status == 200) {
            return $ret;
        }
        return false;
    }

    /**
     * Get the registered webhooks from Wemalo Connect
     * @return array|boolean status result or false if call failed
     */
    public function getRegisteredWebHooks() {
        return $this->_GET("webhook");
    }

    /**
     * Return true if the auth_key is correct
     */
    public function checkAuthKeyCorrect(){
        $ret = $this->_GET("webhook");

        return $ret;
    }

    public function cancelLimit($orderId, $function ,$ret = null){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wemalo_timerate_limiter';

		$result = $wpdb->get_results("SELECT * FROM {$table_name} WHERE order_id = $orderId AND status = {$ret->status}");
		if ( count($result) > 0 ){
			return false;
		} else {
			$wpdb->insert(
				$table_name,
				[
					'order_id' => $orderId,
					'function' => $function,
					'status'=> $ret->status,
					'text' => json_encode($ret)
				]);
		}
	    return true;
    }
}
