<?php

namespace PL\Paygcc\Model\Card;

use Magento\Customer\Model\Session;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SaveCards implements ConfigProviderInterface
{
    /**
     * @var \PL\Paygcc\Logger\Logger
     */
    protected $plLogger;

    protected $customerSession;

    protected $scopeConfig;

    const API_GET_TOKEN = 'https://payments.paygcc.com/api/v8/customer/tokenizations';
    /**
     * SaveCards constructor.
     *
     */
    
    public function __construct(
        \PL\Paygcc\Logger\Logger $plLogger,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig
    )
    {        
        $this->plLogger = $plLogger;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $isLoggedIn = $this->customerSession->isLoggedIn();
        if ($isLoggedIn) {
            $customer = $this->customerSession->getCustomer();
            $request['customer_id'] = $customer->getEmail();
            $additionalVariables['save_cards'] = $this->doCURL(self::API_GET_TOKEN, $request);
        }
        else
        {
            $additionalVariables['save_cards'] = null;
        }
        
        return $additionalVariables;
    }

    public function getConfigValue($path)
    {
        $value = $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        );

        return $value;
    }

    public function doCURL($apiURL, $request = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->getConfigValue('payment/paygcc_apicheckout/ssl_enabled'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
        
        $response = json_decode($response,true);
        return $response;
    }

    public function getCompanyCode()
    {
        $companyCode = trim($this->getConfigValue('payment/paygcc_apicheckout/company_code'));
        return $companyCode;
    }
}