<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Console\Command;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAgreementCommand extends Command
{
    private const MAX_LENGTH = 255;
    private const BOOLEAN_VALUES = ['0', '1'];
    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";

    public function __construct(
        private readonly CheckoutAgreementsRepositoryInterface $agreementsRepository,
        private readonly AgreementInterfaceFactory $agreementFactory,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('checkout_agreement:create');
        $this->setDescription('Create an agreement');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Agreement name');
        $this->addOption('content', null, InputOption::VALUE_REQUIRED, 'Content');
        $this->addOption('checkbox_text', null, InputOption::VALUE_REQUIRED, 'Checkbox text');
        $this->addOption('is_active', null, InputOption::VALUE_REQUIRED, 'Active (0 or 1)', '1');
        $this->addOption(
            'is_html',
            null,
            InputOption::VALUE_REQUIRED,
            'HTML (0 or 1). HTML content is rendered unescaped in the checkout',
            '0'
        );
        $this->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode (0 = automatic, 1 = manual)', '1');
        $this->addOption('store_id', null, InputOption::VALUE_REQUIRED, 'Store ID (0 = all store views)', '0');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim((string)$input->getOption('name'), self::TRIM_CHARACTERS);
        $content = trim((string)$input->getOption('content'), self::TRIM_CHARACTERS);
        $checkboxText = trim((string)$input->getOption('checkbox_text'), self::TRIM_CHARACTERS);

        $errors = [];
        if ($name === '' || $content === '' || $checkboxText === '') {
            $errors[] = 'Provide a valid name, content and checkbox_text';
        }

        if (mb_strlen($name) > self::MAX_LENGTH || mb_strlen($checkboxText) > self::MAX_LENGTH) {
            $errors[] = 'The name and checkbox_text may contain at most ' . self::MAX_LENGTH . ' characters';
        }

        foreach (['is_active', 'is_html', 'mode'] as $option) {
            if (!in_array((string)$input->getOption($option), self::BOOLEAN_VALUES, true)) {
                $errors[] = 'The option "' . $option . '" must be 0 or 1';
            }
        }

        $storeId = (string)$input->getOption('store_id');
        if (!ctype_digit($storeId) || !$this->storeExists((int)$storeId)) {
            $errors[] = 'The option "store_id" must be the ID of an existing store';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $output->writeln('<error>' . OutputFormatter::escape($error) . '</error>');
            }

            return Command::FAILURE;
        }

        $isHtml = (int)$input->getOption('is_html');
        if ($isHtml === 1) {
            $output->writeln('<comment>Warning: HTML content is rendered unescaped in the checkout</comment>');
        }

        $agreement = $this->agreementFactory->create();
        $agreement->setName($name);
        $agreement->setContent($content);
        $agreement->setCheckboxText($checkboxText);
        $agreement->setIsActive((bool)(int)$input->getOption('is_active'));
        $agreement->setIsHtml((bool)$isHtml);
        $agreement->setMode((int)$input->getOption('mode'));

        try {
            $agreement = $this->agreementsRepository->save($agreement, (int)$storeId);
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('Created agreement with ID ' . (int)$agreement->getAgreementId());

        return Command::SUCCESS;
    }

    private function storeExists(int $storeId): bool
    {
        try {
            $this->storeManager->getStore($storeId);
            return true;
        } catch (LocalizedException) {
            return false;
        }
    }
}
