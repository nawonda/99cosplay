<?php

class TM_FireCheckout_Model_Observer_Captcha
{
    /**
     * Called before captcha check
     */
    public function setCheckoutMethod($observer)
    {
        $data  = $observer->getControllerAction()->getRequest()->getPost('billing', array());
        $checkout = Mage::getSingleton('firecheckout/type_standard');
        $quote = $checkout->getQuote();
        if (isset($data['register_account']) && $data['register_account']) {
            $quote->setCheckoutMethod(TM_FireCheckout_Model_Type_Standard::METHOD_REGISTER);
        } else if ($checkout->getCustomerSession()->isLoggedIn()) {
            $quote->setCheckoutMethod(TM_FireCheckout_Model_Type_Standard::METHOD_CUSTOMER);
        } else {
            $quote->setCheckoutMethod(TM_FireCheckout_Model_Type_Standard::METHOD_GUEST);
        }
        return $this;
    }

    public function getCaptchaConfig($formId)
    {
        $config = array();
        if (Mage::helper('firecheckout')->canUseMageCaptchaModule()) {
            $config['model']   = Mage::helper('captcha')->getCaptcha($formId);
            $config['message'] = Mage::helper('captcha')->__('Incorrect CAPTCHA.');
        } elseif (Mage::helper('firecheckout')->canUseInfolutionILStrongCaptchaModule()) {
            $config['model']   = Mage::helper('ilstrongcaptcha')->getCaptcha($formId);
            $config['message'] = Mage::helper('ilstrongcaptcha')->__('Confirm you are not a spammer.');
        }
        return $config;
    }

    /**
     * Check Captcha On Forgot Password Page
     *
     * @param Varien_Event_Observer $observer
     * @return Mage_Captcha_Model_Observer
     */
    public function checkForgotpassword($observer)
    {
        if (!Mage::helper('firecheckout')->canUseCaptchaModule()) {
            return $this;
        }

        $formId = 'user_forgotpassword';
        $config = $this->getCaptchaConfig($formId);
        $captchaModel = $config['model'];

        if ($captchaModel->isRequired()) {
            $controller = $observer->getControllerAction();
            if (!$captchaModel->isCorrect($this->_getCaptchaString($controller->getRequest(), $formId))) {
                $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
                $result = array(
                    'success' => false,
                    'error'   => $config['message'],
                    'captcha' => 'user_forgotpassword'
                );
                $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }
        }
        return $this;
    }

    /**
     * Check Captcha On User Login Page
     *
     * @param Varien_Event_Observer $observer
     * @return Mage_Captcha_Model_Observer
     */
    public function checkUserLogin($observer)
    {
        if (!Mage::helper('firecheckout')->canUseCaptchaModule()) {
            return $this;
        }

        $formId = 'user_login';
        $config = $this->getCaptchaConfig($formId);
        $captchaModel = $config['model'];

        $controller = $observer->getControllerAction();
        $loginParams = $controller->getRequest()->getPost('login');
        $login = array_key_exists('username', $loginParams) ? $loginParams['username'] : null;
        if ($captchaModel->isRequired($login)) {
            $word = $this->_getCaptchaString($controller->getRequest(), $formId);
            if (!$captchaModel->isCorrect($word)) {
                $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
                Mage::getSingleton('customer/session')->setUsername($login);
                $result = array(
                    'success' => false,
                    'error'   => $config['message'],
                    'captcha' => 'user_login'
                );
                $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }
        }
        $captchaModel->logAttempt($login);
        return $this;
    }

    /**
     * Check Captcha On Checkout as Guest Page
     *
     * @param Varien_Event_Observer $observer
     * @return Mage_Captcha_Model_Observer
     */
    public function checkGuestCheckout($observer)
    {
        if (!Mage::helper('firecheckout')->canUseCaptchaModule()) {
            return $this;
        }

        $formId = 'guest_checkout';
        $config = $this->getCaptchaConfig($formId);
        $captchaModel = $config['model'];

        $checkoutMethod = Mage::getSingleton('checkout/type_onepage')->getQuote()->getCheckoutMethod();
        if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
            if ($captchaModel->isRequired()) {
                $controller = $observer->getControllerAction();
                if (!$captchaModel->isCorrect($this->_getCaptchaString($controller->getRequest(), $formId))) {
                    $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
                    $result = array(
                        'error'   => 1,
                        'message' => $config['message'],
                        'captcha' => 'guest_checkout'
                    );
                    $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
            }
        }
        return $this;
    }

    /**
     * Check Captcha On Checkout Register Page
     *
     * @param Varien_Event_Observer $observer
     * @return Mage_Captcha_Model_Observer
     */
    public function checkRegisterCheckout($observer)
    {
        if (!Mage::helper('firecheckout')->canUseCaptchaModule()) {
            return $this;
        }

        $formId = 'register_during_checkout';
        $config = $this->getCaptchaConfig($formId);
        $captchaModel = $config['model'];

        $checkoutMethod = Mage::getSingleton('checkout/type_onepage')->getQuote()->getCheckoutMethod();
        if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            if ($captchaModel->isRequired()) {
                $controller = $observer->getControllerAction();
                if (!$captchaModel->isCorrect($this->_getCaptchaString($controller->getRequest(), $formId))) {
                    $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
                    $result = array(
                        'error'   => 1,
                        'message' => $config['message'],
                        'captcha' => 'register_during_checkout'
                    );
                    $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
            }
        }
        return $this;
    }

    /**
     * Get Captcha String
     *
     * @param Varien_Object $request
     * @param string $formId
     * @return string
     */
    protected function _getCaptchaString($request, $formId)
    {
        if (Mage::helper('firecheckout')->canUseMageCaptchaModule()) {
            $captchaParams = $request->getPost(Mage_Captcha_Helper_Data::INPUT_NAME_FIELD_VALUE);
        } elseif (Mage::helper('firecheckout')->canUseInfolutionILStrongCaptchaModule()) {
            $honeypot = $request->getPost(Infolution_ILStrongCaptcha_Helper_Data::HONEYPOT_NAME_FIELD_VALUE);
            if (!isset($honeypot) || trim($honeypot) !== '') {
                return null;
            }
            $captchaParams = $request->getPost(Infolution_ILStrongCaptcha_Helper_Data::CHECKBOX_NAME_FIELD_VALUE);
        }
        return $captchaParams[$formId];
    }
}
