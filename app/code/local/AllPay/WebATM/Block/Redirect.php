<?php
require_once(Mage::getBaseDir('app') . '/code/local/AllPay/AllPay.Payment.Integration.php');

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   AllPay
 * @package    AllPay_AllInOne
 * @copyright  Copyright (c) 2010 AllPay (http://www.allpay.com.tw)
 */
class AllPay_WebATM_Block_Redirect extends Mage_Core_Block_Template {

    private function _getSession() {
        return Mage::getSingleton('checkout/session');
    }

    private function _getOrder() {
        if ($this->getOrder()) {
            return $this->getOrder();
        } elseif ($orderIncrementId = $this->_getSession()->getLastRealOrderId()) {
            return Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        } else {
            return null;
        }
    }

    private function _getConfigData($keyword) {
        return Mage::getStoreConfig('payment/webatm/' . $keyword, null);
    }

    protected function AutoSubmit() {
        $oOrder = $this->_getOrder();
        $szHtml = '';

        if ($oOrder) {
            try {
                $oPayment = new AllInOne();
                $oPayment->ServiceURL = ($this->_getConfigData('test_mode') ? 'http://payment-stage.allpay.com.tw/Cashier/AioCheckOut' : 'https://payment.allpay.com.tw/Cashier/AioCheckOut');
                $oPayment->HashKey = $this->_getConfigData('hash_key');
                $oPayment->HashIV = $this->_getConfigData('hash_iv');
                $oPayment->MerchantID = $this->_getConfigData('merchant_id');

                $oPayment->Send['ReturnURL'] = Mage::getUrl('webatm/processing/response');
                $oPayment->Send['ClientBackURL'] = Mage::getUrl('');
                $oPayment->Send['OrderResultURL'] = Mage::getUrl('webatm/processing/result');
                $oPayment->Send['MerchantTradeNo'] = ($this->_getConfigData('test_mode') ? $this->_getConfigData('test_order_prefix') : '') . $oOrder->getIncrementId();
                $oPayment->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                $oPayment->Send['TotalAmount'] = (int) $oOrder->getGrandTotal();
                $oPayment->Send['TradeDesc'] = "AllPay_Magento_Module";
                $oPayment->Send['ChoosePayment'] = PaymentMethod::WebATM;
                $oPayment->Send['Remark'] = '';
                $oPayment->Send['ChooseSubPayment'] = PaymentMethodItem::None;
                $oPayment->Send['NeedExtraPaidInfo'] = ExtraPaymentInfo::No;
                $oPayment->Send['DeviceSource'] = DeviceType::PC;

                array_push($oPayment->Send['Items'], array('Name' => Mage::helper('webatm')->__('Commodity Group'), 'Price' => (int) $oOrder->getGrandTotal(), 'Currency' => Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol(), 'Quantity' => 1, 'URL' => ''));
                
                $szHtml = $oPayment->CheckOutString();
            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
            }
        }

        return $szHtml;
    }

}
