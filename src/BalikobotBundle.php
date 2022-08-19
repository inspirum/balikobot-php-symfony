<?php

declare(strict_types=1);

namespace Inspirum\Balikobot\Integration\Symfony;

use Inspirum\Balikobot\Client\Client;
use Inspirum\Balikobot\Client\DefaultClient;
use Inspirum\Balikobot\Client\DefaultCurlRequester;
use Inspirum\Balikobot\Client\Requester;
use Inspirum\Balikobot\Client\Response\Validator;
use Inspirum\Balikobot\Model\Account\AccountFactory;
use Inspirum\Balikobot\Model\Account\DefaultAccountFactory;
use Inspirum\Balikobot\Model\AdrUnit\AdrUnitFactory;
use Inspirum\Balikobot\Model\AdrUnit\DefaultAdrUnitFactory;
use Inspirum\Balikobot\Model\Attribute\AttributeFactory;
use Inspirum\Balikobot\Model\Attribute\DefaultAttributeFactory;
use Inspirum\Balikobot\Model\Branch\BranchFactory;
use Inspirum\Balikobot\Model\Branch\BranchResolver;
use Inspirum\Balikobot\Model\Branch\DefaultBranchFactory;
use Inspirum\Balikobot\Model\Branch\DefaultBranchResolver;
use Inspirum\Balikobot\Model\Carrier\CarrierFactory;
use Inspirum\Balikobot\Model\Carrier\DefaultCarrierFactory;
use Inspirum\Balikobot\Model\Changelog\ChangelogFactory;
use Inspirum\Balikobot\Model\Changelog\DefaultChangelogFactory;
use Inspirum\Balikobot\Model\Country\CountryFactory;
use Inspirum\Balikobot\Model\Country\DefaultCountryFactory;
use Inspirum\Balikobot\Model\Label\DefaultLabelFactory;
use Inspirum\Balikobot\Model\Label\LabelFactory;
use Inspirum\Balikobot\Model\ManipulationUnit\DefaultManipulationUnitFactory;
use Inspirum\Balikobot\Model\ManipulationUnit\ManipulationUnitFactory;
use Inspirum\Balikobot\Model\Method\DefaultMethodFactory;
use Inspirum\Balikobot\Model\Method\MethodFactory;
use Inspirum\Balikobot\Model\OrderedShipment\DefaultOrderedShipmentFactory;
use Inspirum\Balikobot\Model\OrderedShipment\OrderedShipmentFactory;
use Inspirum\Balikobot\Model\Package\DefaultPackageFactory;
use Inspirum\Balikobot\Model\Package\PackageFactory;
use Inspirum\Balikobot\Model\PackageData\DefaultPackageDataFactory;
use Inspirum\Balikobot\Model\PackageData\PackageDataFactory;
use Inspirum\Balikobot\Model\ProofOfDelivery\DefaultProofOfDeliveryFactory;
use Inspirum\Balikobot\Model\ProofOfDelivery\ProofOfDeliveryFactory;
use Inspirum\Balikobot\Model\Service\DefaultServiceFactory;
use Inspirum\Balikobot\Model\Service\ServiceFactory;
use Inspirum\Balikobot\Model\Status\DefaultStatusFactory;
use Inspirum\Balikobot\Model\Status\StatusFactory;
use Inspirum\Balikobot\Model\TransportCost\DefaultTransportCostFactory;
use Inspirum\Balikobot\Model\TransportCost\TransportCostFactory;
use Inspirum\Balikobot\Model\ZipCode\DefaultZipCodeFactory;
use Inspirum\Balikobot\Model\ZipCode\ZipCodeFactory;
use Inspirum\Balikobot\Provider\CarrierProvider;
use Inspirum\Balikobot\Provider\DefaultCarrierProvider;
use Inspirum\Balikobot\Provider\DefaultServiceProvider;
use Inspirum\Balikobot\Provider\LiveCarrierProvider;
use Inspirum\Balikobot\Provider\LiveServiceProvider;
use Inspirum\Balikobot\Provider\ServiceProvider;
use Inspirum\Balikobot\Service\BranchService;
use Inspirum\Balikobot\Service\DefaultBranchService;
use Inspirum\Balikobot\Service\DefaultInfoService;
use Inspirum\Balikobot\Service\DefaultPackageService;
use Inspirum\Balikobot\Service\DefaultSettingService;
use Inspirum\Balikobot\Service\DefaultTrackService;
use Inspirum\Balikobot\Service\InfoService;
use Inspirum\Balikobot\Service\PackageService;
use Inspirum\Balikobot\Service\SettingService;
use Inspirum\Balikobot\Service\TrackService;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class BalikobotBundle extends AbstractBundle
{
    public const ALIAS = 'balikobot';

    protected string $extensionAlias = self::ALIAS;

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition  $root*/
        $root     = $definition->rootNode();
        $children = $root->children();

        $children->scalarNode('api_user')
             ->example('balikobot_test2cztest')
             ->isRequired()
             ->cannotBeEmpty()
             ->end();

        $children->scalarNode('api_key')
             ->example('#lS1tBVo')
             ->isRequired()
             ->cannotBeEmpty()
             ->end();

        $children->end();
    }

    /**
     * @param array<string,mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $this->registerClient($services, $config);
        $this->registerFactories($services);
        $this->registerProviders($services);
        $this->registerServices($services);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function registerClient(ServicesConfigurator $services, array $config): void
    {
        $services->set(DefaultCurlRequester::class)->args([$config['api_user'], $config['api_key']]);
        $services->alias(Requester::class, DefaultCurlRequester::class);

        $services->set(Validator::class);

        $services->set(DefaultClient::class)->args([
            new Reference(Requester::class),
            new Reference(Validator::class),
        ]);
        $services->alias(Client::class, DefaultClient::class);
    }

    private function registerFactories(ServicesConfigurator $services): void
    {
        $services->set(DefaultAccountFactory::class)->args([
            new Reference(CarrierFactory::class),
        ]);
        $services->alias(AccountFactory::class, DefaultAccountFactory::class);

        $services->set(DefaultAdrUnitFactory::class);
        $services->alias(AdrUnitFactory::class, DefaultAdrUnitFactory::class);

        $services->set(DefaultAttributeFactory::class);
        $services->alias(AttributeFactory::class, DefaultAttributeFactory::class);

        $services->set(DefaultBranchFactory::class);
        $services->alias(BranchFactory::class, DefaultBranchFactory::class);

        $services->set(DefaultBranchResolver::class);
        $services->alias(BranchResolver::class, DefaultBranchResolver::class);

        $services->set(DefaultCarrierFactory::class)->args([
            new Reference(MethodFactory::class),
        ]);
        $services->alias(CarrierFactory::class, DefaultCarrierFactory::class);

        $services->set(DefaultChangelogFactory::class);
        $services->alias(ChangelogFactory::class, DefaultChangelogFactory::class);

        $services->set(DefaultCountryFactory::class);
        $services->alias(CountryFactory::class, DefaultCountryFactory::class);

        $services->set(DefaultLabelFactory::class);
        $services->alias(LabelFactory::class, DefaultLabelFactory::class);

        $services->set(DefaultManipulationUnitFactory::class);
        $services->alias(ManipulationUnitFactory::class, DefaultManipulationUnitFactory::class);

        $services->set(DefaultMethodFactory::class);
        $services->alias(MethodFactory::class, DefaultMethodFactory::class);

        $services->set(DefaultOrderedShipmentFactory::class);
        $services->alias(OrderedShipmentFactory::class, DefaultOrderedShipmentFactory::class);

        $services->set(DefaultPackageFactory::class)->args([
            new Reference(Validator::class),
        ]);
        $services->alias(PackageFactory::class, DefaultPackageFactory::class);

        $services->set(DefaultPackageDataFactory::class);
        $services->alias(PackageDataFactory::class, DefaultPackageDataFactory::class);

        $services->set(DefaultProofOfDeliveryFactory::class)->args([
            new Reference(Validator::class),
        ]);
        $services->alias(ProofOfDeliveryFactory::class, DefaultProofOfDeliveryFactory::class);

        $services->set(DefaultServiceFactory::class)->args([
            new Reference(CountryFactory::class),
        ]);
        $services->alias(ServiceFactory::class, DefaultServiceFactory::class);

        $services->set(DefaultStatusFactory::class)->args([
            new Reference(Validator::class),
        ]);
        $services->alias(StatusFactory::class, DefaultStatusFactory::class);

        $services->set(DefaultTransportCostFactory::class)->args([
            new Reference(Validator::class),
        ]);
        $services->alias(TransportCostFactory::class, DefaultTransportCostFactory::class);

        $services->set(DefaultZipCodeFactory::class);
        $services->alias(ZipCodeFactory::class, DefaultZipCodeFactory::class);
    }

    private function registerProviders(ServicesConfigurator $services): void
    {
        $services->set(DefaultCarrierProvider::class);
        $services->set(LiveCarrierProvider::class)->args([
            new Reference(SettingService::class),
        ]);
        $services->alias(CarrierProvider::class, DefaultCarrierProvider::class);

        $services->set(DefaultServiceProvider::class);
        $services->set(LiveServiceProvider::class)->args([
            new Reference(SettingService::class),
        ]);
        $services->alias(ServiceProvider::class, DefaultServiceProvider::class);
    }

    private function registerServices(ServicesConfigurator $services): void
    {
        $services->set(DefaultBranchService::class)->args([
            new Reference(Client::class),
            new Reference(BranchFactory::class),
            new Reference(BranchResolver::class),
            new Reference(CarrierProvider::class),
            new Reference(ServiceProvider::class),
        ]);

        $services->alias(BranchService::class, DefaultBranchService::class);

        $services->set(DefaultInfoService::class)->args([
            new Reference(Client::class),
            new Reference(AccountFactory::class),
            new Reference(ChangelogFactory::class),
        ]);

        $services->alias(InfoService::class, DefaultInfoService::class);

        $services->set(DefaultPackageService::class)->args([
            new Reference(Client::class),
            new Reference(PackageDataFactory::class),
            new Reference(PackageFactory::class),
            new Reference(OrderedShipmentFactory::class),
            new Reference(LabelFactory::class),
            new Reference(ProofOfDeliveryFactory::class),
            new Reference(TransportCostFactory::class),
        ]);
        $services->alias(PackageService::class, DefaultPackageService::class);

        $services->set(DefaultSettingService::class)->args([
            new Reference(Client::class),
            new Reference(CarrierFactory::class),
            new Reference(ServiceFactory::class),
            new Reference(ManipulationUnitFactory::class),
            new Reference(CountryFactory::class),
            new Reference(ZipCodeFactory::class),
            new Reference(AdrUnitFactory::class),
            new Reference(AttributeFactory::class),
        ]);
        $services->alias(SettingService::class, DefaultSettingService::class);

        $services->set(DefaultTrackService::class)->args([
            new Reference(Client::class),
            new Reference(StatusFactory::class),
        ]);
        $services->alias(TrackService::class, DefaultTrackService::class);
    }
}
