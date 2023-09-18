<?php
namespace PL\Paygcc\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    protected $jsonHelper;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->jsonHelper = $jsonHelper;
    }

    public function getTransactionDetails($response = [])
    {
        $paymentInfo = [];
        $lastRequest = count($response['paymnet_requests']) - 1;
        if (isset($response['paymnet_requests'][$lastRequest]['status'])) {
            $paymentInfo['status'] = $response['paymnet_requests'][$lastRequest]['status'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['order_id'])) {
            $paymentInfo['order_id'] = $response['paymnet_requests'][$lastRequest]['order_id'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['tnx_id'])) {
            $paymentInfo['tnx_id'] = $response['paymnet_requests'][$lastRequest]['payment_response']['tnx_id'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['receipt'])) {
            $paymentInfo['receipt'] = $response['paymnet_requests'][$lastRequest]['payment_response']['receipt'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['authorizationCode'])) {
            $paymentInfo['authorizationCode'] = $response['paymnet_requests'][$lastRequest]['payment_response']['authorizationCode'];
        }

        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['number'])) {
            $paymentInfo['number'] = $response['paymnet_requests'][$lastRequest]['payment_response']['number'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['Paid_by'])) {
            $paymentInfo['Paid_by'] = $response['paymnet_requests'][$lastRequest]['payment_response']['Paid_by'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['created_at'])) {
            $paymentInfo['created_at'] = $response['paymnet_requests'][$lastRequest]['payment_response']['created_at'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['meta'])) {
            $meta = $response['paymnet_requests'][$lastRequest]['payment_response']['meta'];
            $metaData = $this->jsonHelper->jsonDecode($meta);
            if (isset($metaData['order']['status'])) {
                $paymentInfo['order_status'] = $metaData['order']['status'];
            }
            if (isset($metaData['result'])) {
                $paymentInfo['result'] = $metaData['result'];
            }
            if(isset($metaData['sourceOfFunds']['provided']['card']['brand'])) {
                $paymentInfo['brand'] = $metaData['sourceOfFunds']['provided']['card']['brand'];
            }

        }
        return $paymentInfo;

    }

    public function getBenfitPayTransactionDetails($response = [])
    {
        $paymentInfo = [];
        $lastRequest = count($response['paymnet_requests']) - 1;
        if (isset($response['paymnet_requests'][$lastRequest]['status'])) {
            $paymentInfo['status'] = $response['paymnet_requests'][$lastRequest]['status'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['order_id'])) {
            $paymentInfo['order_id'] = $response['paymnet_requests'][$lastRequest]['order_id'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['tnx_id'])) {
            $paymentInfo['tnx_id'] = $response['paymnet_requests'][$lastRequest]['payment_response']['tnx_id'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['authorizationCode'])) {
            $paymentInfo['authorizationCode'] = $response['paymnet_requests'][$lastRequest]['payment_response']['authorizationCode'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['invoice_id'])) {
            $paymentInfo['invoice_id'] = $response['paymnet_requests'][$lastRequest]['payment_response']['invoice_id'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['Paid_by'])) {
            $paymentInfo['Paid_by'] = $response['paymnet_requests'][$lastRequest]['payment_response']['Paid_by'];
        }
        if (isset($response['paymnet_requests'][$lastRequest]['payment_response']['created_at'])) {
            $paymentInfo['created_at'] = $response['paymnet_requests'][$lastRequest]['payment_response']['created_at'];
        }

        return $paymentInfo;
    }

    public function getUniqueIncrementId($incrementId = null)
    {
        if (!empty($incrementId)) {
            $incrementId = $incrementId.rand(1000000,9999999);
            return $incrementId;
        }
    }

    public function revertIncrementId($qsIdentifier)
    {
        $length = strlen($qsIdentifier)-7;
        return substr($qsIdentifier, 0, $length);
    }
}
