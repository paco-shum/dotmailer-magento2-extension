<?php

namespace Dotdigitalgroup\Email\Observer\Customer;

class NewAutomation implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Dotdigitalgroup\Email\Helper\Automation
     */
    protected $_helper;

    /**
     * NewAutomation constructor.
     *
     * @param \Dotdigitalgroup\Email\Helper\Automation $automation
     */
    public function __construct(
        \Dotdigitalgroup\Email\Helper\Automation $automation
    ) {
        $this->_helper = $automation;
    }

    /**
     * If it's configured to capture on shipment - do this.
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        //New Automation enrolment to queue
        $this->_helper->newCustomerAutomation($customer);
        return $this;
    }
}
