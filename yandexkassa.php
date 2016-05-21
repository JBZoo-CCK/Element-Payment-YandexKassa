<?php
/**
 * JBZoo Element YandexKassa
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package     JBZoo
 * @license     MIT
 * @copyright   Copyright (C) JBZoo.com, All rights reserved.
 * @link        https://github.com/JBZoo/Element-Payment-YandexKassa
 * @author      Denis Smetannikov <denis@jbzoo.com>
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Class JBCartElementPaymentYandexKassa
 */
class JBCartElementPaymentYandexKassa extends JBCartElementPayment
{
    protected $_apiUrl = 'https://money.yandex.ru/eshop.xml';

    protected $_apiTest = 'https://demomoney.yandex.ru/eshop.xml';

    /**
     * Redirect to payment action
     * @return null|string
     */
    public function getRedirectUrl()
    {
        $fields = array(
            'shopId'         => trim($this->config->get('shopId')),
            'scid'           => trim($this->config->get('scid')),
            'customerNumber' => 'userid_' . (int)JFactory::getUser()->id,
            'orderNumber'    => $this->getOrderId(),
            'sum'            => round($this->_getOrderAmount()->val(), 0),
        );

        $url = $this->isDebug() ? $this->_apiTest : $this->_apiUrl;

        return $url . '?' . $this->_jbrouter->query($fields);
    }

    /**
     * Checking the MD5 sign
     * @param array $params
     * @return bool
     */
    public function isValid($params = array())
    {
        $myMD5 = strtoupper(md5(implode(';', array(
            $_REQUEST['action'],
            $_REQUEST['orderSumAmount'],
            $_REQUEST['orderSumCurrencyPaycash'],
            $_REQUEST['orderSumBankPaycash'],
            $_REQUEST['shopId'],
            $_REQUEST['invoiceId'],
            $_REQUEST['customerNumber'],
            $this->config->get('password')
        ))));

        if ($myMD5 !== strtoupper($_REQUEST['md5'])) {
            return false;
        }

        if ('checkorder' == strtolower($_REQUEST['action'])) {
            $this->renderResponse();
        }

        return true;
    }

    /**
     * Detect order id from merchant's robot request
     * @return int
     */
    public function getRequestOrderId()
    {
        return (int)$this->app->jbrequest->get('orderNumber');
    }

    /**
     * @return JBCartValue
     */
    public function getRequestOrderSum()
    {
        return $this->_order->val($this->app->jbrequest->get('orderSumAmount'), $this->getDefaultCurrency());
    }

    /**
     * Get order amount
     * @return JBCartValue
     */
    protected function _getOrderAmount()
    {
        $order       = $this->getOrder();
        $payCurrency = $this->getDefaultCurrency();

        return $this->_order->val($this->getOrderSumm(), $order->getCurrency())->convert($payCurrency);
    }

    /**
     * {@inheritdoc}
     */
    public function renderResponse()
    {
        $responseBody = $this->_renderResponse($_REQUEST['action'], $_REQUEST['invoiceId'], 0);
        header("Content-Type: application/xml");
        echo $responseBody;
        exit;
    }

    /**
     * Building XML response.
     * @param  string $functionName "checkOrder" or "paymentAviso" string
     * @param  string $invoiceId    transaction number
     * @param  string $result_code  result code
     * @param  string $message      error message. May be null.
     * @return string                prepared XML response
     */
    protected function _renderResponse($functionName, $invoiceId, $result_code, $message = null)
    {
        $performedDatetime = $this->_formatDate(new DateTime());

        $response = array(
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<' . $functionName . 'Response performedDatetime="' . $performedDatetime . '"',
            ' code="' . $result_code . '" ',
            ($message != null ? 'message="' . $message . '"' : ""),
            ' invoiceId="' . $invoiceId . '"',
            ' shopId="' . trim($this->config->get('shopId')) . '"',
            '/>'
        );

        return implode(PHP_EOL, $response);
    }

    protected function _formatDate(DateTime $date)
    {
        $performedDatetime = $date->format("Y-m-d") . "T" . $date->format("H:i:s") . ".000" . $date->format("P");
        return $performedDatetime;
    }
}
