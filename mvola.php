<?php


use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
 
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mvola  extends PaymentModule
{
    protected $_html;

    public function __construct()
    {
        $this->name = 'mvola';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'ravelomevasoavina';
       // $this->controllers = array('MvolaValidationModuleFrontController');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MVola');
        $this->description = $this->l('Accepter Paiement par MVola');
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() 
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            ) {
            return false;
        }
        return true;
    }

    public function hookPaymentOptions($params){
        if (!$this->active) {
            return;
        }

        //DELETE TOKEN IF EXISTS
        if(!$this->context->cookie->__isset('ff_pay_token')){
            $this->context->cookie->__unset('ff_pay_token'); //remove pay_token for security end
        }

        // GET TOKEN FROM TELMA
        $MPGW_BASEURL = Configuration::get('MVOLA_BASE_URL'); // Sera fourni par TELMA
        $MPGW_WS_URL = $MPGW_BASEURL . "/ws/MPGwApi"; // Sera fourni par TELMA
        $MPGW_TRANSACTION_URL = $MPGW_BASEURL . "/transaction/"; // Sera fourni par TELMA
        $APIVersion = Configuration::get('MVOLA_API_VERSION'); 

        $parameters = new \stdClass();
        
        $parameters->Login_WS = Configuration::get('MERCHANT_LOGIN'); 
        $parameters->Password_WS = Configuration::get('MERCHANT_PASSWORD');
        $parameters->HashCode_WS = Configuration::get('MERCHANT_HASH');

        $parameters->ShopTransactionAmount =  $this->context->cart->getOrderTotal(true, Cart::BOTH);
        $parameters->ShopTransactionID = $this->context->cart->id."0".time();

        $parameters->ShopTransactionLabel = "PAIEMENT CMD N ".$this->context->cart->id;
        $parameters->ShopShippingName = "";
        $parameters->ShopShippingAddress = "";
        $parameters->UserField1 = "";
        $parameters->UserField2 = "";
        $parameters->UserField3 = "";

        $this->context->parameters =   $parameters;

        // Initialisation du web service
        $ws = new \SoapClient($MPGW_WS_URL);
        $initRes = $ws->WS_MPGw_PaymentRequest($APIVersion, $parameters);

        // Test des resultats
        if(($initRes->APIVersion != $APIVersion) || ($initRes->ResponseCode != 0))
        {
            return;
        }

        // Appel OK, redirection sur la page de paiement de laplateforme 
        $pay_token = $initRes->MPGw_TokenID;
        
        //ADD PAY_TOKEN IN COOKIES
        $this->context->cookie->__set('ff_pay_token', $pay_token);
        $this->context->cookie->write();

        $payment_url = $MPGW_TRANSACTION_URL . $pay_token;

        $this->smarty->assign(['payment_url' => $payment_url]);

        $apiPayement = new PaymentOption();
        $apiPayement->setModuleName($this->name)
                ->setCallToActionText($this->l('Paiement par MVola'))
                //Définition d'un formulaire personnalisé
                ->setForm($this->fetch('module:mvola/views/templates/hook/payment_api_form.tpl'))
                ->setAdditionalInformation($this->fetch('module:mvola/views/templates/hook/displayPaymentApi.tpl'));
                
        return [$apiPayement];

    }

    /**
     * Affichage du message de confirmation de la commande
     * @param type $params
     * @return type
     */
    public function hookDisplayPaymentReturn($params) 
    {
        if (!$this->active) {
            return;
        }
        
        $this->smarty->assign(
            $this->getTemplateVars()
            );
        return $this->fetch('module:mvola/views/templates/hook/payment_return.tpl');
    }

    /**
     * Configuration admin du module
     */
    public function getContent()
    {
        $this->_html .=$this->postProcess();
        $this->_html .= $this->renderForm();

        return $this->_html;

    }

    /**
     * Traitement de la configuration BO
     * @return type
     */
    public function postProcess()
    {
        if ( Tools::isSubmit('SubmitPaymentConfiguration'))
        {
            Configuration::updateValue('MVOLA_BASE_URL', Tools::getValue('MVOLA_BASE_URL'));
            Configuration::updateValue('MVOLA_API_VERSION', Tools::getValue('MVOLA_API_VERSION'));
            Configuration::updateValue('MERCHANT_LOGIN', Tools::getValue('MERCHANT_LOGIN'));
            Configuration::updateValue('MERCHANT_PASSWORD', Tools::getValue('MERCHANT_PASSWORD'));
            Configuration::updateValue('MERCHANT_HASH', Tools::getValue('MERCHANT_HASH'));
        }
        return $this->displayConfirmation($this->l('Configuration mise à jour!'));
    }

    /**
    * Formulaire de configuration admin
    */
   public function renderForm()
   {
       $fields_form = [
           'form' => [
               'legend' => [
                   'title' => $this->l('Configuration paiement MVola'),
                   'icon' => 'icon-cogs'
               ],
               'description' => $this->l('Ici, vous configurez le paiement par MVola'),
               'input' => [
                  [
                       'type' => 'text',
                       'label' => $this->l('Base URL'),
                       'name' => 'MVOLA_BASE_URL',
                       'required' => true,
                       'value' => "https://www.telma.net/mpgw/v2",
                       'empty_message' => $this->l('Completez la Base URL')
                  ],
                  [
                       'type' => 'text',
                       'label' => $this->l("Version de l'API"),
                       'name' => 'MVOLA_API_VERSION',
                       'required' => true,
                       'value' => "2.0.0",
                       'empty_message' => $this->l('Completez la version')
                   ],
                   [
                       'type' => 'text',
                       'label' => $this->l('identifiant du Marchand'),
                       'name' => 'MERCHANT_LOGIN',
                       'required' => true,
                       'empty_message' => $this->l("Completez l'identifiant du Marchand")
                   ],
                   [
                       'type' => 'text',
                       'label' => $this->l('mot de passe du Marchand'),
                       'name' => 'MERCHANT_PASSWORD',
                       'required' => true,
                       'empty_message' => $this->l("Completez le mot de passe du Marchand")
                   ],
                   [
                       'type' => 'text',
                       'label' => $this->l('HAsh du Marchand'),
                       'name' => 'MERCHANT_HASH',
                       'required' => true,
                       'empty_message' => $this->l("Completez le mot de passe du Marchand")
                   ]
               ],
               'submit' => [
                   'title' => $this->l('Enregistrer'),
                   'class' => 'button btn btn-default pull-right'
               ]
           ]
           ];

       $helper = new HelperForm();
       $helper->show_toolbar = false;
       $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
       $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
       $helper->id = 'mvola';
       $helper->identifier = 'mvola';
       $helper->submit_action = 'SubmitPaymentConfiguration';
       $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
       $helper->token = Tools::getAdminTokenLite('AdminModules');
       $helper->tpl_vars = [
           'fields_value' => $this->getConfigFieldsValues(),
           'languages' => $this->context->controller->getLanguages(),
           'id_language' => $this->context->language->id
       ];

       return $helper->generateForm(array($fields_form));
   }

    /**
     * Récupération des variables de configuration du formulaire admin
     */
    public function getConfigFieldsValues()
    {
        return [
            'MVOLA_BASE_URL' => Tools::getValue('MVOLA_BASE_URL', Configuration::get('MVOLA_BASE_URL')),
            'MVOLA_API_VERSION' => Tools::getValue('MVOLA_API_VERSION', Configuration::get('MVOLA_API_VERSION')),
            'MERCHANT_LOGIN' => Tools::getValue('MERCHANT_LOGIN', Configuration::get('MERCHANT_LOGIN')),
            'MERCHANT_PASSWORD' => Tools::getValue('MERCHANT_PASSWORD', Configuration::get('MERCHANT_PASSWORD')),
            'MERCHANT_HASH' => Tools::getValue('MERCHANT_HASH', Configuration::get('MERCHANT_HASH'))
        ];
    }

    /**
     * Récupération des informations du template
     * @return array
     */
    public function getTemplateVars()
    {
        return [
            'shop_name' => $this->context->shop->name,
            'custom_var' => $this->l('My custom var value'),
            'payment_details' => $this->l('custom details'),
        ];
    }

}