<?php
declare(strict_types=1);

namespace Yireo\CheckoutAgreementCommands\Test\Integration;

use PHPUnit\Framework\TestCase;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsEnabled;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsRegistered;

final class ModuleTest extends TestCase
{
    use AssertModuleIsEnabled;
    use AssertModuleIsRegistered;

    public function testIfModuleIsEnabled(): void
    {
        $requiredModules = [
            'Magento_CheckoutAgreements',
            'Magento_Store',
            'Yireo_CheckoutAgreementCommands',
        ];

        foreach ($requiredModules as $moduleName) {
            $this->assertModuleIsRegistered($moduleName);
            $this->assertModuleIsEnabled($moduleName);
        }
    }
}
