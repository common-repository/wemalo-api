<?php

require_once __DIR__ . '/../api/handler/WemaloPluginOrders.php';

/**
 * provides an interface for accessing functions from other plugins
 * @author Patric Eid
 *
 */
class WemaloInterface {

    /**
     * called for cancelling an order
     * @param int $postId
     * @return Boolean true if order was cancelled
     * @throws Exception
     */
	public function cancelOrder(int $postId): bool
    {
		$handler = new WemaloPluginOrders();
		return $handler->cancelOrder($postId);
	}

    /**
     * sends an order to wemalo
     * @param int $postId
     * @param WC_Order|null $orderObj
     * @throws Exception
     */
	public function transmitOrder(int $postId, WC_Order $orderObj=null) {
		$handler = new WemaloPluginOrders();
		$handler->transmitOrder($postId, $orderObj);
	}
}