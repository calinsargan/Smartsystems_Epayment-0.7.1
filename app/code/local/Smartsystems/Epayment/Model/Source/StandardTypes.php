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
 *
 * SmartSystems ePayment Standard Types Dropdown source
 *
 * @category    SmartSystems
 * @package     SmartSystems_Epayment
 * @author      Cristian Muraru <office@smartsystems.ro>
 */
class Smartsystems_Epayment_Model_Source_StandardTypes
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Smartsystems_Epayment_Model_Standard::STANDARD_TYPE_IPN,
                'label' => Mage::helper('epayment')->__('IPN')
            ),
        );
    }
}
