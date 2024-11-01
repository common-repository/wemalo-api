<?php

require_once __DIR__ . '/../api/handler/class-wemalopluginorders.php';

/**
 * Provides an interface for accessing functions from other plugins
 *
 * @author Patric Eid
 */
class WemaloInterface {

	/**
	 * Called for cancelling an order
	 *
	 * @param int $post_id
	 * @return Boolean true if order was cancelled
	 * @throws Exception
	 */
	public function cancel_order( int $post_id ): bool {
		$handler = new WemaloPluginOrders();
		return $handler->cancel_order( $post_id );
	}

	/**
	 * Sends an order to wemalo
	 *
	 * @param int           $post_id
	 * @param WC_Order|null $order_obj
	 * @throws Exception
	 */
	public function transmit_order( int $post_id, WC_Order $order_obj = null ) {
		$handler = new WemaloPluginOrders();
		$handler->transmit_order( $post_id, $order_obj );
	}
}
