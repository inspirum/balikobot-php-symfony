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
use Inspirum\Balikobot\Service\Registry\DefaultServiceContainer;
use Inspirum\Balikobot\Service\Registry\DefaultServiceContainerRegistry;
use Inspirum\Balikobot\Service\Registry\ServiceContainer;
use Inspirum\Balikobot\Service\Registry\ServiceContainerRegistry;
use Inspirum\Balikobot\Service\SettingService;
use Inspirum\Balikobot\Service\TrackService;
use RuntimeException;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function count;
use function sprintf;

final class BalikobotBundle extends AbstractBundle
{
    public const ALIAS = 'balikobot';

    private const CONNECTION_DEFAULT = 'default';

    protected string $extensionAlias = self::ALIAS;

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $root */
        $root = $definition->rootNode();

        $root
            ->children()
                ->scalarNode('api_user')
                    ->example('balikobot_test2cztest')
                    ->cannotBeEmpty()
                    ->setDeprecated('inspirum/balikobot-symfony', '1.1')
                ->end()
                ->scalarNode('api_key')
                    ->example('#lS1tBVo')
                    ->cannotBeEmpty()
                    ->setDeprecated('inspirum/balikobot-symfony', '1.1')
                ->end()
                ->scalarNode('default_connection')
                    ->example('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('api_user')
                                ->example('balikobot_test2cztest')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('api_key')
                                ->example('#lS1tBVo')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{'api_user'?: string, 'api_key'?: string, 'default_connection'?: string, 'connections'?: array<string,array{'api_user': string, 'api_key': string}>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $connections = $this->resolveConnections($config);
        $connectionsNames = array_keys($connections);
        $defaultConnectionName = $this->resolveDefaultConnectionName($connections, $config['default_connection'] ?? self::CONNECTION_DEFAULT);

        $this->registerClients($services, $connections, $defaultConnectionName);
        $this->registerFactories($services);
        $this->registerProviders($services);
        $this->registerServices($services, $connectionsNames, $defaultConnectionName);
        $this->registerServiceRegistry($services, $connectionsNames, $defaultConnectionName);
    }

    /**
     * @param array<string, array{'api_user': string, 'api_key': string}> $connections
     */
    private function registerClients(ServicesConfigurator $services, array $connections, string $defaultConnectionName): void
    {
        $services->set(Validator::class);

        foreach ($connections as $name => $connection) {
            $this->registerClientForConnection($services, $connection, $name, $name === $defaultConnectionName);
        }
    }

    /**
     * @param array{'api_user': string, 'api_key': string} $connection
     */
    private function registerClientForConnection(ServicesConfigurator $services, array $connection, string $name, bool $default): void
    {
        $defaultCurlRequesterServiceId = $this->serviceIdForConnection(DefaultCurlRequester::class, $name);
        $services->set($defaultCurlRequesterServiceId, DefaultCurlRequester::class)->args([
            '$apiUser' => $connection['api_user'],
            '$apiKey' => $connection['api_key'],
        ]);

        $requesterServiceId = $this->serviceIdForConnection(Requester::class, $name);
        $services->alias($requesterServiceId, $defaultCurlRequesterServiceId);

        $defaultClientServiceId = $this->serviceIdForConnection(DefaultClient::class, $name);
        $services->set($defaultClientServiceId, DefaultClient::class)->args([
            new Reference($requesterServiceId),
            new Reference(Validator::class),
        ]);

        $clientServiceId = $this->serviceIdForConnection(Client::class, $name);
        $services->alias($clientServiceId, $defaultClientServiceId);

        if ($default) {
            $services->alias(DefaultCurlRequester::class, $defaultCurlRequesterServiceId);
            $services->alias(Requester::class, $defaultCurlRequesterServiceId);
            $services->alias(DefaultClient::class, $defaultClientServiceId);
            $services->alias(Client::class, $defaultClientServiceId);
        }
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

    /**
     * @param array<string> $connectionsNames
     */
    private function registerServices(ServicesConfigurator $services, array $connectionsNames, string $defaultConnectionName): void
    {
        foreach ($connectionsNames as $name) {
            $this->registerServicesForConnection($services, $name, $name === $defaultConnectionName);
        }
    }

    private function registerServicesForConnection(ServicesConfigurator $services, string $name, bool $default): void
    {
        $clientServiceId = $this->serviceIdForConnection(Client::class, $name);

        $defaultBranchServiceServiceId = $this->serviceIdForConnection(DefaultBranchService::class, $name);
        $services->set($defaultBranchServiceServiceId, DefaultBranchService::class)->args([
            new Reference($clientServiceId),
            new Reference(BranchFactory::class),
            new Reference(BranchResolver::class),
            new Reference(CarrierProvider::class),
            new Reference(ServiceProvider::class),
        ]);

        $branchServiceServiceId = $this->serviceIdForConnection(BranchService::class, $name);
        $services->alias($branchServiceServiceId, $defaultBranchServiceServiceId);

        $defaultInfoServiceServiceId = $this->serviceIdForConnection(DefaultInfoService::class, $name);
        $services->set($defaultInfoServiceServiceId, DefaultInfoService::class)->args([
            new Reference($clientServiceId),
            new Reference(AccountFactory::class),
            new Reference(ChangelogFactory::class),
        ]);

        $infoServiceServiceId = $this->serviceIdForConnection(InfoService::class, $name);
        $services->alias($infoServiceServiceId, $defaultInfoServiceServiceId);

        $defaultPackageServiceServiceId = $this->serviceIdForConnection(DefaultPackageService::class, $name);
        $services->set($defaultPackageServiceServiceId, DefaultPackageService::class)->args([
            new Reference($clientServiceId),
            new Reference(PackageDataFactory::class),
            new Reference(PackageFactory::class),
            new Reference(OrderedShipmentFactory::class),
            new Reference(LabelFactory::class),
            new Reference(ProofOfDeliveryFactory::class),
            new Reference(TransportCostFactory::class),
        ]);

        $packageServiceServiceId = $this->serviceIdForConnection(PackageService::class, $name);
        $services->alias($packageServiceServiceId, $defaultPackageServiceServiceId);

        $defaultSettingServiceServiceId = $this->serviceIdForConnection(DefaultSettingService::class, $name);
        $services->set($defaultSettingServiceServiceId, DefaultSettingService::class)->args([
            new Reference($clientServiceId),
            new Reference(CarrierFactory::class),
            new Reference(ServiceFactory::class),
            new Reference(ManipulationUnitFactory::class),
            new Reference(CountryFactory::class),
            new Reference(ZipCodeFactory::class),
            new Reference(AdrUnitFactory::class),
            new Reference(AttributeFactory::class),
        ]);

        $settingServiceServiceId = $this->serviceIdForConnection(SettingService::class, $name);
        $services->alias($settingServiceServiceId, $defaultSettingServiceServiceId);

        $defaultTrackServiceServiceId = $this->serviceIdForConnection(DefaultTrackService::class, $name);
        $services->set($defaultTrackServiceServiceId, DefaultTrackService::class)->args([
            new Reference($clientServiceId),
            new Reference(StatusFactory::class),
        ]);

        $tackServiceServiceId = $this->serviceIdForConnection(TrackService::class, $name);
        $services->alias($tackServiceServiceId, $defaultTrackServiceServiceId);

        if ($default) {
            $services->alias(BranchService::class, $defaultBranchServiceServiceId);
            $services->alias(DefaultBranchService::class, $defaultBranchServiceServiceId);
            $services->alias(InfoService::class, $defaultInfoServiceServiceId);
            $services->alias(DefaultInfoService::class, $defaultInfoServiceServiceId);
            $services->alias(PackageService::class, $defaultPackageServiceServiceId);
            $services->alias(DefaultPackageService::class, $defaultPackageServiceServiceId);
            $services->alias(SettingService::class, $defaultSettingServiceServiceId);
            $services->alias(DefaultSettingService::class, $defaultSettingServiceServiceId);
            $services->alias(TrackService::class, $defaultTrackServiceServiceId);
            $services->alias(DefaultTrackService::class, $defaultTrackServiceServiceId);
        }
    }

    /**
     * @param array<string> $connectionsNames
     */
    private function registerServiceRegistry(ServicesConfigurator $services, array $connectionsNames, string $defaultConnectionName): void
    {
        $containers = [];

        foreach ($connectionsNames as $name) {
            $this->registerServiceContainerForConnection($services, $name, $name === $defaultConnectionName);

            $containers[$name] = new Reference($this->serviceIdForConnection(ServiceContainer::class, $name));
        }

        $services->set(DefaultServiceContainerRegistry::class)->args([
            $containers,
            $defaultConnectionName,
        ]);

        $services->alias(ServiceContainerRegistry::class, DefaultServiceContainerRegistry::class);
    }

    private function registerServiceContainerForConnection(ServicesConfigurator $services, string $name, bool $default): void
    {
        $defaultServiceContainerServiceId = $this->serviceIdForConnection(DefaultServiceContainer::class, $name);
        $services->set($defaultServiceContainerServiceId, DefaultServiceContainer::class)->args([
            new Reference($this->serviceIdForConnection(BranchService::class, $name)),
            new Reference($this->serviceIdForConnection(InfoService::class, $name)),
            new Reference($this->serviceIdForConnection(PackageService::class, $name)),
            new Reference($this->serviceIdForConnection(SettingService::class, $name)),
            new Reference($this->serviceIdForConnection(TrackService::class, $name)),
        ]);

        $serviceContainerServiceId = $this->serviceIdForConnection(ServiceContainer::class, $name);
        $services->alias($serviceContainerServiceId, $defaultServiceContainerServiceId);

        if ($default) {
            $services->alias(DefaultServiceContainer::class, $defaultServiceContainerServiceId);
            $services->alias(ServiceContainer::class, $defaultServiceContainerServiceId);
        }
    }

    /**
     * @param array<string,array{'api_user': string, 'api_key': string}> $connections
     */
    private function resolveDefaultConnectionName(array $connections, string $defaultName): string
    {
        if (array_key_exists($defaultName, $connections)) {
            return $defaultName;
        }

        return array_key_first($connections) ?? self::CONNECTION_DEFAULT;
    }

    /**
     * @param array{'api_user'?: string, 'api_key'?: string, 'connections'?: array<string,array{'api_user': string, 'api_key': string}>} $config
     *
     * @return non-empty-array<string,array{'api_user': string, 'api_key': string}>
     */
    private function resolveConnections(array $config): array
    {
        $connections = $config['connections'] ?? [];

        if (count($connections) === 0) {
            $connections[self::CONNECTION_DEFAULT] = [
                'api_user' => $config['api_user'] ?? throw new RuntimeException('Missing "api_user" configuration'),
                'api_key' => $config['api_key'] ?? throw new RuntimeException('Missing "api_key" configuration'),
            ];
        }

        return $connections;
    }

    private function serviceIdForConnection(string $class, string $name): string
    {
        return sprintf('%s.%s', $class, $name);
    }
}
