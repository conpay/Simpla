<?php

require_once('api/Simpla.php');

class Conpay extends Simpla
{
	public function checkout_form($order_id, $button_text = null)
	{
		
		$order = $this->orders->get_order((int) $order_id);
		$conpay = $this->payment->get_payment_method($order->payment_method_id);
		
		$user = NULL;
		if ($user_id = $order->user_id) $user = $this->users->get_user($user_id);
		
		require_once('conpay.callback.php');
		
		$conpay->settings = $this->payment->get_payment_settings($conpay->id);
		$conpay->settings = conpay_correct_settings($conpay->settings);
		
		$conpay->items = conpay_get_purchases($order->id);
		$conpay->custom = conpay_get_custom_vars
		(
			$user,
			$order
		);
		
		$conpay->custom->order_id = $order_id;
		
		$button_text = '<span class="conpay-btn-credit"><b></b>Заполнить анкету на кредит</span>';
		$button =	"
		<div id='" . $conpay->settings['button_container_id'] . "'></div>
		<script type=\"text/javascript\" src=\"http://www.conpay.ru/public/api/btn.1.6.min.js\"></script>
		<script type=\"text/javascript\">
			try
			{
				window.conpay.init
				(
					'/payment/Conpay/conpay-proxy.php',
					{
						'className': '" . $conpay->settings['button_class_name'] . "',
						'tagName': '" . $conpay->settings['button_tag_name'] . "',
						'text': '" . $button_text . "',
					},
					" . json_encode($conpay->custom) . "
				);
				window.conpay.addButton(" . json_encode($conpay->items) . ", '" . $conpay->settings['button_container_id'] . "');
			} catch(e) {}
		</script>
		";
		
		return $button;
	}
}