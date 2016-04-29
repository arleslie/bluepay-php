<?php

namespace arleslie\Bluepay;

use \Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client as Guzzle;

class Bluepay
{
	const API_URL = 'https://secure.bluepay.com/interfaces/bp20post';

	private $accountid;
	private $secretkey;
	private $apis = [];
	protected $mode;

	private $tpsParams = [
		'AMOUNT',
		'MASTER_ID',
		'NAME1',
		'PAYMENT_ACCOUNT'
	];

	private $params = [];

	public function __construct($accountid, $secretkey, $sandbox = false)
	{
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

	private function request($transaction, $params = [])
	{
		// $params = array_change_key_case($params, CASE_UPPER);
		$params['TAMPER_PROOF_SEAL'] = $this->calculateTPS($transaction, $params);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::API_URL);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		$response = curl_exec($ch);
		curl_close($ch);

		parse_str($response, $response);

		return $response;
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
		$this->params['CARD_EXPIRE'] = $expire;
		$this->params['PAYMENT_TYPE'] = 'CREDIT';
	}

	public function setACH($route, $account, $type = 'C', $memo = '')
	{
		$this->params['PAYMENT_ACCOUNT'] = "{$type}:{$route}:{$account}";
		$this->params['MEMO'] = $memo;
		$this->params['PAYMENT_TYPE'] = 'ACH';
	}
	
	public function setDuplicatesAllowed($status)
	{
		$this->params['DUPLICATE_OVERRIDE'] = $status;
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

	public function setCustomerDetails($name, $name2, $address, $address2, $city, $state, $zip, $phone, $email, $country, $ip)
	{
		$this->params['NAME1'] = $name;
		$this->params['NAME2'] = $name2;
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