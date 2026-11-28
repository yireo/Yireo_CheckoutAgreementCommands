<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Test\Unit\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CheckoutAgreementCommands\Console\Command\CreateAgreementCommand;

final class CreateAgreementCommandTest extends TestCase
{
    private CheckoutAgreementsRepositoryInterface&MockObject $repository;
    private AgreementInterfaceFactory&MockObject $factory;
    private StoreManagerInterface&MockObject $storeManager;
    private AgreementInterface&MockObject $agreement;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CheckoutAgreementsRepositoryInterface::class);
        $this->factory = $this->createMock(AgreementInterfaceFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->agreement = $this->createMock(AgreementInterface::class);
        $this->factory->method('create')->willReturn($this->agreement);
        $this->storeManager->method('getStore')->willReturnCallback(function ($storeId) {
            if (!in_array((int)$storeId, [0, 1], true)) {
                throw new NoSuchEntityException(new Phrase('Store not found'));
            }

            return $this->createStub(StoreInterface::class);
        });
    }

    public function testConfiguration(): void
    {
        $definition = $this->createCommand()->getDefinition();

        $this->assertSame('checkout_agreement:create', $this->createCommand()->getName());
        foreach (['name', 'content', 'checkbox_text', 'is_active', 'is_html', 'mode', 'store_id'] as $option) {
            $this->assertTrue($definition->hasOption($option), $option);
            $this->assertTrue($definition->getOption($option)->isValueRequired(), $option);
        }

        $this->assertSame('1', $definition->getOption('is_active')->getDefault());
        $this->assertSame('0', $definition->getOption('is_html')->getDefault());
        $this->assertSame('1', $definition->getOption('mode')->getDefault());
        $this->assertSame('0', $definition->getOption('store_id')->getDefault());
    }

    public function testSavesAgreementWithTrimmedValuesAndDefaults(): void
    {
        $this->agreement->expects($this->once())->method('setName')->with('Terms');
        $this->agreement->expects($this->once())->method('setContent')->with("Line 1\nLine 2");
        $this->agreement->expects($this->once())->method('setCheckboxText')->with('I agree');
        $this->agreement->expects($this->once())->method('setIsActive')->with(true);
        $this->agreement->expects($this->once())->method('setIsHtml')->with(false);
        $this->agreement->expects($this->once())->method('setMode')->with(1);
        $this->agreement->method('getAgreementId')->willReturn('42');
        $this->repository->expects($this->once())->method('save')
            ->with($this->agreement, 0)
            ->willReturn($this->agreement);

        $commandTester = $this->execute([
            '--name' => " \tTerms\x00\n",
            '--content' => "\nLine 1\nLine 2\r\n",
            '--checkbox_text' => "\vI agree\f",
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Created agreement with ID 42', $commandTester->getDisplay());
        $this->assertStringNotContainsString('Warning', $commandTester->getDisplay());
    }

    public function testSavesInactiveAutomaticHtmlAgreementForStore(): void
    {
        $this->agreement->expects($this->once())->method('setIsActive')->with(false);
        $this->agreement->expects($this->once())->method('setIsHtml')->with(true);
        $this->agreement->expects($this->once())->method('setMode')->with(0);
        $this->repository->expects($this->once())->method('save')
            ->with($this->agreement, 1)
            ->willReturn($this->agreement);

        $commandTester = $this->execute([
            '--is_active' => '0',
            '--is_html' => '1',
            '--mode' => '0',
            '--store_id' => '01',
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('HTML content is rendered unescaped', $commandTester->getDisplay());
    }

    public function testAcceptsMaximumMultibyteLength(): void
    {
        $value = str_repeat('é', 255);
        $this->agreement->expects($this->once())->method('setName')->with($value);
        $this->agreement->expects($this->once())->method('setCheckboxText')->with($value);
        $this->repository->expects($this->once())->method('save')->willReturn($this->agreement);

        $commandTester = $this->execute(['--name' => $value, '--checkbox_text' => $value]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    #[DataProvider('getInvalidOptions')]
    public function testRejectsInvalidInput(array $options, string $expectedError): void
    {
        $this->factory->expects($this->never())->method('create');
        $this->repository->expects($this->never())->method('save');

        $commandTester = $this->execute($options);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString($expectedError, $commandTester->getDisplay());
    }

    public static function getInvalidOptions(): array
    {
        return [
            'missing name' => [['--name' => null], 'Provide a valid name, content and checkbox_text'],
            'control characters only' => [['--checkbox_text' => "\x00\t\n"], 'Provide a valid name'],
            'long multibyte name' => [['--name' => str_repeat('é', 256)], 'at most 255 characters'],
            'long checkbox text' => [['--checkbox_text' => str_repeat('a', 256)], 'at most 255 characters'],
            'boolean word' => [['--is_active' => 'true'], '"is_active" must be 0 or 1'],
            'padded boolean' => [['--is_html' => ' 1'], '"is_html" must be 0 or 1'],
            'invalid mode' => [['--mode' => '2'], '"mode" must be 0 or 1'],
            'non-numeric store' => [['--store_id' => 'abc'], '"store_id" must be'],
            'decimal store' => [['--store_id' => '1.5'], '"store_id" must be'],
            'empty store' => [['--store_id' => ''], '"store_id" must be'],
            'negative store' => [['--store_id' => '-1'], '"store_id" must be'],
            'unknown store' => [['--store_id' => '999'], '"store_id" must be'],
        ];
    }

    public function testShowsEveryValidationError(): void
    {
        $commandTester = $this->execute([
            '--name' => '',
            '--is_active' => 'x',
            '--is_html' => 'x',
            '--mode' => 'x',
            '--store_id' => 'x',
        ]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertSame(5, substr_count($commandTester->getDisplay(), PHP_EOL));
    }

    public function testShowsEscapedSaveError(): void
    {
        $this->repository->method('save')
            ->willThrowException(new CouldNotSaveException(new Phrase('Failed <info>badly</info>')));

        $commandTester = $this->execute();

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Failed <info>badly</info>', $commandTester->getDisplay());
        $this->assertStringNotContainsString('Created agreement', $commandTester->getDisplay());
    }

    private function execute(array $options = []): CommandTester
    {
        $options = array_merge([
            '--name' => 'Terms',
            '--content' => 'Content',
            '--checkbox_text' => 'I agree',
        ], $options);

        $commandTester = new CommandTester($this->createCommand());
        $commandTester->execute(array_filter($options, fn ($value) => $value !== null), ['decorated' => false]);

        return $commandTester;
    }

    private function createCommand(): CreateAgreementCommand
    {
        return new CreateAgreementCommand($this->repository, $this->factory, $this->storeManager);
    }
}
