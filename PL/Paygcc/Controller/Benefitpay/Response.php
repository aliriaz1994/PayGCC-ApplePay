<?php
namespace PL\Paygcc\Controller\Benefitpay;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use PL\Paygcc\Controller\Benefitpay;

class Response extends Benefitpay
    implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{

    public function execute()
    {
        $incrementId = $this->_quote->getReservedOrderId();
        if (!isset($incrementId)) {
            $this->messageManager->addError("Invalid Data");
            $this->_redirect('checkout/cart');
            return;
        }

       $payGCCOrderId = $this->_benefitpayModel->getPayGCCOrderId($this->_quote->getReservedOrderId());
       $response = $this->_benefitpayModel->getPayGCCOrderDetails($payGCCOrderId);
       $paymentInfo = $this->_paygccHelper->getBenfitPayTransactionDetails($response);
        if($this->_benefitpayModel->getConfigData('debug')) {
            $this->_plLogger->debug("benefitpay response>>>>>>>".print_r($response,1));
            $this->_plLogger->debug("benefitpay payment Info>>>>>>>".print_r($paymentInfo,1));
        }
        if(isset($paymentInfo['status']) && $paymentInfo['status'] == 'SUCCESS') {
            $this->_benefitpayCheckout->place($paymentInfo);
            $this->_getCheckoutSession()->clearHelperData();

            $quoteId = $this->_getQuote()->getId();
            $this->_getCheckoutSession()
                ->setLastQuoteId($quoteId)
                ->setLastSuccessQuoteId($quoteId);

            $order = $this->_benefitpayCheckout->getOrder();
            if ($order) {
                $this->_getCheckoutSession()
                    ->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
                $order->getPayment()->setTransactionId($paymentInfo['tnx_id']);
                $order->getPayment()->setLastTransId($paymentInfo['tnx_id']);
                $order->getPayment()->setParentTransactionId($paymentInfo['tnx_id']);
                $order->getPayment()->setIsTransactionClosed(0);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $note = sprintf('%s. PayGCC Order ID: %s', $paymentInfo['status'],$paymentInfo['order_id']);
                $order->addStatusHistoryComment($note);
                $order->setCustomerNoteNotify(false);
                $order->setTotalpaid($order->getGrandTotal());
                if (!$order->hasInvoices() && $order->canInvoice()) {
                    $invoice = $order->prepareInvoice();
                    if ($invoice->getTotalQty() > 0) {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                        $invoice->setTransactionId($order->getPayment()->getTransactionId());
                        $invoice->register();
                        $invoice->save();
                    }
                }
                $order->save();
                $this->messageManager->addSuccess(__('Transaction was successful'));
                $this->_redirect('checkout/onepage/success');
                return;
            }
        } else {
           //$this->_redirect('checkout', ['_fragment' => 'payment']);
            $this->_redirect('checkout', ['_fragment' => 'shipping']);
        }

    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }


    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}

