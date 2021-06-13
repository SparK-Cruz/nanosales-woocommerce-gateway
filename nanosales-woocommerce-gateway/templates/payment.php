<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<hr />
<div class="nano-payment-address">
	<?= sprintf(__('Please use the address below to pay %s Nano', 'nanosales-woocommerce-gateway'), number_format($amount)) ?>
	<div class="qr-code" data-text="<?=$url?>"></div>
	<div class="payment-address"><p><?=$address?> <button class="clip"><?=__('Copy Address', 'nanosales-woocommerce-gateway')?></button></p></div>
</div>
