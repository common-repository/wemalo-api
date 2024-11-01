<?php

require_once __DIR__ . '/../plugin/WemaloAPIOrderStatus.php';
require_once __DIR__ . '/../plugin/WemaloAPIOrderStatusArray.php';

class WemaloPluginConfig
{
    public function __construct() {
    }

    /**
     * adds an input field for saving user authkey
     */
    private function set_user_auth_key() {
        $authkey_editor_id = "authkeyarea";
        $option = "wemalo_plugin_auth_key";

        if (isset($_REQUEST["generate_new_token"]))
        {
            $value = sanitize_text_field($_REQUEST[$authkey_editor_id]);
            if(!add_option($option, $value)){
                update_option($option, $value);
            }
            $api = new WemaloAPIConnect();
            $api->updateWebHooks(true);

        }else if (isset($_REQUEST[$authkey_editor_id]) && get_option($option) !== sanitize_text_field($_REQUEST[$authkey_editor_id])) {
            $value = sanitize_text_field($_REQUEST[$authkey_editor_id]);
            if(!add_option($option, $value)){
                update_option($option, $value);
            }

            $api = new WemaloAPIConnect();
            $api->updateWebHooks(false);
        }

        $content = get_option($option);

        echo '<p class="wemalo-config"><input requried style="width:750px;" type="text" name="'.$authkey_editor_id.'" id="'.$authkey_editor_id.
            '" class="form-control" aria-describedby="'.$authkey_editor_id.'_help" value="'.$content.'" placeholder="Bitte Authkey eintragen"></p>';
        $hook = get_option("wemalo_hook_token");
        if ($hook) {
            echo '<p>Webhook-Token: '.$hook.'</p>';
        }
    }


    /**
     * adds a label for setting an order as blocked
     */
    private function set_check_label() {
        global $wpdb;
        $option_name = "_wemalo_order_blocked_label";
        $old_value = get_option($option_name);
	    $requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
        $new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
        update_option($option_name, $new_value);
        echo '<p class="wemalo-config"><label for="'.$option_name.'">Blockiert</label>';
        echo '<input required type="text" name="'.$option_name.'" id="'.$option_name.
            '" class="form-control" value="'.get_option($option_name).'" placeholder="Bitte Bezeichnung eintragen"></p>';
    }

    /**
     * displays an email field for entering the receiver mail for celebrity mails
     */
    private function set_celeb_email() {
    	global $wpdb;
    	$option_name = "_wemalo_order_celeb_email";
    	$old_value = get_option($option_name);
	    $requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
    	$new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
    	update_option($option_name, $new_value);
    	echo '<p class="wemalo-config"><label for="'.$option_name.'">Promi-Versand</label>';
    	echo '<input type="email" name="'.$option_name.'" id="'.$option_name.
    	'" class="form-control" value="'.get_option($option_name).'" placeholder="Bitte E-Mail-Adresse eintragen"></p>';
    }

    /**
     * displays an input field for entering the formatted order number
     */
    private function set_formatted_order_number() {
        global $wpdb;
        $option_name = "_wemalo_order_number_formatted";
        $old_value = get_option($option_name);
        $requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
        $new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
        update_option($option_name, $new_value);
        echo '<p class="wemalo-config"><label for="'.$option_name.'">Auftragsnummer</label>';
        echo '<input type="text" name="'.$option_name.'" id="'.$option_name.
            '" class="form-control" value="'.get_option($option_name).'" placeholder="Bitte das Feld für Auftragsnummer eintragen"></p>';

    }

    /**
     * displays an input field for setting a custom house number field
     */
    private function set_house_number_field() {
        global $wpdb;
        $option_name = "_wemalo_house_number_field";
        $old_value = get_option($option_name);
        $requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
        $new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
        update_option($option_name, $new_value);
        echo '<p class="wemalo-config"><label for="'.$option_name.'">Hausnummer</label>';
        echo '<input type="text" name="'.$option_name.'" id="'.$option_name.
            '" class="form-control" value="'.get_option($option_name).'" placeholder="Bitte das Feld für Hausnummer eintragen"></p>';
    }

    /**
     * displays a field for setting a parent order field
     */
    private function set_parent_order_field() {
    	global $wpdb;
    	$option_name = "_wemalo_parent_order_field";
    	$old_value = get_option($option_name);
    	$requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
    	$new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
    	update_option($option_name, $new_value);
    	echo '<p class="wemalo-config"><label for="'.$option_name.'">Parent-Order (Retoure)</label>';
    	echo '<input type="text" name="'.$option_name.'" id="'.$option_name.
    		'" class="form-control" value="'.get_option($option_name).
    		'" title="Beinhaltet ein Matching-Feld zum Hinterlegen eines Custom Fields für Vater-Aufträge" placeholder="Auftrag-ID"></p>';
    }

