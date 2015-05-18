<?php

class TM_FireCheckout_Model_Observer
{
    public function addToCartComplete(Varien_Event_Observer $observer)
    {
        $generalConfig = Mage::getStoreConfig('firecheckout/general');
        if (($generalConfig['enabled'] && $generalConfig['redirect_to_checkout'])
            || $observer->getRequest()->getParam('firecheckout')) {

            $observer->getResponse()
                ->setRedirect(
                    Mage::helper('firecheckout/url')->getCheckoutUrl()
                );
            Mage::getSingleton('checkout/session')->setNoCartRedirect(true);
        }
    }

    public function addAdditionalFieldsToResponseFrontend(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('payment/authorizenet_directpost/active')) {
            return Mage::getSingleton('authorizenet/directpost_observer')->addAdditionalFieldsToResponseFrontend($observer);
        }
        return $this;
    }

    /**
     * Saves customer comment and delivery date to quote
     */
    public function adminhtmlAddAdditionalFields($observer)
    {
        $quote   = $observer->getOrderCreateModel()->getQuote();
        $request = $observer->getRequest();

        if (isset($request['firecheckout_customer_comment'])) {
            $quote->setFirecheckoutCustomerComment($request['firecheckout_customer_comment']);
        }

        if (!isset($request['delivery_date']) || !is_array($request['delivery_date'])) {
            return;
        }

        $firecheckout = Mage::getModel('firecheckout/type_standard')->setQuote($quote);
        $result = $firecheckout->saveDeliveryDate($request['delivery_date'], false);

        if (is_array($result)) {
            throw new Exception($result['message']);
        }
    }

    public function validateAddressInformation($observer)
    {
        if (!Mage::getStoreConfigFlag('firecheckout/address_verification/enabled')) {
            return $this;
        }

        $controller       = $observer->getControllerAction();
        $request          = $controller->getRequest();
        $skipVerification = $request->getQuery('skip-address-verification');
        $block            = $controller->getLayout()
            ->createBlock('firecheckout/address_validator')
            ->setValidator(Mage::getModel('firecheckout/address_validator_usps'));

        $billing = $request->getPost('billing', array());
        if (!$request->getPost('billing_address_id')
            && !$this->_canSkipAddressVerification($billing, $skipVerification)) {

            $block->getValidator()->addAddress($billing, 'billing');
        }

        if (!isset($billing['use_for_shipping']) || !$billing['use_for_shipping']) {
            $shipping = $request->getPost('shipping', array());
            if (!$request->getPost('shipping_address_id')
                && !$this->_canSkipAddressVerification($shipping, $skipVerification)) {

                $block->getValidator()->addAddress($shipping, 'shipping');
            }
        }

        if (!$block->getValidator()->isValid()) {
            $result = array();
            $result['body']['content'] = $block->toHtml();
            $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
            $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            return;
        }

        return $this;
    }

    protected function _canSkipAddressVerification($address, $skipVerification)
    {
        if (!isset($address['country_id'])
            || 'US' != $address['country_id']
            || !isset($address['region_id'])) {

            return true;
        }

        $session = Mage::getSingleton('checkout/session');
        $key = md5(implode('_', array(
            'FIRECHECKOUT_ADDRESS_VERIFICATION_SKIP_',
            $address['street'][0],
            isset($address['street'][1]) ? $address['street'][1] : '',
            $address['city'],
            $address['region_id'],
            $address['postcode']
        )));

        if ($session->hasData($key)) { // previously marked as verified
            return true;
        }

        if ($skipVerification) {
            $session->setData($key, 1);
            return true;
        }

        return false;
    }

    public function addThirdPartyModulesLayoutUpdate($observer)
    {
        $helper  = Mage::helper('core');
        $updates = $observer->getUpdates();
        $mapping = array(
            'Adyen_Payment'              => 'tm/firecheckout/adyen_payment.xml',
            'Aicod_Italy'                => 'tm/firecheckout/aicod_italy.xml',
            'Aitoc_Aitgiftwrap'          => 'tm/firecheckout/aitoc_aitgiftwrap.xml',
            'Amasty_Deliverydate'        => 'tm/firecheckout/amasty_deliverydate.xml',
            'AW_Advancednewsletter'      => 'tm/firecheckout/aw_newsletter.xml',
            'AW_Newsletter'              => 'tm/firecheckout/aw_newsletter.xml',
            'AW_Storecredit'             => 'tm/firecheckout/aw_storecredit.xml',
            'Billpay'                    => 'tm/firecheckout/billpay.xml',
            'Bitpay_Bitcoins'            => 'tm/firecheckout/bitpay_bitcoins.xml',
            'Bpost_ShippingManager'      => 'tm/firecheckout/bpost_shippingmanager.xml',
            'Braintree'                  => 'tm/firecheckout/braintree.xml',
            'Bysoft_Relaypoint'          => 'tm/firecheckout/bysoft_relaypoint.xml', // not confirmed module code
            'CraftyClicks'               => 'tm/firecheckout/craftyclicks.xml',
            'Customweb_PayUnityCw'       => 'tm/firecheckout/customweb_payunitycw.xml',
            'DPD_Shipping'               => 'tm/firecheckout/dpd_shipping.xml',
            'Ebizmarts_MageMonkey'       => 'tm/firecheckout/ebizmarts_magemonkey.xml',
            'Ebizmarts_SagePaySuite'     => 'tm/firecheckout/ebizmarts_sagepaysuite.xml',
            'Emja_TaxRelief'             => 'tm/firecheckout/emja_taxrelief.xml',
            'Emjainteractive_ShippingOption' => 'tm/firecheckout/emjainteractive_shippingoption.xml',
            'Enterprise_Enterprise'      => 'tm/firecheckout/mage_enterprise.xml',
            'GCMC_GiveChange'            => 'tm/firecheckout/gcmc_givechange.xml',
            'Geissweb_Euvatgrouper'      => 'tm/firecheckout/geissweb_euvatgrouper.xml',
            'Inchoo_SocialConnect'       => 'tm/firecheckout/inchoo_socialconnect.xml',
            'Infolution_ILStrongCaptcha' => 'tm/firecheckout/infolution_ilstrongcaptcha.xml',
            'IntellectLabs_Stripe'       => 'tm/firecheckout/intellectlabs_stripe.xml',
            'IrvineSystems_Deliverydate' => 'tm/firecheckout/irvinesystems_deliverydate.xml',
            'IrvineSystems_JapanPost'    => 'tm/firecheckout/irvinesystems_japanpost.xml',
            'IrvineSystems_Sagawa'       => 'tm/firecheckout/irvinesystems_sagawa.xml',
            'IrvineSystems_Seino'        => 'tm/firecheckout/irvinesystems_seino.xml',
            'IrvineSystems_Yamato'       => 'tm/firecheckout/irvinesystems_yamato.xml',
            'IWD_OnepageCheckoutSignature' => 'tm/firecheckout/iwd_opc_signature.xml',
            'Kiala_LocateAndSelect'      => 'tm/firecheckout/kiala_locateandselect.xml',
            'Klarna_KlarnaPaymentModule' => 'tm/firecheckout/klarna_klarnapaymentmodule.xml',
            'Mage_Captcha'               => 'tm/firecheckout/mage_captcha.xml',
            'Magestore_Storepickup'      => 'tm/firecheckout/magestore_storepickup.xml',
            'MageWorx_MultiFees'         => 'tm/firecheckout/mageworx_multifees.xml',
            'Netresearch_Billsafe'       => 'tm/firecheckout/netresearch_billsafe.xml',
            'Netresearch_OPS'            => 'tm/firecheckout/netresearch_ops.xml',
            'Payone_Core'                => 'tm/firecheckout/payone_core.xml',
            'Phoenix_Ipayment'           => 'tm/firecheckout/phoenix_ipayment.xml',
            'Phoenix_WirecardCheckoutPage' => 'tm/firecheckout/phoenix_wirecardcheckoutpage.xml',
            'PostcodeNl_Api'             => 'tm/firecheckout/postcodenl_api.xml',
            'Rack_Getpostcode'           => 'tm/firecheckout/rack_getpostcode.xml',
            'Rewardpoints'               => 'tm/firecheckout/rewardpoints.xml',
            'Symmetrics_Buyerprotect'    => 'tm/firecheckout/symmetrics_buyerprotect.xml',
            'Tig_MyParcel'               => 'tm/firecheckout/tig_myparcel.xml',
            'TIG_Postcode'               => 'tm/firecheckout/tig_postcode.xml',
            'TIG_PostNL'                 => 'tm/firecheckout/tig_postnl.xml',
            'Unirgy_Giftcert'            => 'tm/firecheckout/unirgy_giftcert.xml',
            'Vaimo_Klarna'               => 'tm/firecheckout/vaimo_klarna.xml',
            'Webgriffe_TaxIdPro'         => 'tm/firecheckout/webgriffe_taxidpro.xml',
            'Webshopapps_Desttype'       => 'tm/firecheckout/webshopapps_desttype.xml',
            'Webshopapps_Wsafreightcommon' => 'tm/firecheckout/webshopapps_wsafreightcommon.xml',
            'Webtex_Giftcards'           => 'tm/firecheckout/webtex_gitcards.xml'
        );
        $disabled = array(
            'Mage_Captcha' => array(
                // 'Infolution_ILStrongCaptcha',
                array(Mage::helper('firecheckout'), 'canUseInfolutionILStrongCaptchaModule')
            )
        );
        foreach ($mapping as $module => $layoutXml) {
            if (!$helper->isModuleOutputEnabled($module)) {
                continue;
            }
            if (isset($disabled[$module])) {
                foreach ($disabled[$module] as $_module) {
                    if (is_array($_module)) {
                        if (call_user_func($_module)) {
                            continue 2;
                        }
                    } elseif ($helper->isModuleOutputEnabled($_module)) {
                        continue 2;
                    }
                }
            }
            $tag = strtolower("firecheckout_{$module}");
            $xml = "<{$tag}><file>{$layoutXml}</file></{$tag}>";
            $node = new Varien_Simplexml_Element($xml);
            $updates->appendChild($node);
        }
    }
}
