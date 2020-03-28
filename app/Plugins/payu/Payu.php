<?php

namespace App\Plugins\payu;

use App\Helpers\Ip;
use App\Helpers\Number;
use App\Models\Post;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use App\Helpers\Payment;
use App\Models\Package;
use Illuminate\Support\Facades\Session;
use Omnipay\Omnipay;

class Payu extends Payment
{
	/**
	 * Send Payment
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \App\Models\Post $post
	 * @return \App\Helpers\Payment|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 * @throws \Exception
	 */
	public static function sendPayment(Request $request, Post $post)
	{
		// Set URLs
		parent::$uri['previousUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['previousUrl']);
		parent::$uri['nextUrl'] = str_replace(['#entryToken', '#entryId', '#title'], [$post->tmp_token, $post->id, slugify($post->title)], parent::$uri['nextUrl']);
		parent::$uri['paymentCancelUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['paymentCancelUrl']);
		parent::$uri['paymentReturnUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['paymentReturnUrl']);
		
		// Get the Package
		$package = Package::find($request->input('package_id'));
		
		// Don't make a payment if 'price' = 0 or null
		if (empty($package) || $package->price <= 0 || empty($post)) {
			return redirect(parent::$uri['previousUrl'] . '?error=package')->withInput();
		}
		
		// Get the amount
		$amount = Number::toFloat($package->price);
		$amount = self::getAmount($amount, $package->currency_code);
		
		// Get first name & last name
		$firstName = $lastName = '';
		if (isset($post->contact_name)) {
			$tmp = splitName($post->contact_name);
			$firstName = $tmp['firstName'];
			$lastName = $tmp['lastName'];
		}
		
		// Get Merchant Info
		$posId = config('payment.payu.pos_id');
		$secondKey = config('payment.payu.second_key');
		$oAuthClientSecret = config('payment.payu.oauth_client_secret');
		$posAuthKey = null;
		
		// API Parameters
		$providerParams = [
			'purchaseData' => [
				'customerIp'    => Ip::get(),
				'continueUrl'   => parent::$uri['paymentReturnUrl'],
				'merchantPosId' => $posId,
				'description'   => config('app.name'),
				'currencyCode'  => $package->currency_code, // Set in the PayU's Dashboard (e.g. PLN)
				'totalAmount'   => $amount,
				'extOrderId'    => md5($post->id . $package->tid), // Unique value by merchantPosId
				'buyer'         => (object)[
					'email'     => isset($post->email) ? $post->email : '',
					'firstName' => $firstName,
					'lastName'  => $lastName,
					'language'  => self::getLanguage(parent::$lang->get('abbr')),
				],
				'products'      => [
					(object)[
						'name'      => $package->name,
						'unitPrice' => $amount,
						'quantity'  => 1,
					],
				],
				'payMethods'    => (object)[
					'payMethod' => (object)[
						'type'  => 'PBL', // This is for card-only forms (no bank transfers available)
						'value' => 'c',
					],
				],
			],
		];
		
		// Local Parameters
		$localParams = [
			'payment_method_id' => $request->input('payment_method_id'),
			'cancelUrl'         => parent::$uri['paymentCancelUrl'],
			'returnUrl'         => parent::$uri['paymentReturnUrl'],
			'name'              => $package->name,
			'description'       => $package->name,
			'post_id'           => $post->id,
			'package_id'        => $package->id,
			'amount'            => Number::toFloat($package->price),
			'currency'          => $package->currency_code,
		];
		
		// Try to make the Payment
		try {
			$settings = [
				'posId'        => $posId,
				'secondKey'    => $secondKey,
				'clientSecret' => $oAuthClientSecret,
				'testMode'     => (config('payment.payu.mode') == 'sandbox') ? true : false,
				'posAuthKey'   => $posAuthKey,
			];
			$gateway = Omnipay::create('PayU');
			$gateway->initialize($settings);
			
			// Make the payment
			$response = $gateway->purchase($providerParams)->send();
			
			// Save the Transaction ID at the Provider
			$localParams['transaction_id'] = $response->getTransactionId();
			
			// Save local parameters into session
			Session::put('params', $localParams);
			Session::save();
			
			// Payment by Credit Card when Card info are provide from the form.
			if ($response->isSuccessful()) {
				
				// Check if redirection to offsite payment gateway is needed
				if ($response->isRedirect()) {
					return $response->redirect();
				}
				
				// Apply actions after successful Payment
				return self::paymentConfirmationActions($localParams, $post);
				
			} elseif ($response->isRedirect()) {
				
				// Redirect to offsite payment gateway
				return $response->redirect();
				
			} else {
				
				// Apply actions when Payment failed
				return parent::paymentFailureActions($post, $response->getMessage());
				
			}
		} catch (\Exception $e) {
			
			// Apply actions when API failed
			return parent::paymentApiErrorActions($post, $e);
			
		}
	}
	
	/**
	 * @param $params
	 * @param $post
	 * @return \App\Helpers\Payment|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 * @throws \Exception
	 */
	public static function paymentConfirmation($params, $post)
	{
		// Set form page URL
		parent::$uri['previousUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['previousUrl']);
		parent::$uri['nextUrl'] = str_replace(['#entryToken', '#entryId', '#title'], [$post->tmp_token, $post->id, slugify($post->title)], parent::$uri['nextUrl']);
		
		// Check if the Payment was successful
		if (isset($params['transaction_id']) && !empty($params['transaction_id'])) {
			
			// Apply actions after successful Payment
			return parent::paymentConfirmationActions($params, $post);
			
		} else {
			
			// Apply actions when Payment failed
			return parent::paymentFailureActions($post);
			
		}
	}
	
	/**
	 * Amount's specificity for PayU
	 * Note: specify prices using the lowest currency unit
	 * e.g. in lowest currency unit for PLN, so 1000 is equal to 10 PLN. HUF is the exception – multiply this by 100.
	 *
	 * @param $amount
	 * @param $currencyCode
	 * @return int
	 */
	private static function getAmount($amount, $currencyCode)
	{
		$exceptCurrencies = ['HUF'];
		
		if (!in_array($currencyCode, $exceptCurrencies)) {
			$amount = intval((float)$amount * 100);
		}
		
		return $amount;
	}
	
	/**
	 * Available language versions
	 * http://developers.payu.com/en/restapi.html
	 *
	 * @param $languageCode
	 * @return string
	 */
	private static function getLanguage($languageCode)
	{
		$validLanguages = ['pl', 'en', 'cs', 'bg', 'de', 'ee', 'el', 'es', 'fi', 'fr', 'hr', 'hu', 'it', 'lt', 'lv', 'pt', 'ro', 'ru', 'sk', 'sl', 'uk'];
		if (!in_array($languageCode, $validLanguages)) {
			return 'en';
		}
		
		return $languageCode;
	}
	
	/**
	 * @return array
	 */
	public static function getOptions()
	{
		$options = [];
		
		$paymentMethod = PaymentMethod::active()->where('name', 'payu')->first();
		if (!empty($paymentMethod)) {
			$options[] = (object)[
				'name'     => mb_ucfirst(trans('admin::messages.settings')),
				'url'      => admin_url('payment_methods/' . $paymentMethod->id . '/edit'),
				'btnClass' => 'btn-info',
			];
		}
		
		return $options;
	}
	
	/**
	 * @return bool
	 */
	public static function installed()
	{
		$paymentMethod = PaymentMethod::active()->where('name', 'payu')->first();
		if (empty($paymentMethod)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	public static function install()
	{
		// Remove the plugin entry
		self::uninstall();
		
		// Plugin data
		$data = [
			'id'                => 4,
			'name'              => 'payu',
			'display_name'      => 'PayU',
			'description'       => 'Payment with PayU',
			'has_ccbox'         => 1,
			'is_compatible_api' => 0,
			'lft'               => 4,
			'rgt'               => 4,
			'depth'             => 1,
			'active'            => 1,
		];
		
		try {
			// Create plugin data
			$paymentMethod = PaymentMethod::create($data);
			if (empty($paymentMethod)) {
				return false;
			}
		} catch (\Exception $e) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	public static function uninstall()
	{
		$paymentMethod = PaymentMethod::where('name', 'payu')->first();
		if (!empty($paymentMethod)) {
			$deleted = $paymentMethod->delete();
			if ($deleted > 0) {
				return true;
			}
		}
		
		return false;
	}
}
