<?php



class MvolaAnnulationModuleFrontController extends ModuleFrontController {
    

    /**
     * Retours de l'api de paiement
     */
    public function postProcess()
    {
        if(!$this->context->cookie->__isset('ff_pay_token')){
            $this->context->cookie->__unset('ff_pay_token'); //remove pay_token for security end
        }

        Tools::redirect('index.php?controller=order&step=1');
    }   

    
}
