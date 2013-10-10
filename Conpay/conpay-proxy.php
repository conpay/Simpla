<?php

chdir ('../../');

require_once('api/Simpla.php');
$simpla = new Simpla();

$simpla->db->query("SELECT settings FROM __payment_methods WHERE module='Conpay' LIMIT 1");
$settings = unserialize($simpla->db->result('settings'));

// Подключаем скрипт с классом ConpayProxyModel, выполняющим бизнес-логику
require_once 'ConpayProxyModel.php';
try
{
	// Создаем объект класса ConpayProxyModel
	$proxy = new ConpayProxyModel;
	// Устанавливаем свой идентификатор продавца
	$proxy->setMerchantId($settings['merchant_id']);
	// Устанавливаем свой API-ключ
	$proxy->setApiKey($settings['api_key']);
	// Устанавливаем кодировку, используемую на сайте (по-умолчанию 'UTF-8')
	$proxy->setCharset('UTF-8');
	// Выполняем запрос, выводя его результат
	echo $proxy->sendRequest();
}
catch (Exception $e) {
	echo json_encode(array('error'=>$e->getMessage()));
}
