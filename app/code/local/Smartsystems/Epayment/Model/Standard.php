<?php
/**
 * SmartSystems
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to office@smartsystems.ro so we can send you a copy immediately.
 *
 * @category   SmartSystems
 * @package    SmartSystems_Epayment
 * @copyright  Copyright (c) 2011 SmartSystems (http://www.smartsystems.ro)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 *
 * Epayment Standard Checkout Module
 *
 * @category	SmartSystems
 * @package		SmartSystems_Epayment
 * @author		Cristian Muraru <office@smartsystems.ro>
 */

class Smartsystems_Epayment_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
 	const PAYMENT_TYPE_AUTH = 'AUTHORIZATION';
	const STANDARD_TYPE_IPN = 'IPN';
	const PAYMENT_TYPE_SALE = 'SALE';
	const LOGO_URL = 'https://secure.epayment.ro/images/epayment/powerby-ePayment.gif';

	protected $_code = 'epayment';
  protected $_isGateway               = true;
  protected $_canAuthorize            = true;
  protected $_canCapture              = true;
  protected $_canCapturePartial       = false;
  protected $_canRefund               = false;
  protected $_canVoid                 = true;
  protected $_canUseInternal          = false;
  protected $_canUseCheckout          = true;
  protected $_canUseForMultishipping  = true;
  protected $_canSaveCc = false;

	protected $_stObj;

	protected $_formBlockType = 'epayment/form';
	protected $_allowCurrencyCode = array('RON', 'EUR', 'USD');
	protected $_allowLanguageCode = array('RO','EN', 'DE', 'FR', 'IT', 'ES');

     /**
     * Get epayment session namespace
     *
     * @return Smartsystems_Epayment_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('epayment/session');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
    
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('epayment/form', $name)
            ->setMethod('epayment_standard')
            ->setPayment($this->getPayment())
            ->setTemplate('epayment/form.phtml');

        return $block;
    }


    public function getEpaymentUrl()
    {
        return Mage::getStoreConfig('payment/epayment/cgi_url');
    }

    public function getOrderPlaceRedirectUrl()
    {
      return Mage::getUrl('epayment/index/redirect', array('_secure' => true));
    }


    public function isInitializeNeeded()
    {
        return true;
    }

    public function initialize($paymentAction, $stateObject)
    {
      $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
      $stateObject->setState($state);	
	    $stateObject->setStatus('pending_epayment');
      $stateObject->setIsNotified(false);
    }

    /**
     * Validate the currency code and language code used by ePayment
     *
     * @return Smartsystems_Epayment_Model_Standard
     */
    public function validate()
    {
        parent::validate();
        $currency_code = $this->getQuote()->getBaseCurrencyCode();
        if (!in_array($currency_code,$this->_allowCurrencyCode)) {
            Mage::throwException(Mage::helper('epayment')->__('Selected currency code (%s) is not compatible with ePayment',$currency_code));
        }
	      /*
        $language_code = $this->getQuote()->getBaseLanguageCode();
        if (!in_array($language_code,$this->_allowLanguageCode)) {
            Mage::throwException(Mage::helper('epayment')->__('Selected language code ('.$language_code.') is not compatible with ePayment'));
        }
	     */
        return $this;
    }

    public function getCheckoutFormFields()
    {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $salesEntity = $order;
        
        //if ($this->getQuote()->getIsVirtual()) {
        if ($salesEntity->getIsVirtual()) {        
            //$a = $this->getQuote()->getBillingAddress();
            //$b = $this->getQuote()->getShippingAddress();
            $a = $salesEntity->getBillingAddress();
            $b = $salesEntity->getShippingAddress();
        } else {
            //$a = $this->getQuote()->getShippingAddress();
            //$b = $this->getQuote()->getBillingAddress();
            $a = $salesEntity->getShippingAddress();
            $b = $salesEntity->getBillingAddress();            
        }

        //getQuoteCurrencyCode
        //$currency_code = $this->getQuote()->getBaseCurrencyCode();
        $currency_code = $salesEntity->getBaseCurrencyCode();


      $sArr = array(
            'MERCHANT'        => 	Mage::getStoreConfig('payment/epayment/partner'),
		        'ORDER_REF'				=>	$orderIncrementId, //$this->getCheckout()->getLastRealOrderId(),
		        //'ORDER_DATE'			=>	date( 'Y-m-d H:i:s', strtotime( $this->getCheckout()->getCreatedAt() ) ),
		        'ORDER_DATE'			=>	date( 'Y-m-d H:i:s', strtotime( $salesEntity->getCreatedAt() ) ),		        
        );

      $arrPlus1= array(
      		        'PRICES_CURRENCY'			=>	$currency_code,
      		        //'DISCOUNT'				    =>	sprintf('%.2f',$a->getBaseDiscountAmount()+$b->getBaseDiscountAmount()),
      		        'DISCOUNT'			=>	sprintf('%.2f',abs($a->getBaseDiscountAmount())+abs($b->getBaseDiscountAmount())+abs($salesEntity->getBaseDiscountAmount())),
                  'DESTINATION_CITY'    => 	$a->getCity(),
                  'DESTINATION_STATE'   => 	$a->getRegionCode(),
                  'DESTINATION_COUNTRY' => 	$a->getCountry(),
        );

      $arrPlus2=array(
      		'TESTORDER'				    =>	Mage::getStoreConfig('payment/epayment/test')?'TRUE':'FALSE',
      		'BILL_FNAME'			    =>	$b->getFirstname(),
      		'BILL_LNAME'			    =>	$b->getLastname(),
      		'BILL_CNP'			      =>	$b->getCnp(),
      		'BILL_ADDRESS'			  => 	$b->getStreet(1),
      		'BILL_ADDRESS2'		    => 	$b->getStreet(2),
      		'BILL_CITY'			      =>	$b->getCity(),
      		'BILL_STATE'			    =>	$b->getRegion(),
      		'BILL_COUNTRYCODE'	  =>	$b->getCountry(),
      		'BILL_EMAIL'          =>  $salesEntity->getCustomerEmail(),
      		'DELIVERY_FNAME'			=> 	$a->getFirstname(),
      		'DELIVERY_LNAME'			=> 	$a->getLastname(),
      		'DELIVERY_ADDRESS'		=> 	$a->getStreet(1),
      		'DELIVERY_ADDRESS2'		=> 	$a->getStreet(2),
      
      	);


	    //tranzactii individuale - dupa cum stie ePayment
      //s$items = $this->getQuote()->getItemsCollection();//getAllItems();
      $items = $salesEntity->getAllItems();
      //$items = $this->getSession()->getAllItems();
      //var_dump($items->getItemsCount());die;
	    $arrProd= array();
      if ($items) {
      	$i = 1;
            foreach($items as $item){
              //var_dump($item); die;
            	if ($item->getParentItem()) {
                  	continue;
            	}
              //echo "<pre>"; print_r($item->getData()); echo"</pre>";
        			$arrProd['ORDER_PNAME'][]=$item->getName();
        			$arrProd['ORDER_PCODE'][]=$item->getSku();
        			$arrProd['ORDER_PINFO'][]=$item->getDescription();
        			$arrProd['ORDER_PRICE'][]=sprintf('%.2f',$item->getPrice());
        			$arrProd['ORDER_QTY'][]=$item->getQtyOrdered();
        			$arrProd['ORDER_VAT'][]=sprintf('%.2f',$item->getTaxPercent());
        			/*
              $sArr = array_merge($sArr, array(
        				'ORDER_PNAME[]'	=> 	$item->getName(),
        				'ORDER_PCODE[]'	=>	$item->getSku(),
        				'ORDER_PINFO[]'	=>	$item->getDescription(),
        				'ORDER_PRICE[]'	=>	sprintf('%.2f',$item->getPrice()),
        				'ORDER_QTY[]'	=>	$item->getQty(),
        				'ORDER_VAT[]'	=>	sprintf('%.2f',$item->getTaxPercent()),
                ));
        			*/
              $i++;
             }
        }

	     $sArr= array_merge($sArr,$arrProd);

        //$totalArr = $a->getTotals();
        //$shipping = sprintf('%.2f', $this->getQuote()->getShippingAddress()->getBaseShippingAmount());
        $shipping = sprintf('%.2f', $salesEntity->getBaseShippingAmount());
        $sArr = array_merge($sArr, array(
	     	  'ORDER_SHIPPING'	=>	sprintf('%.2f',$shipping),
        ));

	     $sArr= array_merge($sArr,$arrPlus1);


        $sReq = '';
        $rArr = array();
        foreach ($sArr as $k=>$v){
            /*
            replacing & char with and. otherwise it will break the post
            */
            $value =  str_replace("&","and",$v);
            $rArr[$k] =  $value;
            //$sReq .= '&'.$k.'='.$value;
        }
      	//echo "<pre>".var_dump($rArr)."</pre>";
      	$arrHashmac = array(
      		'MERCHANT'			      =>	$rArr['MERCHANT'],
      		'ORDER_REF'			      =>	$rArr['ORDER_REF'],
      		'ORDER_DATE'		      =>	$rArr['ORDER_DATE'],
      		'ORDER_PNAME'		      =>	$rArr['ORDER_PNAME'],
      		'ORDER_PCODE'		      =>	$rArr['ORDER_PCODE'],
      		'ORDER_PINFO'		      =>	$rArr['ORDER_PINFO'],
      		'ORDER_PRICE'		      =>	$rArr['ORDER_PRICE'],
      		'ORDER_QTY'			      =>	$rArr['ORDER_QTY'],
      		'ORDER_VAT'			      =>	$rArr['ORDER_VAT'],
      		'ORDER_SHIPPING'		  =>	$rArr['ORDER_SHIPPING'],
      		'PRICES_CURRENCY'		  =>	$rArr['PRICES_CURRENCY'],
      		'DISCOUNT'			      =>	$rArr['DISCOUNT'],
      		'DESTINATION_CITY'	  =>	$rArr['DESTINATION_CITY'],
      		'DESTINATION_STATE'	  =>	$rArr['DESTINATION_STATE'],
      		'DESTINATION_COUNTRY'	=>	$rArr['DESTINATION_COUNTRY'],
      		//'PAY_METHOD'		=>	$rArr['PAY_METHOD'],
      		//'ORDER_PGROUP']		=>	$rArr['ORDER_PGROUP'],
      	);
	
	    $hmacHash = $this->hmac(Mage::getStoreConfig('payment/epayment/trans_key'), $this->getHmacString($arrHashmac));
	
    	$rArr= array_merge($rArr, array(
    		'ORDER_HASH'	=> $hmacHash,
    	));
	
	     $rArr = array_merge($rArr,$arrPlus2);
      return $rArr;
    }


	//functii legate de hashmac

		/**
		 * getHmacString
		 *
		 * Creates source string for hmac hash
		 * THIS FUNCTION SHOULD NOT BE MODIFIED.
		 *
		 * @access		private
		 * @return		string
		 */
		private function getHmacString ($arrData) {
			$retval = "";
			$retval .= $this->expandString($arrData['MERCHANT']);
			$retval .= $this->expandString($arrData['ORDER_REF']);
			$retval .= $this->expandString($arrData['ORDER_DATE']);
			$retval .= $this->expandArray($arrData['ORDER_PNAME']);
			$retval .= $this->expandArray($arrData['ORDER_PCODE']);
			if (is_array($arrData['ORDER_PINFO']) && !empty($arrData['ORDER_PINFO']))
				$retval .= $this->expandArray($arrData['ORDER_PINFO']);
			$retval .= $this->expandArray($arrData['ORDER_PRICE']);
			$retval .= $this->expandArray($arrData['ORDER_QTY']);
			$retval .= $this->expandArray($arrData['ORDER_VAT']);

			//if (is_array($arrData['ORDER_VER']) && !empty($arrData['ORDER_VER']))
			//	$retval .= $this->expandArray($arrData['ORDER_VER']);

			//if(!empty($this->orderShipping))

			if (is_numeric($arrData['ORDER_SHIPPING']) && $arrData['ORDER_SHIPPING'] >= 0){
				$retval .= $this->expandString($arrData['ORDER_SHIPPING']);
			}

			if (is_string($arrData['PRICES_CURRENCY']) && !empty($arrData['PRICES_CURRENCY'])){
				$retval .= $this->expandString($arrData['PRICES_CURRENCY']);
			}
			if (is_numeric($arrData['DISCOUNT']) && $arrData['DISCOUNT'] >=0 ){
				$retval .= $this->expandString($arrData['DISCOUNT']);
			}
			if (is_string($arrData['DESTINATION_CITY']) && !empty($arrData['DESTINATION_CITY'])){
				$retval .= $this->expandString($arrData['DESTINATION_CITY']);
			}
			//if (is_string($arrData['DESTINATION_STATE']) && !empty($arrData['DESTINATION_STATE'])){
				$retval .= $this->expandString($arrData['DESTINATION_STATE']);
			//}
			if (is_string($arrData['DESTINATION_COUNTRY']) && !empty($arrData['DESTINATION_COUNTRY'])){
				$retval .= $this->expandString($arrData['DESTINATION_COUNTRY']);
			}

			//if (is_string($arrData['PAY_METHOD']) && !empty($arrData['PAY_METHOD']))
			//	$retval .= $this->expandString($arrData['PAY_METHOD']);
			//if (is_array($arrData['ORDER_PGROUP']) && count($arrData['ORDER_PGROUP']))
			//	$retval .= $this->expandArray($arrData['ORDER_PGROUP']);
			
			return $retval;
		}

		/**
		 * expandString
		 *
		 * Outputs a string for hmac format. For a string like 'a' it will return '1a'.
		 *
		 * @access		private
		 * @param		$string string
		 * @return 		string
		 */
		private function expandString ($string) {
			$retval = "";
			//$string = htmlspecialchars($string);
			$size = strlen($string);
			$retval = $size . $string;
			return $retval;
		}

		/**
		 * expandArray
		 *
		 * The same as expandString except that it receives an array of strings and
		 * returns the string from all values within the array.
		 *
		 * @param		$array array
		 * @return		string
		 */
		private function expandArray($array) {
			$retval = "";
			for ($i = 0; $i < count($array); $i++)
				$retval .= $this->expandString($array[$i]);
			return $retval;
		}
		function ArrayExpand($array){
			$retval = "";
			for($i = 0; $i < sizeof($array); $i++){
				$size		= strlen(stripslashes($array[$i]));
				$retval	.= $size.stripslashes($array[$i]);
			}
			return $retval;
		}

		/**
		 * hmac
		 *
		 * Build HMAC key. THIS FUNCTION SHOULD NOT BE MODIFIED.
		 *
		 * @param		$array string secret key
		 * @param		@data string the source string that will be converted into hmac hash
		 * @return		string hmac hash
		 */
		private function hmac ($key, $data) {
		   $b = 64; // byte length for md5
		   if (strlen($key) > $b) {
			   $key = pack("H*",md5($key));
		   }
		   $key  = str_pad($key, $b, chr(0x00));
		   $ipad = str_pad('', $b, chr(0x36));
		   $opad = str_pad('', $b, chr(0x5c));
		   $k_ipad = $key ^ $ipad ;
		   $k_opad = $key ^ $opad;
		   return md5($k_opad  . pack("H*",md5($k_ipad . $data)));
		}


    public function ipnPostSubmit(){

    	$result		= ""; 				/* string for compute HASH for received data */
    	$return		= ""; 				/* string to compute HASH for return result */
    	$signature	= $this->getIpnFormData('HASH'); //$_POST["HASH"];	/* HASH received */
    	$body		= "";
    	$pass = Mage::getStoreConfig('payment/epayment/trans_key');
    	
    
    	//Mage::log("Preluare informatii IPN - before...");
    	/* read info received */
    	//ob_start();
    	while(list($key, $val) = each($_POST)){
    		$$key=$val;
    
    		/* get values */
    		if($key != "HASH"){
    
    			if(is_array($val)){
    				$result .= $this->ArrayExpand($val);
    			}else{
    				$size		= strlen(stripslashes($val));
    				$result	.= $size.stripslashes($val);
    			}
    		}
    	}
    	    //$body = ob_get_contents();
    	    //ob_end_flush();
          //$http = new Varien_Http_Adapter_Curl();
          //$http->write(Zend_Http_Client::POST,$this->getEpaymentUrl(), '1.1', array(), $sReq);
          //$body = $http->read();
    
    	//Mage::log("Preluare informatii IPN - after...");
    
    	$date_return = date("YmdGis");
    	$return = strlen($_POST["IPN_PID"][0]).$_POST["IPN_PID"][0].strlen($_POST["IPN_PNAME"][0]).$_POST["IPN_PNAME"][0];
    	$return .= strlen($_POST["IPN_DATE"]).$_POST["IPN_DATE"].strlen($date_return).$date_return;
    	$hash =  $this->hmac($pass, $result); /* HASH for data received */
    	$body .= $result."\r\n\r\nHash: ".$hash."\r\n\r\nSignature: ".$signature."\r\n\r\nReturnSTR: ".$return;
    	
    	$id = $this->getIpnFormData('REFNOEXT');
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($id);

    	//Mage::log("Preluare informatii IPN - before hash test...");
    	if($hash == $signature){
    		//Mage::log("Verified OK!");
    		/* ePayment response */
    		$result_hash =  $this->hmac($pass, $return);
    
    		$body .= "<br><EPAYMENT>".$date_return."|".$result_hash."</EPAYMENT>";
    		$body = "<EPAYMENT>".$date_return."|".$result_hash."</EPAYMENT>";
    		Mage::log($body);
    		//echo "<EPAYMENT>".$date_return."|".$result_hash."</EPAYMENT>";
    		/* Begin automated procedures (START YOUR CODE)*/
                if (!$order->getId()) {
                    /*
                    * need to have logic when there is no order with the order id from epayment
                    */
    			     //Mage::log("Order ID: ".$id ." does not exist !");
                } else {
    			     //Mage::log("Processing ... ");
                	if ($this->getIpnFormData('IPN_TOTALGENERAL') != $order->getBaseGrandTotal()) {
                      	//when grand total does not equal, need to have some logic to take care
                      	$order->addStatusToHistory(
                	          	$order->getStatus(),//continue setting current order status
        		      	      Mage::helper('epayment')->__('Order total amount does not match epayment gross total amount')
    	                   );
                        $order->save();
    			       //Mage::log("Order total amount does not match epayment gross total amount");
                    }else {
    			/*
                        //quote id
                        $quote_id = $order->getQuoteId();
                        //the customer close the browser or going back after submitting payment
                        //so the quote is still in session and need to clear the session
                        //and send email
                        if ($this->getQuote() && $this->getQuote()->getId()== $quote_id) {
                            $this->getCheckout()->clear();
                            $order->sendNewOrderEmail();
                        }
                      */
    
                        // get from config order status to be set
                        $newOrderStatus = $this->getConfigData('order_status', $order->getStoreId());
    			             //Mage::log("Setare nou status pentru order - before...");
                        if (empty($newOrderStatus)) {
    				          //Mage::log("Setare nou status pentru order - efectiv: ".$order->getStatus());
                            $newOrderStatus = $order->getStatus();
                        }
    
                        /*
                        if payer_status=verified ==> transaction in sale mode
                        if transactin in sale mode, we need to create an invoice
                        otherwise transaction in authorization mode
                        */
                        if (strtoupper($this->getIpnFormData('ORDERSTATUS')) == 'COMPLETE') {
    			  	          //Mage::log("Order status este COMPLETE...");
                           	if (!$order->canInvoice()) {
                               //when order cannot create invoice, need to have some logic to take care
                               $order->addStatusToHistory(
                                    $order->getStatus(), // keep order status/state
                                    Mage::helper('epayment')->__('Error in creating an invoice', true),
                                    $notified = true
                               );
    				            //Mage::log("Nu pot crea INVOICE...");
                           } else {
                               //need to save transaction id...
                               $order->getPayment()->setTransactionId($this->getIpnFormData('REFNO'));
                               //need to convert from order into invoice ...
                               $invoice = $order->prepareInvoice();
                               $invoice->register()->capture();
                               Mage::getModel('core/resource_transaction')
                                   ->addObject($invoice)
                                   ->addObject($invoice->getOrder())
                                   ->save();
                                   
                              /* - Nu merge in 1.7.x
                              $order->setState(
                                   Mage_Sales_Model_Order::STATE_COMPLETE, $newOrderStatus,
                                   Mage::helper('epayment')->__('Invoice #%s created', $invoice->getIncrementId()),
                                   $notified = true
                               );
                               */
                               $order->addStatusToHistory(
                                    Mage_Sales_Model_Order::STATE_COMPLETE, // complete
                                    Mage::helper('epayment')->__('Invoice #%s created', $invoice->getIncrementId()),
                                    $notified = true
                               );
                              //$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_COMPLETE);
                              //$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                              //$order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE);
                              //$history = $order->addStatusHistoryComment(Mage::helper('epayment')->__('Invoice #%s created', $invoice->getIncrementId(), false);
                              //$history->setIsCustomerNotified(true);
                              //$order->save();
    					           //Mage::log("Invoice  creat..");
                           }
                        } else { 
                    				/*
                    				 alte stari 
                    					TEST,
                    					CASH,
                    					REVERSED,
                    					REFUND,
                    					PAYMENT_AUTHORIZED,
                    					PAYMENT_RECEIVED
                    				*/
                            //$order->setState(
                            //    Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus,
                            //    Mage::helper('epayment')->__('Received IPN verification'),
                            //    $notified = true
                            //);
    
     				$payment_status= strtoupper($this->getIpnFormData('ORDERSTATUS')); //$this->getIpnFormData('payment_status');
                		$comment = $payment_status;
    
                		//response error
                		if (!$order->getId()) {
                   			/*
                    			* need to have logic when there is no order with the order id from ePayment
                    			*/
                		} else {
    					$tmpStatus = '';
    					switch($payment_status){
    					case 'PAYMENT_AUTHORIZED':
    						$tmpStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
    						break;
    					case 'PAYMENT_RECEIVED':
    						$tmpStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
    						break;
    					case 'REVERSED':
    						$tmpStatus = Mage_Sales_Model_Order::STATE_CANCELED;
    						break;
    					case 'REFUND':
    						$tmpStatus = Mage_Sales_Model_Order::STATE_CANCELED;
    						break;
    					case 'CASH':
    						$tmpStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
    						break;
    					case "-":
    						$tmpStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
    						break;
    					default:
    						$tmpStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
    					}
    					
    					//Mage::log("Setare status order: ". $payment_status );
    
    					$order->setState(
    						$tmpStatus, $payment_status,
                    				Mage::helper('epayment')->__('GeCAD ePayment IPN status: %s.', $comment),
                                    	$notified = true
    					);
    					/*
                    			$order->addStatusToHistory(
                        			$order->getStatus(),//continue setting current order status
                        			Mage::helper('epayment')->__('GeCAD ePayment IPN Invalid %s.', $comment),
                                    	$notified = true
                    			);
    					*/
                    			//$order->save();
                		}
                        }
                        $order->save();
                        $order->sendNewOrderEmail();
    			//Mage::log("Mailul de confirmare a fost trimis !");			
    		    }
    		}
    	}else{
    	    /* warning email */
    		//mail("webmaster@gecad.ro","BAD IPN Signature", $body,"");
    		Mage::log("BAD IPN signature!!!!!");
    	}
    	//$this->getResponse()->setBody($body)->toHtml());
    	return $body;
    }
}