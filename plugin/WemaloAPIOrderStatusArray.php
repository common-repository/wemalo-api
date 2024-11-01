<?php

require_once __DIR__ . '/WemaloAPIOrderStatus.php';

/**
 * holds wemalo order statuses
 * @author Patric Eid
 *
 */
class WemaloAPIOrderStatusArray {
    private ?array $statusArray = null;
    
    public static string $STATUS_RETURN_BOOKED = "wc-return-booked";
    public static string $STATUS_RETURN_ANNOUNCED = "wc-return-announced";
    public static string $STATUS_ORDER_CANCELLED = "wc-wemalo-cancel";
    public static string $STATUS_ORDER_FULFILLMENT = "wc-wemalo-fulfill";
    public static string $STATUS_ORDER_FULFILLMENT_BLOCKED = "wc-wemalo-block";
    public static string $STATUS_RECLAMATION_ANNOUNCED = "wc-reclam-announced";
    public static string $STATUS_RECLAMATION_BOOKED = "wc-reclam-booked";
    public static string $STATUS_ORDER_INPROCESS = "wc-order-inprocess";
    public static string $STATUS_ORDER_SHIPPED = "wc-order-shipped";

    /**
     * builds a default order status array
     * @return WemaloAPIOrderStatus[]
     */
    private function buildArray()
    {
        if ($this->statusArray == null) {
            $this->statusArray = array();
            $this->statusArray[0] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_RETURN_BOOKED, 'Retoure gebucht');
            $this->statusArray[1] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_RETURN_ANNOUNCED, 'Retoure angemeldet');
            $this->statusArray[2] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_ORDER_CANCELLED, 'Auftrag storniert');
            $this->statusArray[3] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT, 'Fulfillment');
            $this->statusArray[4] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_ORDER_FULFILLMENT_BLOCKED, 'Fulfillment blocked');
            $this->statusArray[5] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_RECLAMATION_ANNOUNCED, 'Reklamation angemeldet');
            $this->statusArray[6] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_RECLAMATION_BOOKED, 'Reklamation gebucht');
            $this->statusArray[7] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_ORDER_INPROCESS, 'Auftrag wird bearbeitet');
            $this->statusArray[8] = WemaloAPIOrderStatus::create(WemaloAPIOrderStatusArray::$STATUS_ORDER_SHIPPED, 'Auftrag ist versendet');
        }
        return $this->statusArray;
    }
    
    /**
     * adds a new order status
     * @param wpdb $wpdb
     * @param int $id
     * @param array $order_statuses
     * @param string $table_name
     * @return array
     */
    public function createOrderStatus(wpdb $wpdb, int $id, array $order_statuses, string $table_name): array
    {
        $orderStatus = $this->getById($id);
        if ($orderStatus) {
            $order_statuses[$orderStatus->getKey()] = _x($orderStatus->getValue(), 'Order status', 'WooCommerce');
            $sql = "INSERT INTO ".$table_name." (id, status_display, status_name) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE status_name = %s";
            $sql = $wpdb->prepare($sql, $id, $orderStatus->getValue(), $orderStatus->getKey(), $orderStatus->getKey());
            $wpdb->query($sql);
        }
        return $order_statuses;
    }
    
    /**
     * updates order statuses
     * @param wpdb $wpdb
     * @param string $table_name
     * @param array $orderStatus
     * @param array $newStatus
     */
    public function updateOrderStatus(wpdb $wpdb, string $table_name, array $orderStatus, array $newStatus) {
        foreach ($orderStatus as $key => $status) {
            $status_display = (array_key_exists($key, $newStatus) && $newStatus[$key] != "" ?
                $newStatus[$key] : $status);
            if ($status != $status_display) {
                $sql = "UPDATE ".$table_name." SET status_display = '".$status_display."' WHERE status_name = '".$key."';";
                $update_status = $wpdb->query($sql);
                //update array
                $this->setValue($key, $status_display);
            }
        }
    }
    
    /**
     * updates an array object
     * @param string $key
     * @param string $value
     */
    public function setValue(string $key, string $value) {
        foreach ($this->getStatusArray() as $s) {
            if ($s->getKey() == $key) {
                $s->setValue($value);
                break;
            }
        }
    }
    
    /**
     * registers order statuses
     */
    public function registerCustomerOrderStatuses() {
        foreach ($this->getStatusArray() as $orderStatus) {
            register_post_status($orderStatus->getKey(), array(
                'label'                     => $orderStatus->getValue(),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop($orderStatus->getValue().' <span class="count">(%s)</span>',
                    $orderStatus->getValue().' <span class="count">(%s)</span>')
            ));
            add_option("wemalo_".$orderStatus->getKey(), $orderStatus->getValue());
        }
    }
    
    /**
     * loads status array
     * @return WemaloAPIOrderStatus[]
     */
    public function getStatusArray() {
        if ($this->statusArray == null) {
            $this->buildArray();
        }
        return $this->statusArray;
    }

    /**
     * loads an entry by its id
     * @param int $id
     * @return WemaloAPIOrderStatus|null
     */
    public function getById(int $id): ?WemaloAPIOrderStatus
    {
        if (array_key_exists($id, $this->getStatusArray())) {
            return $this->getStatusArray()[$id];
        }

        return null;
    }
}