    /**
     * display a field for setting the category
     */
    private function set_order_category_field() {
        global $wpdb;
        $option_name = "_wemalo_order_category_field";
        $old_value = get_option($option_name);
        $requestOption = sanitize_text_field(isset($_REQUEST[$option_name]) ?? $_REQUEST[$option_name]);
        $new_value = array_key_exists($option_name, $_REQUEST) ? $requestOption : $old_value;
        update_option($option_name, $new_value);
        echo '<p class="wemalo-config"><label for="'.$option_name.'">Category</label>';
        echo '<input type="text" name="'.$option_name.'" id="'.$option_name.
        '" class="form-control" value="'.get_option($option_name).
        '" title="Beinhaltet ein Matching-Feld zum Hinterlegen eines Custom Fields für Auftragskategorie" placeholder="Kategorie"></p>';
    }

    /**
     * generates form elements
     */
    private function set_status_strings() {
        global $wpdb;
        $order_status = wc_get_order_statuses();
        $table_name = "{$wpdb->prefix}wemalo_custom_orderstatus";
        $newStatus = array();
        $statusArray = new WemaloAPIOrderStatusArray();
        foreach ($statusArray->getStatusArray() as $orderStatus) {
        	$newStatus[$orderStatus->getKey()] = array_key_exists($orderStatus->getKey(), $_REQUEST) ?
		        sanitize_text_field($_REQUEST[$orderStatus->getKey()]) : "";
        }
        $cancelFound = false;
        $returnBookedFound = false;
        $returnAnnouncedFound = false;
        $orderFulfillmentFound = false;
        $orderFulfillmentBlockedFound = false;
        $reclamationBookedFound = false;
        $reclamationAnnouncedFound = false;
        $orderInProcessFound = false;
        $orderShipped = false;

        foreach ($order_status as $key => $status) {
            switch ($key) {
            	case WemaloAPIOrderStatusArray::$STATUS_ORDER_CANCELLED:
	            	$cancelFound = true;
	            	$statusArray->setValue($key, $status);
	            	break;
            	case WemaloAPIOrderStatusArray::$STATUS_RETURN_ANNOUNCED:
	            	$returnAnnouncedFound = true;
	            	$statusArray->setValue($key, $status);
	            	break;
                case WemaloAPIOrderStatusArray::$STATUS_RETURN_BOOKED:
                	$returnBookedFound = true;
                	$statusArray->setValue($key, $status);
                	break;
                case WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT:
                	$orderFulfillmentFound = true;
                	$statusArray->setValue($key, $status);
                	break;
                case WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT_BLOCKED:
                	$orderFulfillmentBlockedFound = true;
                	$statusArray->setValue($key, $status);
                	break;
                case WemaloAPIOrderStatusArray::$STATUS_RECLAMATION_ANNOUNCED:
                    $reclamationAnnouncedFound = true;
                    $statusArray->setValue($key, $status);
                    break;
                case WemaloAPIOrderStatusArray::$STATUS_RECLAMATION_BOOKED:
                    $reclamationBookedFound = true;
                    $statusArray->setValue($key, $status);
                    break;
                case WemaloAPIOrderStatusArray::$STATUS_ORDER_INPROCESS:
                    $orderInProcessFound = true;
                    $statusArray->setValue($key, $status);
                    break;
                case WemaloAPIOrderStatusArray::$STATUS_ORDER_SHIPPED:
                    $orderShipped = true;
                    $statusArray->setValue($key, $status);
                    break;
            }
        }
        $reloadStatus = false;

        if (!$returnBookedFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 0, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$returnAnnouncedFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 1, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$cancelFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 2, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$orderFulfillmentFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 3, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$orderFulfillmentBlockedFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 4, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$reclamationAnnouncedFound) {
        	$order_status = $statusArray->createOrderStatus($wpdb, 5, $order_status, $table_name);
        	$reloadStatus = true;
        }
        if (!$reclamationBookedFound) {
            $order_status = $statusArray->createOrderStatus($wpdb, 6, $order_status, $table_name);
            $reloadStatus = true;
        }
        if (!$orderInProcessFound) {
            $order_status = $statusArray->createOrderStatus($wpdb, 7, $order_status, $table_name);
            $reloadStatus = true;
        }
        if (!$orderShipped) {
            $order_status = $statusArray->createOrderStatus($wpdb, 8, $order_status, $table_name);
            $reloadStatus = true;
        }

        if ($reloadStatus) {
        	//reload
        	$order_status = wc_get_order_statuses();
        }
        //update in database
        $statusArray->updateOrderStatus($wpdb, $table_name, $order_status, $newStatus);
        //register again
        $statusArray->registerCustomerOrderStatuses();
        //display data
        foreach ($statusArray->getStatusArray() as $orderStatus) {
        	echo '<p class="wemalo-config"><label for="'.$orderStatus->getKey().'">'.$orderStatus->getKey().'</label>';
        	echo '<input required type="text" name="'.$orderStatus->getKey().'" id="'.$orderStatus->getKey().
        		'" class="form-control" value="'.$orderStatus->getValue().'" placeholder="Bitte Bezeichnung eintragen"></p>';
        }
    }

    public function buildMarkup(){
        include __DIR__ . '/wemaloplugin_configHTML.php';
    }
}