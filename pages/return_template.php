<?php
require_once __DIR__ . '/../api/handler/WemaloPluginOrders.php';
$orders = new WemaloPluginOrders();
$single_product = $orders->getReturns(get_the_ID());
?>

	<h2>Rücksendungsdetails:</h2>
	<table style="max-width:550px;width:100%;">
	    <thead>
	    <tr>
	        <th>Datum</th>
	        <th>Produkt</th>
	        <th>Anzahl</th>
	        <th>Merkmal</th>
	        <th>Retourengrund</th>
	        <th>Seriennummer</th>
	        <th>lot</th>
	        <th>sku</th>
	    </tr>
	    </thead>
	    <tbody>
	    <?php foreach ($single_product as $product) {
	    echo "
	    <tr>
	        <td>".$product->timestamp."</td>
			<td>".$product->product_name."</td>
	        <td style='text-align:right;'>".$product->quantity."</td>
	        <td>".$product->choice."</td>
			<td>".$product->return_reason."</td>
	        <td>".$product->serial_number."</td>
	        <td>".$product->lot."</td>
	        <td>".$product->sku."</td>
	    </tr>
	    ";
	    }
	    ?>
	    </tbody>
	</table>