<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Test\Unit\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsListInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\CheckoutAgreementCommands\Console\Command\ListAgreementsCommand;
use Yireo\CheckoutAgreementCommands\Console\OutputSanitizer;

final class ListAgreementsCommandTest extends TestCase
{
    private CheckoutAgreementsListInterface&MockObject $agreementsList;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private SearchCriteria $searchCriteria;

    protected function setUp(): void
    {
        $this->agreementsList = $this->createMock(CheckoutAgreementsListInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteria = $this->createStub(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('create')->willReturn($this->searchCriteria);
    }

    public function testConfiguration(): void
    {
        $command = $this->createCommand(new OutputSanitizer());

        $this->assertSame('checkout_agreement:list', $command->getName());
        $this->assertSame('List all agreements', $command->getDescription());
    }

    public function testRendersOnlyHeadersWithoutAgreements(): void
    {
        $this->agreementsList->expects($this->once())->method('getList')
            ->with($this->searchCriteria)
            ->willReturn([]);

        $commandTester = $this->execute(new OutputSanitizer());

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertMatchesRegularExpression('/ID\s+\|\s+Name\s+\|\s+Mode\s+\|\s+Is Active\s+\|\s+Is HTML/', $commandTester->getDisplay());
        $this->assertCount(1, preg_grep('/^\|/', explode(PHP_EOL, $commandTester->getDisplay())));
    }

    public function testRendersAgreementsAsIntegersWithSanitizedNames(): void
    {
        $this->agreementsList->method('getList')->willReturn([
            $this->createAgreement('7', 'Terms', '1', '1', '0'),
            $this->createAgreement('8', 'Privacy', null, '0', '1'),
        ]);

        $outputSanitizer = $this->createMock(OutputSanitizer::class);
        $outputSanitizer->expects($this->exactly(2))->method('sanitize')
            ->willReturnCallback(fn ($value) => 'Sanitized ' . $value);

        $display = $this->execute($outputSanitizer)->getDisplay();

        $this->assertMatchesRegularExpression('/\|\s+7\s+\|\s+Sanitized Terms\s+\|\s+1\s+\|\s+1\s+\|\s+0\s+\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s+8\s+\|\s+Sanitized Privacy\s+\|\s+0\s+\|\s+0\s+\|\s+1\s+\|/', $display);
    }

    private function createAgreement(string $id, string $name, ?string $mode, string $isActive, string $isHtml): AgreementInterface
    {
        $agreement = $this->createStub(AgreementInterface::class);
        $agreement->method('getAgreementId')->willReturn($id);
        $agreement->method('getName')->willReturn($name);
        $agreement->method('getMode')->willReturn($mode);
        $agreement->method('getIsActive')->willReturn($isActive);
        $agreement->method('getIsHtml')->willReturn($isHtml);

        return $agreement;
    }

    private function execute(OutputSanitizer $outputSanitizer): CommandTester
    {
        $commandTester = new CommandTester($this->createCommand($outputSanitizer));
        $commandTester->execute([], ['decorated' => false]);

        return $commandTester;
    }

    private function createCommand(OutputSanitizer $outputSanitizer): ListAgreementsCommand
    {
        return new ListAgreementsCommand($this->agreementsList, $this->searchCriteriaBuilder, $outputSanitizer);
    }
}
