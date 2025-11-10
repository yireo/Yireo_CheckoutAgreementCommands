<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterfaceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAgreementCommand extends Command
{
    public function __construct(
        private CheckoutAgreementsRepositoryInterface $agreementsRepository,
        private AgreementInterfaceFactory $agreementFactory,
        ?string $name = null) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('checkout_agreement:create');
        $this->setDescription('Create an agreement');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Agreement name');
        $this->addOption('content', null, InputOption::VALUE_REQUIRED, 'Content');
        $this->addOption('checkbox_text', null, InputOption::VALUE_REQUIRED, 'Checkbox text');
        $this->addOption('is_active', null, InputOption::VALUE_OPTIONAL, 'Active', 1);
        $this->addOption('is_html', null, InputOption::VALUE_OPTIONAL, 'HTML', 1);
        $this->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Mode', 1);

        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output
    ): int {
        $name = (string)$input->getOption('name');
        $content = (string)$input->getOption('content');
        $checkboxText = (string)$input->getOption('checkbox_text');
        $isActive = (int)$input->getOption('is_active');
        $isHtml = (int)$input->getOption('is_html');
        $mode = (int)$input->getOption('mode');

        if (empty($name) || empty($content) || empty($checkboxText)) {
            $output->writeln('Provide a valid name, content and checkbox_text');
            return Command::FAILURE;
        }

        $agreement = $this->agreementFactory->create();
        $agreement->setName($name);
        $agreement->setContent($content);
        $agreement->setCheckboxText($checkboxText);
        $agreement->setIsActive($isActive);
        $agreement->setIsHtml($isHtml);
        $agreement->setMode($mode);

        $this->agreementsRepository->save($agreement);

        return Command::SUCCESS;
    }
}
