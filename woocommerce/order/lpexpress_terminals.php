<?php defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); ?>
<section class="woocommerce-lpexpress-details">

	<h2><?php _e( 'Chosen terminal', 'lp-express-shipping-method-for-woocommerce' ); ?></h2>

	<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">

		<tr>
			<th><?php _e( 'Address', 'lp-express-shipping-method-for-woocommerce' ); ?>:</th>
            <td><?php echo $name; ?>, <?php echo $address; ?>, <?php echo $zipcode; ?> <?php echo $city; ?></td>
		</tr>
        <tr>
            <th><?php _e( 'Comment', 'lp-express-shipping-method-for-woocommerce' ); ?>:</th>
            <td><?php echo $comment; ?></td>
        </tr>

	</table>
</section>