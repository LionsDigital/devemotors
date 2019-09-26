<?php

class Clearsale_Total_Model_BillingData extends Clearsale_Total_Model_Api_Request_Billing
{
    use Clearsale_Total_Model_Trait_Customer;

    public function __construct(Mage_Sales_Model_Order $order, Mage_Customer_Model_Customer $customer)
    {
        $this->buildCustomerData($order, $customer);
        $this->setClientID($customer->getId());
        $this->setAddress(Mage::getModel('clearsale_total/address', $order->getBillingAddress()));
        $this->addPhone(Mage::getModel('clearsale_total/phone', $order->getBillingAddress()));
    }
}