<?php

if (Mage::helper('core')->isModuleOutputEnabled('MultiSafepay_Msp')) {
    class TM_FireCheckout_Model_Service_QuoteAbstract extends MultiSafepay_Msp_Model_Service_Quote {}
} else {
    class TM_FireCheckout_Model_Service_QuoteAbstract extends Mage_Sales_Model_Service_Quote {}
}
