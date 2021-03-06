<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\Sales\Model\Order;

/**
 * Class PaymentTest
 * @package Magento\Sales\Model\Order
 */
class PaymentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Payment
     */
    protected $payment;

    /**
     * @var \Magento\Payment\Helper\Data | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Event\Manager | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventManager;

    protected function setUp()
    {
        $objectManger = new \Magento\TestFramework\Helper\ObjectManager($this);

        $this->eventManager = $this->getMock('Magento\Framework\Event\Manager', [], [], '', false);

        $context = $this->getMock('Magento\Framework\Model\Context', [], [], '', false);
        $context->expects($this->once())
            ->method('getEventDispatcher')
            ->will($this->returnValue($this->eventManager));

        $this->helper = $this->getMock('Magento\Payment\Helper\Data', ['getMethodInstance'], [], '', false);

        $this->payment = $objectManger->getObject(
            'Magento\Sales\Model\Order\Payment',
            [
                'paymentData' => $this->helper,
                'context' => $context
            ]
        );
    }

    protected function tearDown()
    {
        $this->payment = null;
    }

    public function testCancel()
    {
        $paymentMethod = $this->getMock('Magento\Payment\Model\Method\AbstractMethod', ['canVoid'], [], '', false);
        $this->helper->expects($this->once())->method('getMethodInstance')->will($this->returnValue($paymentMethod));
        $this->payment->setMethod('any');
        // check fix for partial refunds in Payflow Pro
        $paymentMethod->expects(
            $this->once()
        )->method(
            'canVoid'
        )->with(
            new \PHPUnit_Framework_Constraint_IsIdentical($this->payment)
        )->will(
            $this->returnValue(false)
        );

        $this->assertEquals($this->payment, $this->payment->cancel());
    }

    public function testPlace()
    {
        $newOrderStatus = 'new_status';
        /** @var \Magento\Sales\Model\Order\Config | \PHPUnit_Framework_MockObject_MockObject $orderConfig */
        $orderConfig = $this->getMock('Magento\Sales\Model\Order\Config', [], [], '', false);
        $orderConfig->expects($this->at(0))
            ->method('getStateStatuses')
            ->with(\Magento\Sales\Model\Order::STATE_NEW)
            ->will($this->returnValue(['firstStatus', 'secondStatus']));
        $orderConfig->expects($this->at(1))
            ->method('getStateDefaultStatus')
            ->with(\Magento\Sales\Model\Order::STATE_NEW)
            ->will($this->returnValue($newOrderStatus));
        /** @var \Magento\Sales\Model\Order | \PHPUnit_Framework_MockObject_MockObject $order */
        $order = $this->getMock('Magento\Sales\Model\Order', [], [], '', false);
        $order->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($orderConfig));
        $order->expects($this->once())
            ->method('setState')
            ->with(\Magento\Sales\Model\Order::STATE_NEW, $newOrderStatus);
        $methodInstance = $this->getMock('Magento\Payment\Model\Method\AbstractMethod', [], [], '', false);

        $this->payment->setOrder($order);
        $this->payment->setMethodInstance($methodInstance);

        $this->eventManager->expects($this->at(0))
            ->method('dispatch')
            ->with('sales_order_payment_place_start', ['payment' => $this->payment]);
        $this->eventManager->expects($this->at(1))
            ->method('dispatch')
            ->with('sales_order_payment_place_end', ['payment' => $this->payment]);

        $this->assertEquals($this->payment, $this->payment->place());
    }
}
