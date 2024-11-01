<?php
require_once __DIR__ . '/../api/handler/class-wemalopluginorders.php';
$orders         = new WemaloPluginOrders();
$single_product = $orders->get_returns( get_the_ID() );
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
		<?php
		foreach ( $single_product as $product ) {
			echo '
	    <tr>
	        <td>' . esc_html( $product->timestamp ) . '</td>
			<td>' . esc_html( $product->product_name ) . "</td>
	        <td style='text-align:right;'>" . esc_html( $product->quantity ) . '</td>
	        <td>' . esc_html( $product->choice ) . '</td>
			<td>' . esc_html( $product->return_reason ) . '</td>
	        <td>' . esc_html( $product->serial_number ) . '</td>
	        <td>' . esc_html( $product->lot ) . '</td>
	        <td>' . esc_html( $product->sku ) . '</td>
	    </tr>
	    ';
		}
		?>
		</tbody>
	</table>