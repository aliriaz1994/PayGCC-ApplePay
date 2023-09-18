<?php
namespace PL\Paygcc\Model;

class Benefitpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_CODE = 'paygcc_benefitpay';

    protected $_code = self::METHOD_CODE;

    protected $_infoBlockType = 'PL\Paygcc\Block\Benefitpay\Info';

    protected $_formBlockType = 'PL\Paygcc\Block\Benefitpay\Form';

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
     * @var \PL\Paygcc\Helper\Data
     */
    protected $paygccHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    const BENEFIT_CHECKOUT_URL = 'https://payments.paygcc.com/api/v7/benefit_pay';

    const ORDER_PAYMENT_DETAILS_URL = 'https://payments.paygcc.com/api/v7/orderPaymentDetails';


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

    public function initialize($paymentAction, $stateObject)
    {
        if ($paymentAction == 'order') {
            $order = $this->getInfoInstance()->getOrder();
            $order->setCustomerNoteNotify(false);
            $order->setCanSendNewEmailFlag(false);
            $stateObject->setIsNotified(false);
            $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        }
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
        return $this->urlBuilder->getUrl('paygcc/benefitpay/response', ['_secure' => $this->getRequest()->isSecure()]);
    }

    public function getFailedRedirectUrl()
    {
        return $this->urlBuilder->getUrl('paygcc/benefitpay/failed', ['_secure' => $this->getRequest()->isSecure()]);
    }


    function getBaseGrandTotal(\Magento\Quote\Model\Quote $quote)
    {
        $amount = sprintf("%.2F",$quote->getBaseGrandTotal());
        if ($quote->getBaseCurrencyCode() == 'BHD') {
            $amount = sprintf("%.3F",$quote->getBaseGrandTotal());
        }

        return $amount;
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
        if ($this->getConfigData('debug')) {
            $this->plLogger->debug("BENEFITPAY>>>>>>>>>>>>>>>>".$response);
        }
        return $response;
    }

    public function getPayGCCPaymentUrl(\Magento\Quote\Model\Quote $quote)
    {
        $billing = $quote->getBillingAddress();
        $request['customer_id'] =  $quote->getReservedOrderId();
        $request['order_id'] = $this->getPayGCCOrderId($quote->getReservedOrderId());
        $request['name'] = $billing->getName();
        $request['grand_total'] = $this->getBaseGrandTotal($quote);
        $request['success_url'] = $this->getResponseUrl();
        $request['failed_url'] = $this->getResponseUrl();
        $request['email'] = $billing->getEmail();
        $request['phone'] = $billing->getTelephone();
        $response = $this->doCURL(self::BENEFIT_CHECKOUT_URL,$request);
        if ($this->getConfigData('debug')) {
            $this->plLogger->debug("PAYMENT RESPONSE: ".$response);
        }
        $responseData = $this->jsonHelper->jsonDecode($response);
        $paymentUrl = null;
        if (isset($responseData['status'])) {
            if ($responseData['status'] == 1) {
                $paymentUrl = $responseData['PaymentURL'];
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
            $note = sprintf('%s. PayGCC Order ID: %s', $response['status'],$response['order_id']);
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
                $note = sprintf('%s. PayGCC Order ID: %s', $response['status'],$response['order_id']);
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
