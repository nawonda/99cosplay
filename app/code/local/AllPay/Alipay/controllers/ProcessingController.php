<?php
require_once(Mage::getBaseDir('app') . '/code/local/AllPay/AllPay.Payment.Integration.php');

/**
 * ProcessingController short summary.
 *
 * ProcessingController description.
 *
 * @version 1.0
 * @author andy.chao
 * @version 1.1
 * @author shawn.chang
 */
class AllPay_Alipay_ProcessingController extends Mage_Core_Controller_Front_Action {

		private function _getCheckout() {
				return $this->_getSession();
		}

    private function _getOrder($orderID = NULL) {
        if (!isset($orderID)) {
            $orderID = $this->_getSession()->getLastRealOrderId();
        }
        if ($orderID) {
            return Mage::getModel('sales/order')->loadByIncrementId($orderID);
        } else {
            return null;
        }
    }

    private function _getSession() {
        return Mage::getSingleton('checkout/session');
    }

    private function _getConfigData($keyword) {
        return Mage::getStoreConfig('payment/alipay/' . $keyword, null);
    }

    /**
     * main entry point
     */
    public function viewAction() {
        
    }

    /**
     * when customer selects AllPay payment method
     */
    public function redirectAction() {
        try {
            $oSession = $this->_getSession();
            $oOrder = $this->_getOrder();

            if (!$oOrder->getId()) {
                Mage::throwException(Mage::helper('alipay')->__('Order No not found'));
            }
            if ($oOrder->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $oOrder->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('alipay')->__('Redirect to pay page')
                )->save();

                $oOrder->sendNewOrderEmail();  //發出E-mail通知信
                $oOrder->setEmailSent(true);
            }

            if ($oSession->getQuoteId() && $oSession->getLastSuccessQuoteId()) {
                $oSession->setAllpayQuoteId($oSession->getQuoteId());
                $oSession->setAllpaySuccessQuoteId($oSession->getLastSuccessQuoteId());
                $oSession->setAllpayRealOrderId($oSession->getLastRealOrderId());
                $oSession->getQuote()->setIsActive(false)->save();
                $oSession->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * AllPay returns POST variables to this action
     */
    public function responseAction() {
        try {
            $oPayment = new AllInOne();
            $oPayment->HashKey = $this->_getConfigData('hash_key');
            $oPayment->HashIV = $this->_getConfigData('hash_iv');
            $oPayment->MerchantID = $this->_getConfigData('merchant_id');

            $arFeedback = $oPayment->CheckOutFeedback();

            $this->_processSale($arFeedback);
            
            print '1|OK';
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
            print '0|' . $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            print '0|' . $e->getMessage();
        }
    }

    /**
     * AllPay returns POST variables to this action
     */
    public function resultAction() {
        $isSuccess = FALSE;

        try {
            $oSession = $this->_getSession();
            $oSession->unsAllpayRealOrderId();
            $oSession->setQuoteId($oSession->getAllpayQuoteId(true));
            $oSession->setLastSuccessQuoteId($oSession->getAllpaySuccessQuoteId(true));

            $oPayment = new AllInOne();
            $oPayment->HashKey = $this->_getConfigData('hash_key');
            $oPayment->HashIV = $this->_getConfigData('hash_iv');
            $oPayment->MerchantID = $this->_getConfigData('merchant_id');

            $arFeedback = $oPayment->CheckOutFeedback();

            if (sizeof($arFeedback) > 0) {
                $isSuccess = $this->_processSale($arFeedback);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }

        if ($isSuccess) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('checkout/onepage/failure');
        }
    }

    /**
     * Process success response
     */
    protected function _processSale($request) {
        $isSuccess = false;

        try {
            $szTradeNo = $request['TradeNo'];
            $szMerchantTradeNo = ($this->_getConfigData('test_mode') ? str_replace($this->_getConfigData('test_order_prefix'), '', $request['MerchantTradeNo']) : $request['MerchantTradeNo']);
            $szReturnCode = $request['RtnCode'];
            $szPaymenttype = $request['PaymentType'];
            
            $oOrder = $this->_getOrder($szMerchantTradeNo);
            // check transaction amount and currency
            if ($this->_getConfigData('use_store_currency')) {
                $dePrice = number_format($oOrder->getGrandTotal(), 0, '.', '');
                $szCurrency = $oOrder->getOrderCurrencyCode();
            } else {
                $dePrice = number_format($oOrder->getBaseGrandTotal(), 0, '.', '');
                $szCurrency = $oOrder->getBaseCurrencyCode();
            }
            // check transaction amount
            if ((int) $dePrice != $request['TradeAmt']) {
                Mage::throwException('Transaction amount doesn\'t match.');
            }
            // save transaction information
            if ($request['RtnCode'] == '1' && $oOrder->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $oOrder->getPayment()
                        ->setTransactionId($szTradeNo)
                        ->setLastTransId($szMerchantTradeNo)
                        ->setCcStatus($szReturnCode)
                        ->setCcType($szPaymenttype);
                // 產生發票
                if ($oOrder->canInvoice()) {
                    $oInvoice = $oOrder->prepareInvoice();
                    $oInvoice->register()->capture();
                    $oInvoice->sendEmail(); //將發票E-mail給客戶
                    Mage::getModel('core/resource_transaction')
                            ->addObject($oInvoice)
                            ->addObject($oInvoice->getOrder())
                            ->save();
                }
                $oOrder->setState(Mage_Sales_Model_Order::STATE_PROCESSING, TRUE);
                $oOrder->save();
            }

            $isSuccess = true;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $isSuccess;
    }

}
