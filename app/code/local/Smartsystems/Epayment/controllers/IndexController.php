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
 *
 * @category   SmartSystems
 * @package    SmartSystems_Epayment
 * @copyright  Copyright (c) 2011 SmartSystems (http://www.smartsystems.ro)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Epayment Standard Checkout Controller
 *
 * @category	SmartSystems
 * @package		SmartSystems_Epayment
 * @author		Cristian Muraru <office@smartsystems.ro>
 */
class Smartsystems_Epayment_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Order instance
     */
    protected $_order;

    /**
     *  Get order
     *
     *  @param    none
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order == null) {
        }
        return $this->_order;
    }

    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton with epayment standard order transaction information
     *
     * @return Smartsystems_Epayment_Model_Standard
     */
    public function getStandard()
    {
        return Mage::getSingleton('epayment/standard');
    }

    /**
     * When a customer chooses ePayment on Checkout/Payment page
     *
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setEpaymentStandardQuoteId($session->getQuoteId());
        $this->getResponse()->setBody($this->getLayout()->createBlock('epayment/redirect')->toHtml());
        $session->unsQuoteId();
    }

    /**
     * When a customer cancel payment from ePayment.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getEpaymentStandardQuoteId(true));

        // cancel order
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
        }

        /*we are calling getEpaymentStandardQuoteId with true parameter, the session object will reset the session if parameter is true.
        so we don't need to manually unset the session*/
        //$session->unsEpaymentStandardQuoteId();

        //need to save quote as active again if the user click on cacanl payment from paypal
        //Mage::getSingleton('checkout/session')->getQuote()->setIsActive(true)->save();
        //and then redirect to checkout one page

        $this->_redirect('checkout/cart');
    }

    /**
     * when epayment returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the IPN.
     */
    public function  successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getEpaymentStandardQuoteId(true));
        /**
         * set the quote as inactive after back from ePayment
         */
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();

        //Mage::getSingleton('checkout/session')->unsQuoteId();

        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }

    /**
     * when ePayment returns via ipn
     * cannot have any output here
     * validate IPN data
     * if data is valid need to update the database that the user has
     */
    public function ipnAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('');
            return;
        }
	
	       //$this->getStandard()->setIpnFormData($this->getRequest()->getPost());
	       Mage::getSingleton('epayment/standard')->setIpnFormData($this->getRequest()->getPost());
	       $this->getResponse()->setBody($this->getStandard()->ipnPostSubmit());
    }
}
