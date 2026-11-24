<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Test\Integration\Console\Command;

use Magento\CheckoutAgreements\Model\ResourceModel\Agreement\Collection;
use Magento\CheckoutAgreements\Model\ResourceModel\Agreement\CollectionFactory;
use Magento\Framework\Console\CommandListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CheckoutAgreementCommands\Console\Command\CreateAgreementCommand;

/**
 * @magentoDbIsolation enabled
 */
class CreateAgreementCommandTest extends TestCase
{
    private const NAME = 'Yireo CheckoutAgreementCommands test';

    public function testCommandIsRegistered(): void
    {
        $commands = Bootstrap::getObjectManager()->get(CommandListInterface::class)->getCommands();
        $command = $commands['Yireo_CheckoutAgreementCommands::create'] ?? null;

        $this->assertInstanceOf(CreateAgreementCommand::class, $command);
        $this->assertSame('checkout_agreement:create', $command->getName());
    }

    public function testCreatesPlainTextAgreementForAllStoresByDefault(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--name' => self::NAME,
            '--content' => 'Content',
            '--checkbox_text' => 'I agree',
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Created agreement with ID', $commandTester->getDisplay());
        $this->assertStringNotContainsString('Warning', $commandTester->getDisplay());

        $agreements = $this->getAgreements();
        $this->assertCount(1, $agreements);

        $agreement = $agreements->getFirstItem();
        $this->assertSame('Content', $agreement->getContent());
        $this->assertSame('I agree', $agreement->getCheckboxText());
        $this->assertSame(1, (int)$agreement->getIsActive());
        $this->assertSame(0, (int)$agreement->getIsHtml());
        $this->assertSame(1, (int)$agreement->getMode());
        $this->assertSame([0], array_map('intval', (array)$agreement->getStores()));
    }

    public function testWarnsWhenCreatingHtmlAgreement(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--name' => self::NAME,
            '--content' => '<p>Content</p>',
            '--checkbox_text' => 'I agree',
            '--is_html' => '1',
            '--store_id' => '1',
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('rendered unescaped', $commandTester->getDisplay());

        $agreement = $this->getAgreements()->getFirstItem();
        $this->assertSame(1, (int)$agreement->getIsHtml());
        $this->assertSame([1], array_map('intval', (array)$agreement->getStores()));
    }

    #[DataProvider('getInvalidOptions')]
    public function testRejectsInvalidInput(array $options, string $expectedError): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(array_merge([
            '--name' => self::NAME,
            '--content' => 'Content',
            '--checkbox_text' => 'I agree',
        ], $options));

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString($expectedError, $commandTester->getDisplay());
        $this->assertCount(0, $this->getAgreements());
    }

    public static function getInvalidOptions(): array
    {
        return [
            'empty name' => [['--name' => " \t "], 'Provide a valid name'],
            'empty content' => [['--content' => ''], 'Provide a valid name'],
            'long checkbox text' => [['--checkbox_text' => str_repeat('a', 256)], 'at most 255'],
            'invalid is_active' => [['--is_active' => 'yes'], '"is_active" must be 0 or 1'],
            'invalid is_html' => [['--is_html' => '2'], '"is_html" must be 0 or 1'],
            'invalid mode' => [['--mode' => '7'], '"mode" must be 0 or 1'],
            'negative store' => [['--store_id' => '-1'], '"store_id" must be'],
            'unknown store' => [['--store_id' => '999999'], '"store_id" must be'],
        ];
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(Bootstrap::getObjectManager()->create(CreateAgreementCommand::class));
    }

    private function getAgreements(): Collection
    {
        $collection = Bootstrap::getObjectManager()->get(CollectionFactory::class)->create();
        $collection->addFieldToFilter('name', self::NAME);
        foreach ($collection as $agreement) {
            $agreement->getResource()->load($agreement, $agreement->getId());
        }

        return $collection;
    }
}
