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
 * Epayment Redirect block
 *
 * @category    SmartSystems
 * @package     SmartSystems_Epayment
 * @author	    Cristian Muraru <office@smartsystems.ro>
 */
class Smartsystems_Epayment_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
      $epay = Mage::getModel('epayment/standard');

      $form = new Smartsystems_Epayment_Data_Form();
      $form->setAction($epay->getEpaymentUrl())
          	->setId('frmForm')
            ->setName('frmForm')
            ->setMethod('POST')
            ->setUseContainer(true);
      foreach ($epay->getCheckoutFormFields() as $field=>$value) {
		  if(is_array($value)){
			 foreach($value as $k=>$val){
				$form->addField($field."_".$k, 'hidden', array('name'=>$field."[]", 'value'=>$val));
			 }
		  }else{
            	$form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
		  }
      }
      $form->addField('BACK_REF','hidden',array('name'=>'BACK_REF','value'=>Mage::getUrl('epayment/index/success')));
      $html = '<html><body>';
      $html.= $this->__('You will be redirected to ePayment in a few seconds.');
      $html.= $form->toHtml();
      $html.= '<script type="text/javascript">document.frmForm.submit();</script>';
      $html.= '</body></html>';

      return $html;
    }
}