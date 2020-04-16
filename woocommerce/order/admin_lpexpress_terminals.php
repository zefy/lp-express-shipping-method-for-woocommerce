<?php defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); ?>
<tr class="shipping" class="selected_terminal">
	<td colspan="6">
		<div><strong><?php _e('Chosen terminal', 'lp-express-shipping-method-for-woocommerce') ?>:</strong></div>
		(#<?php echo $place_id; ?>) <strong><?php echo $name; ?></strong>, <?php echo $address; ?>, <?php echo $zipcode; ?> <?php echo $city; ?>
	</td>
</tr>
