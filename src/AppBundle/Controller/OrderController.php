<?php

namespace AppBundle\Controller;

use AppBundle\Domain\Order\Command\Checkout as CheckoutCommand;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\DeliveryAddressType;
use AppBundle\Form\StripePaymentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/order")
 */
class OrderController extends Controller
{
    /**
     * @Route("/", name="order")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $order = $this->get('sylius.context.cart')->getCart();
        $user = $this->getUser();

        // At this step, we are pretty sure the customer is logged in
        // Make sure the order actually has a customer, if not set previously
        // @see AppBundle\EventListener\WebAuthenticationListener
        if ($user !== $order->getCustomer()) {
            $order->setCustomer($user);
            $this->get('sylius.manager.order')->flush();
        }

        // TODO Check if cart is empty
        $deliveryAddress = $order->getShippingAddress();

        $form = $this->createForm(DeliveryAddressType::class, $deliveryAddress);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $deliveryAddress = $form->getData();
            $deliveryAddress->setFirstName($user->getGivenName());
            $deliveryAddress->setLastName($user->getFamilyName());
            $deliveryAddress->setTelephone($user->getTelephone());
            $this->getDoctrine()->getManagerForClass(Address::class)->persist($deliveryAddress);
            $this->getDoctrine()->getManagerForClass(Address::class)->flush();

            return $this->redirectToRoute('order_payment');
        }

        return array(
            'order' => $order,
            'form' => $form->createView(),
            'restaurant' => $order->getRestaurant(),
            'deliveryAddress' => $deliveryAddress,
        );
    }

    /**
     * @Route("/payment", name="order_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        $order = $this->get('sylius.context.cart')->getCart();
        $orderManager = $this->get('coopcycle.order_manager');

        $form = $this->createForm(StripePaymentType::class);

        $parameters =  [
            'order' => $order,
            'deliveryAddress' => $order->getShippingAddress(),
            'restaurant' => $order->getRestaurant(),
            'form' => $form->createView(),
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);

            $this->get('command_bus')->handle(
                new CheckoutCommand($order, $form->get('stripeToken')->getData())
            );

            $this->get('sylius.manager.order')->flush();

            if (PaymentInterface::STATE_FAILED === $stripePayment->getState()) {
                return array_merge($parameters, [
                    'error' => $stripePayment->getLastError()
                ]);
            }

            return $this->redirectToRoute('profile_order', array('id' => $order->getId()));
        }

        return $parameters;
    }
}
