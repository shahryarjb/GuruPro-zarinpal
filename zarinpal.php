<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Guru
 * @subpackage 	trangell_Zarinpal
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/gurupayment/zarinpal/trangell_inputcheck.php');
}

jimport('joomla.application.menu');
jimport( 'joomla.html.parameter' );

class plgGurupaymentZarinpal extends JPlugin{

	var $_db = null;
    
	function plgGurupaymentZarinpal(&$subject, $config){
		$this->_db = JFactory :: getDBO();
		parent :: __construct($subject, $config);
	}
	
	function onReceivePayment(&$post){
		if($post['processor'] != 'zarinpal'){
			return 0;
		}	
		
		$params = new JRegistry($post['params']);
		$default = $this->params;
        
		$out['sid'] = $post['sid'];
		$out['order_id'] = $post['order_id'];
		$out['processor'] = $post['processor'];
		$Amount = round($this->getPayerPrice($out['order_id']),0);

		if(isset($post['txn_id'])){
			$out['processor_id'] = JRequest::getVar('tx', $post['txn_id']);
		}
		else{
			$out['processor_id'] = "";
		}
		if(isset($post['custom'])){
			$out['customer_id'] = JRequest::getInt('cm', $post['custom']);
		}
		else{
			$out['customer_id'] = "";
		}
		if(isset($post['mc_gross'])){
			$out['price'] = JRequest::getVar('amount', JRequest::getVar('mc_amount3', JRequest::getVar('mc_amount1', $post['mc_gross'])));
		}
		else{
			$out['price'] = $Amount;
		}
		$out['pay'] = $post['pay'];
		if(isset($post['email'])){
			$out['email'] = $post['email'];
		}
		else{
			$out['email'] = "";
		}
		$out["Itemid"] = $post["Itemid"];

		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';		
		//=====================================================================

		$app	= JFactory::getApplication();
		$jinput = $app->input;
		$Authority = $jinput->get->get('Authority', '0', 'INT');
		$status = $jinput->get->get('Status', '', 'STRING');
	
		if (checkHack::checkString($status)){
			if ($status == 'OK') {
				try {
					$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
					//$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local

					$result = $client->PaymentVerification(
						[
							'MerchantID' => $params->get('merchant_id'),
							'Authority' => $Authority,
							'Amount' => $Amount/10,
						]
					);
					$resultStatus = abs($result->Status); 
					if ($resultStatus == 100) {
						$out['pay'] = 'ipn';
						$message = "کد پیگیری".$result->RefID;
						$app->enqueueMessage($message, 'message');
					} 
					else {
						$out['pay'] = 'fail';
						$msg= $this->getGateMsg($resultStatus); 
						$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					}
				}
				catch(\SoapFault $e) {
					$out['pay'] = 'fail';
					$msg= $this->getGateMsg('error'); 
					$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				}
			}
			else {
				$out['pay'] = 'fail';
				$msg= $this->getGateMsg(intval(17)); 
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
		}
		else {
			$out['pay'] = 'fail';
			$msg= $this->getGateMsg('hck2'); 
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

		return $out;
	}

	function onSendPayment(&$post){
		if($post['processor'] != 'zarinpal'){
			return false;
		}

		$params = new JRegistry($post['params']);
		$param['option'] = $post['option'];
		$param['controller'] = $post['controller'];
		$param['task'] = $post['task'];
		$param['processor'] = $post['processor'];
		$param['order_id'] = @$post['order_id'];
		$param['sid'] = @$post['sid'];
		$param['Itemid'] = isset($post['Itemid']) ? $post['Itemid'] : '0';
		foreach ($post['products'] as $i => $item){ $price += $item['value']; }  
		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';


		$app	= JFactory::getApplication();
		$Amount = round($price,0)/10;
		$Description = 'خرید محصول از فروشگاه   ';
		$Email = ''; 
		$Mobile = ''; 
		$CallbackURL = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&customer_id='.intval($post['customer_id']).'&pay=wait';
		
		try {
			$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); 	
			//$client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']); // for local

			$result = $client->PaymentRequest(
				[
				'MerchantID' => $params->get('merchant_id'),
				'Amount' => $Amount,
				'Description' => $Description,
				'Email' => $Email,
				'Mobile' => '',
				'CallbackURL' => $CallbackURL,
				]
			);
			
			$resultStatus = abs($result->Status); 
			if ($resultStatus == 100) {
				if ($params->get('zaringate') == 0){
					$app->redirect('https://www.zarinpal.com/pg/StartPay/'.$result->Authority);
				}
				else {
					$app->redirect('https://www.zarinpal.com/pg/StartPay/'.$result->Authority.'‪/ZarinGate‬‬');
				}
				//$app->redirect('https://sandbox.zarinpal.com/pg/StartPay/'.$result->Authority);  // for local/
			} else {
				$msg= $this->getGateMsg('error');
				$app->redirect($cancel_return, '<h2>'.$msg.$resultStatus .'</h2>', $msgType='Error'); 
			}
		}
		catch(\SoapFault $e) {
			$msg= $this->getGateMsg('error');
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}
	}
	
	function getGateMsg ($msgId) {
		switch($msgId){
			case	11: $out =  'شماره کارت نامعتبر است';break;
			case	12: $out =  'موجودي کافي نيست';break;
			case	13: $out =  'رمز نادرست است';break;
			case	14: $out =  'تعداد دفعات وارد کردن رمز بيش از حد مجاز است';break;
			case	15: $out =   'کارت نامعتبر است';break;
			case	17: $out =   'کاربر از انجام تراکنش منصرف شده است';break;
			case	18: $out =   'تاريخ انقضاي کارت گذشته است';break;
			case	21: $out =   'پذيرنده نامعتبر است';break;
			case	22: $out =   'ترمينال مجوز ارايه سرويس درخواستي را ندارد';break;
			case	23: $out =   'خطاي امنيتي رخ داده است';break;
			case	24: $out =   'اطلاعات کاربري پذيرنده نامعتبر است';break;
			case	25: $out =   'مبلغ نامعتبر است';break;
			case	31: $out =  'پاسخ نامعتبر است';break;
			case	32: $out =   'فرمت اطلاعات وارد شده صحيح نمي باشد';break;
			case	33: $out =   'حساب نامعتبر است';break;
			case	34: $out =   'خطاي سيستمي';break;
			case	35: $out =   'تاريخ نامعتبر است';break;
			case	41: $out =   'شماره درخواست تکراري است';break;
			case	42: $out =   'تراکنش Sale يافت نشد';break;
			case	43: $out =   'قبلا درخواست Verify داده شده است';break;
			case	44: $out =   'درخواست Verify يافت نشد';break;
			case	45: $out =   'تراکنش Settle شده است';break;
			case	46: $out =   'تراکنش Settle نشده است';break;
			case	47: $out =   'تراکنش Settle يافت نشد';break;
			case	48: $out =   'تراکنش Reverse شده است';break;
			case	49: $out =   'تراکنش Refund يافت نشد';break;
			case	51: $out =   'تراکنش تکراري است';break;
			case	52: $out =   'سرويس درخواستي موجود نمي باشد';break;
			case	54: $out =   'تراکنش مرجع موجود نيست';break;
			case	55: $out =   'تراکنش نامعتبر است';break;
			case	61: $out =   'خطا در واريز';break;
			case	100: $out =   'تراکنش با موفقيت انجام شد.';break;
			case	111: $out =   'صادر کننده کارت نامعتبر است';break;
			case	112: $out =   'خطاي سوئيچ صادر کننده کارت';break;
			case	113: $out =   'پاسخي از صادر کننده کارت دريافت نشد';break;
			case	114: $out =   'دارنده کارت مجاز به انجام اين تراکنش نيست';break;
			case	412: $out =   'شناسه قبض نادرست است';break;
			case	413: $out =   'شناسه پرداخت نادرست است';break;
			case	414: $out =   'سازمان صادر کننده قبض نامعتبر است';break;
			case	415: $out =   'زمان جلسه کاري به پايان رسيده است';break;
			case	416: $out =   'خطا در ثبت اطلاعات';break;
			case	417: $out =   'شناسه پرداخت کننده نامعتبر است';break;
			case	418: $out =   'اشکال در تعريف اطلاعات مشتري';break;
			case	419: $out =   'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است';break;
			case	421: $out =   'IP نامعتبر است';break;
			case	500: $out =   'کاربر به صفحه زرین پال رفته ولي هنوز بر نگشته است';break;
			case	'error': $out ='خطا غیر منتظره رخ داده است';break;
			case	'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case	'notff': $out = 'سفارش پیدا نشد';break;
			case	'price': $out = 'مبلغ وارد شده کمتر از ۱۰۰۰ ریال می باشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}


	function getPayerPrice ($id) {
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('amount')
			->from($db->qn('#__guru_order'));
		$query->where(
			$db->qn('userid') . ' = ' . $db->q($user->id) 
							. ' AND ' . 
			$db->qn('id') . ' = ' . $db->q($id)
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}
}
?>
