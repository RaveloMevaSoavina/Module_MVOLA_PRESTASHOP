<?php

class mvolaValidationModuleFrontController extends ModuleFrontController {
    

    /**
     * Retours de l'api de paiement
     */
    public function postProcess()
    {
        //Vérification générales 
        $cart = $this->context->cart;
        $authorized = false;

        /*
         * Verify if this module is enabled and if the cart has
         * a valid customer, delivery address and invoice address
         */
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }
         

        /** 
         * Verify if this payment module is authorized
         */
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'mvola') {
                $authorized = true;
                break;
            }
        }
        
        /** @var CustomerCore $customer */
        $customer = new Customer($cart->id_customer);

        /**
         * Check if this is a vlaid customer account
         */
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        /**
         * check pay token
         */
        if(!$this->context->cookie->__isset('ff_pay_token')){
            Tools::redirect('index.php?controller=order&step=1');
        }

        $MPGw_Token = $this->context->cookie->__get('ff_pay_token');

        $APIVersion = Configuration::get('MVOLA_API_VERSION');

        $MPGW_WS_URL = Configuration::get('MVOLA_BASE_URL'). "/ws/MPGwApi";; 

        $ws = new SoapClient($MPGW_WS_URL);

        $parameters = new \stdClass();

        $parameters->Login_WS = Configuration::get('MERCHANT_LOGIN');
        $parameters->Password_WS = Configuration::get('MERCHANT_PASSWORD');
        $parameters->HashCode_WS = Configuration::get('MERCHANT_HASH');
        $parameters->MPGw_TokenID = $MPGw_Token;

        $retour = $ws->WS_MPGw_CheckTransactionStatus($APIVersion, $parameters);

        $response_desc = $retour->ResponseCodeDescription;

        if($response_desc != 'OK'){
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $this->context->cookie->__unset('ff_pay_token'); //remove pay_token for security end

        /**
         * Place the order
         */
        $this->module->validateOrder(
            (int) $this->context->cart->id,
            Configuration::get('PS_OS_PAYMENT'),
            (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName.", Ref: ".$retour->MvolaTransactionID,
            "MvolaTransactionID : ".$retour->MvolaTransactionID,
            null,
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );

        /**
         * Redirect the customer to the order confirmation page
         */
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }    
}
