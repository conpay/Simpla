<?php

if($_SERVER['REQUEST_METHOD'] = 'POST')
{
	//ob_start();
	
	chdir('../../');
	require_once('api/Simpla.php');
	
	$simpla = new Simpla();
	
	$simpla->db->query("SELECT id FROM __payment_methods WHERE module='Conpay' LIMIT 1");
	$conpay = $simpla->payment->get_payment_method($simpla->db->result('id'));
	
	$conpay->settings = $simpla->payment->get_payment_settings($conpay->id);
	
	$response_pass = $conpay->settings['response_pass'];
	$merchant_id = $conpay->settings['merchant_id'];
	$total = 0;
	
	if (isset($_POST['goods']))
	{
		foreach ((array) $_POST['goods'] as $item)
		{
			$total += (float) $item['price'];
		}
	}
	
	$parts = array
	(
		$response_pass,
		is_numeric($_POST['delivery'])? $total + $_POST['delivery'] : $total,
		$merchant_id,
	);
	
	if (isset($_POST['custom']))
	{
		foreach ($_POST['custom'] as $v)
		{
			$parts[] = $v;
		}
	}
	
	$checksum = md5(implode('!', $parts));
	/*
	print_r($_SERVER);
	print_r($checksum);
	print_r($_POST);
	print_r($conpay->settings);
	print_r(is_numeric($_POST['delivery'])? $total + $_POST['delivery'] : $total);
	print_r($total);
	print_r($parts);
	
	mail('alexey_frolagin@rambler.ru', 'test', ob_get_contents());
	*/
	if ($_POST['checksum'] != $checksum
			|| $_SERVER['HTTP_REFERER'] != 'https://www.conpay.ru'
			|| $_SERVER['HTTP_USER_AGENT'] != 'Conpay')
	{
		die('Access denied.');
	}
	
	$order = new stdClass();
	
	$customer = $simpla->request->post('customer');
	$custom		= (array) $simpla->request->post('custom');
	
	if ($custom['order_id']) die('Order already exists.');
	
	$order->name        = $customer['LastName'] . ' ' . $customer['UserName'] . (($pn = $customer['PatronymicName'])? ' ' . $pn : '');
	$order->email       = $customer['Email'];
	$order->address     = '';
	$order->phone       = $customer['ContactPhone'];
	$order->comment			= '';
	
	$order->discount = 0;
	$order->coupon_discount = 0;
	$order->coupon_code = '';
	
	$order->payment_method_id = $conpay->id;
	$order->payment_date = '0000-00-00 00:00:00';
	$order->closed = 0;
	$order->payment_details = '';
	$order->ip = '';
	$order->total_price = 0;
	$order->note = '';
	
	if(!empty($custom['user_id']))
	{
		$order->user_id = $custom['user_id'];
	}
	
	// Добавляем заказ в базу
	$order_id = $simpla->orders->add_order($order);
	
	// Добавляем товары к заказу
	foreach($simpla->request->post('goods') as $i => $item)
	{
		$item_ids = explode(':', $item['id']);
		$simpla->orders->add_purchase
		(
			array
			(
				'order_id' => $order_id,
				'variant_id' => intval($item_ids[1]),
				'amount' => intval($item['quantity'])
			)
		);
	}
	
	// Отправляем письмо пользователю
	$simpla->notify->email_order_user($order_id);
	
	// Отправляем письмо администратору
	$simpla->notify->email_order_admin($order_id);
}   

?>