<?php
namespace PL\Paygcc\Model;

class ApiCheckout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_CODE = 'paygcc_apicheckout';

    protected $_code = self::METHOD_CODE;

    protected $_infoBlockType = 'PL\Paygcc\Block\ApiCheckout\Info';

    protected $_formBlockType = 'PL\Paygcc\Block\ApiCheckout\Form';

    /**
     * @var bool
     */
    protected $_canAuthorize = false;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    protected $_canRefund = false;

    protected $_canRefundInvoicePartial = true;


    /**
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = false;


    protected $_canOrder = true;


    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \PL\Paygcc\Logger\Logger
     */
    protected $plLogger;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \PG\Paygcc\Helper\Data
     */
    protected $paygccHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    const API_CHECKOUT_URL = 'https://payments.paygcc.com/api/v8/checkout';

    const ORDER_PAYMENT_DETAILS_URL = 'https://payments.paygcc.com/api/v8/orderPaymentDetails';


    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \PL\Paygcc\Helper\Data $paygccHelper,
        \PL\Paygcc\Logger\Logger $plLogger,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->urlBuilder = $urlBuilder;
        $this->paygccHelper = $paygccHelper;
        $this->plLogger = $plLogger;
        $this->request = $request;
        $this->jsonHelper = $jsonHelper;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->checkoutSession = $checkoutSession;
    }

    protected function getRequest()
    {
        return $this->request;
    }


    public function getCompanyCode()
    {
        $companyCode = trim($this->getConfigData('company_code'));
        return $companyCode;
    }

    public function getOrderPrefix()
    {
        $orderPrefix = trim($this->getConfigData('order_prefix'));
        return $orderPrefix;
    }

    public function getPaymentType()
    {
        return $this->getConfigData('payment_type');
    }

    public function getPayGCCOrderId($orderId)
    {
        return $this->getOrderPrefix().$orderId;
    }

    public function revertOrderId($payGCCOrderId = '')
    {
        $length = strlen($this->getOrderPrefix());
        $orderId = substr($payGCCOrderId,$length,0);
        return $orderId;
    }

    public function getResponseUrl()
    {
        return $this->urlBuilder->getUrl('paygcc/apicheckout/response', ['_secure' => $this->getRequest()->isSecure()]);
    }

    public function getFailedRedirectUrl()
    {
        return $this->urlBuilder->getUrl('paygcc/apicheckout/failed', ['_secure' => $this->getRequest()->isSecure()]);
    }

    public function getWebhookUrl()
    {
        return $this->urlBuilder->getUrl('paygcc/webhook/notify', ['_secure' => $this->getRequest()->isSecure()]);
    }


    public function getTotalAmount($amount)
    {
        $amount = sprintf("%.3F", $amount);
        return $amount;
    }

    public function getBaseGrandTotal(\Magento\Quote\Model\Quote $quote)
    {
        $amount = sprintf("%.2F",$quote->getBaseGrandTotal());
        if ($quote->getBaseCurrencyCode() == 'BHD') {
            $amount = sprintf("%.3F",$quote->getBaseGrandTotal());
        }


        return $amount;
    }

    public function getCustomerLocation(\Magento\Quote\Model\Quote $quote)
    {
        $billing = $quote->getBillingAddress();
        $location = null;
        $telephone = null;
        if ($billing->getTelephone()) {
            $telephone = __('Phone[%1] ',  $billing->getTelephone());
        }
        if ($street = $billing->getStreet()) {
            $location.= $street[0].", ";
        }
        if ($billing->getCity()) {
            $location.= $billing->getCity().", ";
        }
        if ($billing->getRegion()) {
            $location.= $billing->getRegion().", ";
        }
        if ($billing->getPostcode()) {
            $location.= $billing->getPostcode().", ";
        }
        if ($billing->getCountryId()) {
            $location.= $billing->getCountryId();
        }
        $location =  __("Address[%1]", $location);
        return $telephone.$location;

    }

    public function doCURL($apiURL, $request = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->getConfigData('ssl_enabled'));
        curl_setopt($ch, CURLOPT_POST, true);
        $header = [];
        $header[] = 'Content-Type: application/x-www-form-urlencoded';
        $header[] =  sprintf('company-code: %s', $this->getCompanyCode());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
        if (! ($response = curl_exec($ch))) {
            $cURLError = sprintf("cURL Error: error_no: %s, error: %s", curl_errno($ch), curl_error($ch));
            $this->plLogger->debug($cURLError);
            throw new \Magento\Framework\Exception\LocalizedException(__($cURLError));

        }
        curl_close($ch);
        return $response;
    }

    public function getPayGCCPaymentUrl(\Magento\Quote\Model\Quote $quote, $cardValue = null)
    {
        $billing = $quote->getBillingAddress();

        // $request['customer_id'] =  $quote->getReservedOrderId();
        $request['customer_id'] =  $billing->getEmail();
        $request['order_id'] = $this->getPayGCCOrderId($quote->getReservedOrderId());
        $request['name'] = $billing->getName();
        $request['grand_total'] = $this->getBaseGrandTotal($quote);
        $request['currency_code'] = $quote->getQuoteCurrencyCode();
        $request['payment_type'] =  $this->getPaymentType();
        if(isset($cardValue))
        {
            $request['used_token'] = 1; 
            $request['token'] = $cardValue;
        }
        else{
            $request['used_token'] = 0;
        }
        $request['save_token'] = 0;
        $request['success_url'] = $this->getResponseUrl();
        $request['failed_url'] = $this->getResponseUrl();
        $request['webhook_url'] = $this->getWebhookUrl();
        $request['email'] = $billing->getEmail();
        $request['location'] = $this->getCustomerLocation($quote);
        $response = $this->doCURL(self::API_CHECKOUT_URL,$request);
        if ($this->getConfigData('debug')) {
            $this->plLogger->debug("PAYMENT RESPONSE: ".$response);
        }
        $responseData = $this->jsonHelper->jsonDecode($response);
        $paymentUrl = null;
        if (isset($responseData['status'])) {
            if ($responseData['status'] == 1) {
                $paymentUrl = isset($responseData['PaymentURL'])?$responseData['PaymentURL']:$this->getResponseUrl();
                return $paymentUrl;
            } else {
                $messages = $responseData['messages'][0];
                die($messages);
                //throw new \Magento\Framework\Exception\LocalizedException(__($messages));
            }

        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__($responseData['title']));
        }

    }

    public function processingByPayGCC(\Magento\Sales\Model\Order $order)
    {
        if ($order->getId()) {
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->addStatusHistoryComment(__('Wait for PayGCC Processing'));
            $order->setCustomerNoteNotify(false);
            $order->save();
        }
    }

    public function getPayGCCOrderDetails($payGCCOrderId)
    {
        $request['order_id'] = $payGCCOrderId;
        $response = $this->doCURL(self::ORDER_PAYMENT_DETAILS_URL,$request);
        $responseData = $this->jsonHelper->jsonDecode($response);
        return $responseData;
    }

    public function acceptTransaction(\Magento\Sales\Model\Order $order, $response = [])
    {
        $this->checkOrderStatus($order);
        if ($order->getId()) {
            $additionalData = $this->jsonHelper->jsonEncode($response);
            $note = sprintf('%s. PayGCC Order ID: %s', $response['result'],$response['order_id']);
            $order->getPayment()->setTransactionId($response['tnx_id']);
            $order->getPayment()->setLastTransId($response['tnx_id']);
            $order->getPayment()->setAdditionalInformation('payment_additional_info', $additionalData);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->addStatusHistoryComment($note);
            $order->setTotalpaid($order->getBaseGrandTotal());
            $this->orderSender->send($order);
            if (!$order->hasInvoices() && $order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                if ($invoice->getTotalQty() > 0) {
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->setTransactionId($order->getPayment()->getTransactionId());
                    $invoice->register();
                    $invoice->addComment(__('Created invoice.'), true);
                    $invoice->save();
                    //$this->invoiceSender->send($invoice);
                }
            }
            $order->save();
        }
    }

    public function rejectTransaction(\Magento\Sales\Model\Order $order, $response = [])
    {
        $this->checkOrderStatus($order);
        if ($order->getId()) {
            $note = 'Order Canceled';
            if (count($response) > 0 && $response['tnx_id'] !="") {
                $additionalData = $this->jsonHelper->jsonEncode($response);
                $order->getPayment()->setTransactionId($response['tnx_id']);
                $order->getPayment()->setLastTransId($response['tnx_id']);
                $order->getPayment()->setAdditionalInformation('payment_additional_info', $additionalData);
                $note = sprintf('%s. PayGCC Order ID: %s', $response['result'],$response['order_id']);
            }
            if ($order->getState()!= \Magento\Sales\Model\Order::STATE_CANCELED) {
                $order->registerCancellation($note)->save();
            }
            //$this->checkoutSession->restoreQuote();
        }
    }


    public function checkOrderStatus(\Magento\Sales\Model\Order $order)
    {
        if ($order->getId()) {
            $state = $order->getState();
            switch ($state) {
                case \Magento\Sales\Model\Order::STATE_HOLDED:
                case \Magento\Sales\Model\Order::STATE_CANCELED:
                case \Magento\Sales\Model\Order::STATE_CLOSED:
                case \Magento\Sales\Model\Order::STATE_COMPLETE:
                    break;
            }
        }
    }
}
