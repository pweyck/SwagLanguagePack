<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\LanguagePack\Test\Core\System\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Command\SalesChannelCreateCommand as InheritedSalesChannelCreateCommand;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Swag\LanguagePack\Core\System\SalesChannel\Command\SalesChannelCreateCommand;
use Swag\LanguagePack\PackLanguage\PackLanguageDefinition;
use Swag\LanguagePack\PackLanguage\PackLanguageEntity;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class SalesChannelCreateCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CommandTestBehaviour;

    protected InheritedSalesChannelCreateCommand $originalSalesChannelCreateCommand;

    protected SalesChannelCreateCommand $overrideSalesChannelCreateCommand;

    protected EntityRepositoryInterface $salesChannelRepository;

    protected EntityRepositoryInterface $languageRepository;

    protected EntityRepositoryInterface $languagePackRepository;

    private Context $context;

    protected function setUp(): void
    {
        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get(\sprintf('%s.repository', SalesChannelDefinition::ENTITY_NAME));
        $this->salesChannelRepository = $salesChannelRepository;

        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get(\sprintf('%s.repository', LanguageDefinition::ENTITY_NAME));
        $this->languageRepository = $languageRepository;

        /** @var EntityRepositoryInterface $languagePackRepository */
        $languagePackRepository = $this->getContainer()->get(\sprintf('%s.repository', PackLanguageDefinition::ENTITY_NAME));
        $this->languagePackRepository = $languagePackRepository;

        $this->context = Context::createDefaultContext();

        /** @var DefinitionInstanceRegistry $definitionRegistry */
        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get(\sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME));
        /** @var EntityRepositoryInterface $shippingMethodRepository */
        $shippingMethodRepository = $this->getContainer()->get(\sprintf('%s.repository', ShippingMethodDefinition::ENTITY_NAME));
        /** @var EntityRepositoryInterface $countryRepository */
        $countryRepository = $this->getContainer()->get(\sprintf('%s.repository', CountryDefinition::ENTITY_NAME));
        /** @var EntityRepositoryInterface $snippetSetRepository */
        $snippetSetRepository = $this->getContainer()->get(\sprintf('%s.repository', SnippetSetDefinition::ENTITY_NAME));
        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get(\sprintf('%s.repository', CategoryDefinition::ENTITY_NAME));

        $this->originalSalesChannelCreateCommand = new InheritedSalesChannelCreateCommand(
            $definitionRegistry,
            $this->salesChannelRepository,
            $paymentMethodRepository,
            $shippingMethodRepository,
            $countryRepository,
            $snippetSetRepository,
            $categoryRepository
        );

        // this should get the replaced / new command. The old one should not be in the container anymore.
        /** @var SalesChannelCreateCommand $overrideSalesChannelCommand */
        $overrideSalesChannelCommand = $this->getContainer()->get(
            InheritedSalesChannelCreateCommand::class
        );
        $this->overrideSalesChannelCreateCommand = $overrideSalesChannelCommand;
    }

    public function testIfOriginalCommandFails(): void
    {
        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->originalSalesChannelCreateCommand, $input, $output);

        $outputString = $output->fetch();

        static::assertStringContainsString('[ERROR]', $outputString);
    }

    public function testIfNewCommandSucceeds(): void
    {
        $salesChannelId = 'ad1028c2a8ed46d2a24f189812b1a23c';
        $input = new StringInput("--id=$salesChannelId");
        $output = new BufferedOutput();

        $this->runCommand($this->overrideSalesChannelCreateCommand, $input, $output);

        $outputString = $output->fetch();

        static::assertStringNotContainsString('[ERROR]', $outputString);

        // check the associated languages of the created sales channel
        $associatedLanguages = $this->getAssociatedLanguageLocalesOfSalesChannel($salesChannelId);
        static::assertCount(2, $associatedLanguages);
        static::assertContains('en-GB', $associatedLanguages);
        static::assertContains('de-DE', $associatedLanguages);
    }

    public function testIfNewCommandConsidersActivatedLanguages(): void
    {
        $this->activateLanguageForSalesChannelUsage('fr-FR');

        $salesChannelId = 'ad1028c2a8ed46d2a24f189812b1a23b';
        $input = new StringInput("--id=$salesChannelId");
        $output = new BufferedOutput();

        $this->runCommand($this->overrideSalesChannelCreateCommand, $input, $output);

        $outputString = $output->fetch();

        static::assertStringNotContainsString('[ERROR]', $outputString);

        // check the associated languages of the created sales channel
        $associatedLanguages = $this->getAssociatedLanguageLocalesOfSalesChannel($salesChannelId);
        static::assertCount(3, $associatedLanguages);
        static::assertContains('en-GB', $associatedLanguages);
        static::assertContains('de-DE', $associatedLanguages);
        static::assertContains('fr-FR', $associatedLanguages);
    }

    protected function activateLanguageForSalesChannelUsage(string $localeCode): void
    {
        // fetch the language with the locale
        $criteria = new Criteria();
        $criteria->addAssociation('swagLanguagePackLanguage');
        $criteria->addFilter(new EqualsFilter('locale.code', $localeCode));

        /** @var LanguageEntity|null $result */
        $result = $this->languageRepository->search($criteria, $this->context)->first();
        static::assertNotNull($result);
        /** @var PackLanguageEntity|null $languagePack */
        $languagePack = $result->getExtension('swagLanguagePackLanguage');
        static::assertNotNull($languagePack);

        $this->languagePackRepository->update([
            [
                'id' => $languagePack->getId(),
                'salesChannelActive' => true,
            ],
        ], $this->context);
    }

    /**
     * @return string[]
     */
    protected function getAssociatedLanguageLocalesOfSalesChannel(string $salesChannelId): array
    {
        // fetch the language with the locale
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('languages.locale');

        /** @var SalesChannelEntity|null $result */
        $result = $this->salesChannelRepository->search($criteria, $this->context)->first();
        static::assertNotNull($result);
        $languages = $result->getLanguages();
        static::assertNotNull($languages);

        return \array_map(function (LanguageEntity $lang) {
            $locale = $lang->getLocale();
            static::assertNotNull($locale);

            return $locale->getCode();
        }, \array_values($languages->getElements()));
    }
}
