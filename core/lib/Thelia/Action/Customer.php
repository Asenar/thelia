<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\CustomerException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\CustomerQuery;
use Thelia\Tools\Password;

/**
 *
 * customer class where all actions are managed
 *
 * Class Customer
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Customer extends BaseAction implements EventSubscriberInterface
{
    /** @var SecurityContext */
    protected $securityContext;

    /** @var MailerFactory */
    protected $mailer;

    public function __construct(SecurityContext $securityContext, MailerFactory $mailer)
    {
        $this->securityContext = $securityContext;
        $this->mailer = $mailer;
    }

    public function create(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $customer = new CustomerModel();

        $plainPassword = $event->getPassword();

        $this->createOrUpdateCustomer($customer, $event, $dispatcher);

        if ($event->getNotifyCustomerOfAccountCreation()) {
            $this->mailer->sendEmailToCustomer('customer_account_created', $customer, [ 'password' => $plainPassword ]);
        }

        if (ConfigQuery::isCustomerEmailConfirmationEnable() && $customer->getConfirmationToken() !== null) {
            $this->mailer->sendEmailToCustomer('customer_confirmation', $customer, ['customer' => $customer]);
        }
    }

    public function modify(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $plainPassword = $event->getPassword();

        $customer = $event->getCustomer();

        $emailChanged = $customer->getEmail() !== $event->getEmail();

        $this->createOrUpdateCustomer($customer, $event, $dispatcher);

        if (! empty($plainPassword) || $emailChanged) {
            $this->mailer->sendEmailToCustomer('customer_account_changed', $customer, ['password' => $plainPassword]);
        }
    }

    public function updateProfile(CustomerCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $customer = $event->getCustomer();

        $customer->setDispatcher($dispatcher);

        if ($event->getTitle() !== null) {
            $customer->setTitleId($event->getTitle());
        }

        if ($event->getFirstname() !== null) {
            $customer->setFirstname($event->getFirstname());
        }

        if ($event->getLastname() !== null) {
            $customer->setLastname($event->getLastname());
        }

        if ($event->getEmail() !== null) {
            $customer->setEmail($event->getEmail(), $event->getEmailUpdateAllowed());
        }

        if ($event->getPassword() !== null) {
            $customer->setPassword($event->getPassword());
        }

        if ($event->getReseller() !== null) {
            $customer->setReseller($event->getReseller());
        }

        if ($event->getSponsor() !== null) {
            $customer->setSponsor($event->getSponsor());
        }

        if ($event->getDiscount() !== null) {
            $customer->setDiscount($event->getDiscount());
        }

        $customer->save();

        $event->setCustomer($customer);
    }

    public function delete(CustomerEvent $event)
    {
        if (null !== $customer = $event->getCustomer()) {
            if (true === $customer->hasOrder()) {
                throw new CustomerException(Translator::getInstance()->trans("Impossible to delete a customer who already have orders"));
            }

            $customer->delete();
        }
    }

    private function createOrUpdateCustomer(CustomerModel $customer, CustomerCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher)
    {
        $customer->setDispatcher($dispatcher);

        $customer->createOrUpdate(
            $event->getTitle(),
            $event->getFirstname(),
            $event->getLastname(),
            $event->getAddress1(),
            $event->getAddress2(),
            $event->getAddress3(),
            $event->getPhone(),
            $event->getCellphone(),
            $event->getZipcode(),
            $event->getCity(),
            $event->getCountry(),
            $event->getEmail(),
            $event->getPassword(),
            $event->getLangId(),
            $event->getReseller(),
            $event->getSponsor(),
            $event->getDiscount(),
            $event->getCompany(),
            $event->getRef(),
            $event->getEmailUpdateAllowed(),
            $event->getState()
        );

        $event->setCustomer($customer);
    }

    public function login(CustomerLoginEvent $event)
    {
        $customer = $event->getCustomer();

        if (method_exists($customer, 'clearDispatcher')) {
            $customer->clearDispatcher();
        }
        $this->securityContext->setCustomerUser($event->getCustomer());
    }

    /**
     * Perform user logout. The user is redirected to the provided view, if any.
     *
     * @param ActionEvent $event
     */
    public function logout(/** @noinspection PhpUnusedParameterInspection */ ActionEvent $event)
    {
        $this->securityContext->clearCustomerUser();
    }

    public function lostPassword(LostPasswordEvent $event)
    {
        if (null !== $customer = CustomerQuery::create()->filterByEmail($event->getEmail())->findOne()) {
            $password = Password::generateRandom(8);

            $customer
                ->setPassword($password)
                ->save()
            ;

            $this->mailer->sendEmailToCustomer('lost_password', $customer, ['password' => $password]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CUSTOMER_CREATEACCOUNT    => array('create', 128),
            TheliaEvents::CUSTOMER_UPDATEACCOUNT    => array('modify', 128),
            TheliaEvents::CUSTOMER_UPDATEPROFILE     => array('updateProfile', 128),
            TheliaEvents::CUSTOMER_LOGOUT           => array('logout', 128),
            TheliaEvents::CUSTOMER_LOGIN            => array('login', 128),
            TheliaEvents::CUSTOMER_DELETEACCOUNT    => array('delete', 128),
            TheliaEvents::LOST_PASSWORD             => array('lostPassword', 128)
        );
    }
}
