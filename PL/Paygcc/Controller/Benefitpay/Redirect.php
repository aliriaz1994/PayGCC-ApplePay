<?php
namespace PL\Paygcc\Controller\Benefitpay;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use PL\Paygcc\Controller\Benefitpay;

class Redirect extends Benefitpay implements HttpGetActionInterface
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
            $this->_benefitpayCheckout->setCustomerWithAddressChange(
                $customerData,
                $quote->getBillingAddress(),
                $quote->getShippingAddress()
            );
        }

        $paymentUrl =  $this->_benefitpayCheckout->getPaymentRequest();
        $this->getResponse()->setRedirect($paymentUrl);
    }

}
