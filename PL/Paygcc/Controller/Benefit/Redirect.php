<?php
namespace PL\Paygcc\Controller\Benefit;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use PL\Paygcc\Controller\Benefit;

class Redirect extends Benefit implements HttpGetActionInterface
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
            $this->_benefitCheckout->setCustomerWithAddressChange(
                $customerData,
                $quote->getBillingAddress(),
                $quote->getShippingAddress()
            );
        }

        $paymentUrl =  $this->_benefitCheckout->getPaymentRequest();
        $this->getResponse()->setRedirect($paymentUrl);

    }

}
