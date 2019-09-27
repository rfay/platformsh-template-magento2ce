<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Quote\Customer;

use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\OfflinePayments\Model\Banktransfer;
use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\OfflinePayments\Model\Checkmo;
use Magento\OfflinePayments\Model\Purchaseorder;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test for setting offline payment methods on cart
 */
class SetOfflinePaymentMethodsOnCartTest extends GraphQlAbstract
{
    /**
     * @var GetMaskedQuoteIdByReservedOrderId
     */
    private $getMaskedQuoteIdByReservedOrderId;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/customer/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/enable_offline_payment_methods.php
     *
     * @param string $methodCode
     * @param string $methodTitle
     * @dataProvider offlinePaymentMethodDataProvider
     */
    public function testSetOfflinePaymentMethod(string $methodCode, string $methodTitle)
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');

        $query = $this->getQuery($maskedQuoteId, $methodCode);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        self::assertArrayHasKey('setPaymentMethodOnCart', $response);
        self::assertArrayHasKey('cart', $response['setPaymentMethodOnCart']);
        self::assertArrayHasKey('selected_payment_method', $response['setPaymentMethodOnCart']['cart']);

        $selectedPaymentMethod = $response['setPaymentMethodOnCart']['cart']['selected_payment_method'];
        self::assertArrayHasKey('code', $selectedPaymentMethod);
        self::assertEquals($methodCode, $selectedPaymentMethod['code']);

        self::assertArrayHasKey('title', $selectedPaymentMethod);
        self::assertEquals($methodTitle, $selectedPaymentMethod['title']);
    }

    /**
     * @return array
     */
    public function offlinePaymentMethodDataProvider(): array
    {
        return [
            'check_mo' => [Checkmo::PAYMENT_METHOD_CHECKMO_CODE, 'Check / Money order'],
            'bank_transfer' => [Banktransfer::PAYMENT_METHOD_BANKTRANSFER_CODE, 'Bank Transfer Payment'],
            'cash_on_delivery' => [Cashondelivery::PAYMENT_METHOD_CASHONDELIVERY_CODE, 'Cash On Delivery'],
        ];
    }

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/customer/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/enable_offline_payment_methods.php
     */
    public function testSetPurchaseOrderPaymentMethod()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $methodTitle = 'Purchase Order';
        $methodCode = Purchaseorder::PAYMENT_METHOD_PURCHASEORDER_CODE;
        $poNumber = 'abc123';

        $query = <<<QUERY
mutation {
  setPaymentMethodOnCart(input: {
    cart_id: "{$maskedQuoteId}", 
    payment_method: {
      code: "{$methodCode}"
      purchase_order_number: "{$poNumber}"
    }
  }) {
    cart {
      selected_payment_method {
        code
        title
        purchase_order_number
      }
    }
  }
}
QUERY;
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        self::assertArrayHasKey('setPaymentMethodOnCart', $response);
        self::assertArrayHasKey('cart', $response['setPaymentMethodOnCart']);
        self::assertArrayHasKey('selected_payment_method', $response['setPaymentMethodOnCart']['cart']);

        $selectedPaymentMethod = $response['setPaymentMethodOnCart']['cart']['selected_payment_method'];
        self::assertArrayHasKey('code', $selectedPaymentMethod);
        self::assertEquals($methodCode, $selectedPaymentMethod['code']);

        self::assertArrayHasKey('title', $selectedPaymentMethod);
        self::assertEquals($methodTitle, $selectedPaymentMethod['title']);

        self::assertArrayHasKey('purchase_order_number', $selectedPaymentMethod);
        self::assertEquals($poNumber, $selectedPaymentMethod['purchase_order_number']);
    }

    /**
     * @param string $maskedQuoteId
     * @param string $methodCode
     * @return string
     */
    private function getQuery(
        string $maskedQuoteId,
        string $methodCode
    ) : string {
        return <<<QUERY
mutation {
  setPaymentMethodOnCart(input: {
    cart_id: "{$maskedQuoteId}", 
    payment_method: {
      code: "{$methodCode}"
    }
  }) {
    cart {
      selected_payment_method {
        code
        title
      }
    }
  }
}
QUERY;
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     */
    private function getHeaderMap(string $username = 'customer@example.com', string $password = 'password'): array
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        $headerMap = ['Authorization' => 'Bearer ' . $customerToken];
        return $headerMap;
    }
}
