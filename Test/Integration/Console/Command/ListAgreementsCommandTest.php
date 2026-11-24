<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Test\Integration\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterfaceFactory;
use Magento\Framework\Console\CommandListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CheckoutAgreementCommands\Console\Command\ListAgreementsCommand;

/**
 * @magentoDbIsolation enabled
 */
class ListAgreementsCommandTest extends TestCase
{
    public function testCommandIsRegistered(): void
    {
        $commands = Bootstrap::getObjectManager()->get(CommandListInterface::class)->getCommands();
        $command = $commands['Yireo_CheckoutAgreementCommands::list'] ?? null;

        $this->assertInstanceOf(ListAgreementsCommand::class, $command);
        $this->assertSame('checkout_agreement:list', $command->getName());
    }

    public function testListsAgreementWithoutTerminalInjection(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $agreement = $objectManager->get(AgreementInterfaceFactory::class)->create();
        $agreement->setName("Yireo<error>Foo</error>\x1b[2J<href=https://evil.test>Bar</>\x1b]0;Owned\x07\rBaz");
        $agreement->setContent('Content');
        $agreement->setCheckboxText('I agree');
        $agreement->setIsActive(true);
        $agreement->setIsHtml(false);
        $agreement->setMode(1);
        $agreement = $objectManager->get(CheckoutAgreementsRepositoryInterface::class)->save($agreement, 0);

        $commandTester = new CommandTester($objectManager->create(ListAgreementsCommand::class));
        $commandTester->execute([], ['decorated' => false, 'verbosity' => OutputInterface::VERBOSITY_NORMAL]);

        $display = $commandTester->getDisplay();
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'Yireo<error>Foo</error><href=https://evil.test>Bar</>Baz',
            $display
        );
        $this->assertStringContainsString((string)$agreement->getAgreementId(), $display);
        $this->assertStringNotContainsString("\x1b", $display);
        $this->assertStringNotContainsString("\r", $display);
    }
}
