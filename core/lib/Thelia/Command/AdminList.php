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

namespace Thelia\Command;

use \DateTime;
use \Exception;
use \PDO;
use Propel\Runtime\Propel;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\Map\AdminTableMap;

/**
 * Class AdminListCommand
 * @package Thelia\Command
 * @author Michaël Marinetti <github@marinetti.fr>
 */
class AdminList extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("admin:list")
            ->setDescription("list all administrators")
            ->setHelp("The <info>admin:list</info> command list all admin users.")
            ->addOption(
                'login-like',
                null,
                InputOption::VALUE_OPTIONAL,
                'Admin login name like',
                null
            )
            ->addOption(
                'email-like',
                null,
                InputOption::VALUE_OPTIONAL,
                'Admin email like',
                null
            )
            ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = new Table($output);
        $helper->addRows($this->getAdminsData($input));

        $helper
            ->setHeaders(["Id", "Login", "Email", "Firstname", "Lastname"])
            ->render()
            ;
    }

    protected function getAdminsData(InputInterface $input)
    {
        $loginLike = $input->getOption("login-like");
        $emailLike = $input->getOption("email-like");

        $adminData = AdminQuery::create();
        if ($loginLike) {
            $adminData->filterByLogin("%$loginLike%");
        }
        if ($emailLike) {
            $adminData->filterByEmail("%$emailLike%");
        }

        $adminData = $adminData->orderById()
            ->addAsColumn("id", AdminTableMap::ID)
            ->addAsColumn("login", AdminTableMap::LOGIN)
            ->addAsColumn("email", AdminTableMap::EMAIL)
            ->addAsColumn("firstname", AdminTableMap::FIRSTNAME)
            ->addAsColumn("lastname", AdminTableMap::LASTNAME)
            ->select([
                "id",
                "login",
                "firstname",
                "lastname",
                "email",
            ])
            ->find()
            ->toArray()
            ;

        return $adminData;
    }
}
