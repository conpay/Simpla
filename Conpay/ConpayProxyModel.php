<?php
class ConpayProxyModel
{
	/**
	 * @var int
	 */
	private $merchantId;
	/**
	 * @var string
	 */
	private $apiKey;
	/**
	 * @var string
	 */
	private $serviceUrl = 'https://www.conpay.ru/service/proxy';
	/**
	 * @var string
	 */
	private $serviceAction;
	/**
	 * @var string
	 */
	private $charset = 'UTF-8';
	/**
	 * @var string
	 */
	private $conpayCharset = 'UTF-8';

	/**
	 * @constructor
	 * @throws Exception
	 */
	public function __construct()
	{
		$this->serviceAction = isset($_POST['conpay-action']) ? $_POST['conpay-action'] : '';

		if (!$this->isSelfRequest() || !$this->isPostRequest() || !$this->isCookieSet()) {
			throw new Exception('Incorrect request');
		}

		$this->serviceUrl = rtrim($this->serviceUrl.'/'.$this->serviceAction, '/');
	}

	/**
	 * @return boolean
	 */
	public function isSelfRequest() {
		return isset($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $_SERVER['HTTP_HOST'];
	}

	/**
	 * @return boolean
	 */
	public function isPostRequest() {
		return isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') && !empty($_POST);
	}

	/**
	 * @return boolean
	 */
	public function isCookieSet()
	{
		if (!$this->serviceAction) {
			return is_numeric($_POST['rand']) && ($_COOKIE['conpay-cs'] === $_POST['rand']);
		}
		return true;
	}

	/**
	 * @return string
	 */
	public function sendRequest()
	{
		$response = function_exists('curl_init') ? $this->useCurl() : $this->useFileGC();
		return $this->convertCharset($this->conpayCharset, $this->charset, $response);
	}

	/**
	 * @param int $id
	 * @return ConpayProxyModel
	 */
	public function setMerchantId($id)
	{
		$this->merchantId = (int)$id;
		return $this;
	}

	/**
	 * @param string $pass
	 * @return ConpayProxyModel
	 */
	public function setApiKey($pass)
	{
		$this->apiKey = $pass;
		return $this;
	}

	/**
	 * @param string $charset
	 * @return ConpayProxyModel
	 */
	public function setCharset($charset)
	{
		$this->charset = strtoupper($charset);
		return $this;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function useCurl()
	{
		$ch = curl_init($this->serviceUrl);

		curl_setopt($ch, CURLOPT_USERAGENT, $this->getUserAgent());
		curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getQueryData());
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$data = curl_exec($ch);

		if ($data === false)
		{
			$error = curl_error($ch);
			curl_close($ch);
			throw new Exception($this->convertCharset($this->conpayCharset, $this->charset, $error));
		}
		elseif (!$data) {
			throw new Exception('Server didn\'t return any data');
		}

		curl_close($ch);
		return $data;
	}

	/**
	 * @return string
	 */
	private function useFileGC()
	{
		$options = array(
			'http'=>array(
				'method'=>"POST",
				'content'=>$this->getQueryData(),
				'header'=>
					"Content-type: application/x-www-form-urlencoded\r\n".
						"Referer: {$_SERVER['HTTP_REFERER']}\r\n".
						"User-Agent: ".$this->getUserAgent()."\r\n"
			)
		);

		$context = stream_context_create($options);
		return file_get_contents($this->serviceUrl, false, $context);
	}

	/**
	 * @return string
	 */
	private function getUserAgent() {
		return 'Conpay/Merchant/'.$this->merchantId;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function getQueryData()
	{
		if ($this->merchantId === null) {
			throw new Exception('MerchantId is not set');
		}

		if (empty($_POST['merchant'])) {
			$_POST['merchant'] = $this->merchantId;
		}

		if (empty($_POST['checksum'])) {
			$_POST['checksum'] = $this->createChecksum($_POST);
		}

		return $this->convertCharset($this->charset, $this->conpayCharset, http_build_query($_POST));
	}

	/**
	 * @param $data
	 * @return string
	 */
	private function createChecksum($data)
	{
		$totalsum = 0;

		if (isset($data['goods']) && is_array($data['goods']) && !empty($data['goods']))
		{
			foreach ($data['goods'] as $p) {
				$totalsum += $p['price'] * (isset($p['quantity']) && $p['quantity'] > 1 ? (int)$p['quantity'] : 1);
			}
		}

		$parts = array(
			$this->apiKey,
			is_numeric($data['delivery']) ? $totalsum + $data['delivery'] : $totalsum,
			$this->merchantId
		);

		if (isset($data['custom']) && is_array($data['custom']) && !empty($data['custom']))
		{
			foreach ($data['custom'] as $customVar) {
				$parts[] = $customVar;
			}
		}

		return md5(implode('!', $parts));
	}

	/**
	 * @param string $in
	 * @param string $out
	 * @param string $data
	 * @return string
	 */
	private function convertCharset($in, $out, $data)
	{
		if ($in !== $out && function_exists('iconv')) {
			return iconv($in, $out, $data);
		}
		return $data;
	}
}
