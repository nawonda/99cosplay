<?php

class TM_FireCheckout_Helper_Checkout extends Mage_Checkout_Helper_Data
{
    /**
     * Modification to skip previously approved agreements
     *
     * @return array
     */
    public function getRequiredAgreementIds()
    {
        if (is_null($this->_agreements)) {
            if (!Mage::getStoreConfigFlag('checkout/options/enable_agreements')) {
                $this->_agreements = array();
            } else {
                $this->_agreements = Mage::getModel('checkout/agreement')->getCollection()
                    ->addStoreFilter(Mage::app()->getStore()->getId())
                    ->addFieldToFilter('is_active', 1)
                    ->getAllIds();

                $approvedIds = $this->getCheckout()->getFirecheckoutApprovedAgreementIds();
                if ($approvedIds) {
                    $this->_agreements = array_diff($this->_agreements, $approvedIds);
                }
            }
        }
        return $this->_agreements;
    }
}
