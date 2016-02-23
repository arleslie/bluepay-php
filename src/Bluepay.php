<?php

namespace arleslie\Bluepay;

use \Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client as Guzzle;

class Bluepay
{
	const API_URL = 'https://secure.bluepay.com/interfaces/bp20post';

	private $guzzle;
	private $accountid;
	private $secretkey;
	private $apis = [];
	protected $mode;

	private $tpsParams = [
		'AMOUNT',
		'MASTERID',
		'NAME1',
		'ACCOUNT'
	];

	private $params = [];

	public function __construct($accountid, $secretkey, $sandbox = false)
	{
		$this->guzzle = new Guzzle([
			'base_uri' => self::API_URL,
			'defaults' => [
				'query' => [
					'ACCOUNT_ID' => $accountid
				]
			],
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded'
			]
		]);

		$this->params['ACCOUNT_ID'] = $this->accountid = $accountid;
		$this->params['MODE'] = $sandbox ? 'TEST' : 'LIVE';
		$this->secretkey = $secretkey;
	}

	private function calculateTPS($transaction, $params = [])
	{
		$tpsExtra = '';
		foreach ($this->tpsParams as $param) {
			if (isset($params[$param])) {
				$tpsExtra .= $params[$param];
			}
		}

		return bin2hex(md5(
			$this->secretkey . $this->accountid . $transaction . $tpsExtra,
			true
		));
	}

	private function request($params = [])
	{
		// $params = array_change_key_case($params, CASE_UPPER);
		$params['TAMPER_PROOF_SEAL'] = $this->calculateTPS($transaction, $params);

		return $this->guzzle->post('/', $params);
	}

	public function process($type)
	{
		$this->params['TRANS_TYPE'] = $type;

		return $this->request($type, $this->params);
	}

	public function setAmount($amount, $tip = '0.00', $tax = '0.00')
	{
		$this->params['AMOUNT'] = sprintf('%01.2f', (float) $amount);
		$this->params['AMOUNT_TIP'] = sprintf('%01.2f', (float) $tip);
		$this->params['AMOUNT_TAX'] = sprintf('%01.2f', (float) $tax);
	}

	public function setCreditCard($creditcard, $cvv2, $expire)
	{
		$this->params['PAYMENT_ACCOUNT'] = $creditcard;
		$this->params['CVV2'] = $cvv2;
		$this->params['EXPIRE'] = $expire;
		$this->params['PAYMENT_TYPE'] = 'CREDIT';
	}

	public function setACH($route, $account, $type = 'CHECKING', $memo = '')
	{
		$this->params['PAYMENT_ACCOUNT'] = "{$type}:{$route}:{$account}";
		$this->params['MEMO'] = $memo;
		$this->params['PAYMENT_TYPE'] = 'ACH';
	}

	public function setOrderId($id)
	{
		$this->params['ORDER_ID'] = $id;
	}

	public function setInvoiceId($id)
	{
		$this->params['INVOICE_ID'] = $id;
	}

	public function setRebill($doRebill, $date = '', $expires = '', $cycles = '', $amount = '')
	{
		$this->params['DO_REBILL'] = $doRebill;
		$this->params['REB_FIRST_DATE'] = $date;
		$this->params['REB_EXPR'] = $expires;
		$this->params['REB_CYCLES'] = $cycles;
		$this->params['REB_AMOUNT'] = $amount;
	}

	public function setCustomerDetails($name, $address, $address2, $city, $state, $zip, $phone, $email, $country, $ip)
	{
		$this->params['NAME1'] = $name;
		$this->params['ADDR1'] = $address;
		$this->params['ADDR2'] = $address2;
		$this->params['CITY'] = $city;
		$this->params['STATE'] = $state;
		$this->params['ZIP'] = $zip;
		$this->params['PHONE'] = $phone;
		$this->params['EMAIL'] = $email;
		$this->params['COUNTRY'] = $country;
		$this->params['CUSTOMER_IP'] = $ip;
	}

	public function reset()
	{
		$this->params = [];
	}
}