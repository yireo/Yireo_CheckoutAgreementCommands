<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsListInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListAgreementsCommand extends Command
{
    public function __construct(
        private CheckoutAgreementsListInterface $agreementsList,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('checkout_agreement:list');
        $this->setDescription('List all agreements');
        parent::configure();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $table = new Table($output);
        $table->setHeaders([
            'ID',
            'Name',
            'Mode',
            'Is Active',
            'Is HTML',
        ]);

        $agreements = $this->agreementsList->getList($this->searchCriteriaBuilder->create());
        foreach ($agreements as $agreement) {
            $table->addRow([
                $agreement->getId(),
                $agreement->getName(),
                $agreement->getMode(),
                $agreement->getIsActive(),
                $agreement->getIsHtml(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
