<?php

class TM_FireCheckout_Block_Checkout_Shipping extends Mage_Checkout_Block_Onepage_Shipping
{
    /**
     * Added to get shipping address from quote for guests too.
     *
     * Return Sales Quote Address model (shipping address)
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }

        return $this->_address;
    }

    public function getCountryHtmlSelect($type)
    {
        $html = parent::getCountryHtmlSelect($type);
        $html = str_replace('onchange="if(window.shipping)shipping.setSameAsBilling(false);"', '', $html);
        return $html;
    }
}
