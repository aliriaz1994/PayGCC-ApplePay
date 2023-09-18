<?php
namespace PL\Paygcc\Controller\ApiCheckout;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use PL\Paygcc\Controller\ApiCheckout;

class Redirect extends ApiCheckout implements HttpGetActionInterface
{

    public function execute()
    {
        $this->_initCheckout();
        $quote = $this->_quote;
        $customerData = $this->_customerSession->getCustomerDataObject();

        if ($quote->getIsMultiShipping()) {
            $quote->setIsMultiShipping(false);
            $quote->removeAllAddresses();
        }

        if ($customerData->getId()) {
            $this->_apiCheckout->setCustomerWithAddressChange(
                $customerData,
                $quote->getBillingAddress(),
                $quote->getShippingAddress()
            );
        }
        $useCard = $this->_request->getParam('usecard');

        if($useCard)
        {
            $cardvalue = $this->_request->getParam('cardvalue');
            $paymentUrl =  $this->_apiCheckout->getPaymentRequest($cardvalue);
        }
        else{
            $paymentUrl =  $this->_apiCheckout->getPaymentRequest();
        }
        $this->getResponse()->setRedirect($paymentUrl);
    }

}
