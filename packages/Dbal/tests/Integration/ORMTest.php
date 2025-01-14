<?php

declare(strict_types=1);

namespace Test\Ecotone\Dbal\Integration;

use Ecotone\Dbal\Configuration\DbalConfiguration;
use Ecotone\Dbal\DbalBackedMessageChannelBuilder;
use Ecotone\Dbal\DbalConnection;
use Ecotone\Lite\EcotoneLite;
use Ecotone\Lite\Test\FlowTestSupport;
use Ecotone\Messaging\Config\ModulePackageList;
use Ecotone\Messaging\Config\ServiceConfiguration;
use Ecotone\Messaging\Endpoint\ExecutionPollingMetadata;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Enqueue\Dbal\DbalConnectionFactory;
use Test\Ecotone\Dbal\DbalMessagingTestCase;
use Test\Ecotone\Dbal\Fixture\ORM\FailureMode\MultipleInternalCommandsService;
use Test\Ecotone\Dbal\Fixture\ORM\Person\Person;
use Test\Ecotone\Dbal\Fixture\ORM\Person\RegisterPerson;
use Test\Ecotone\Dbal\Fixture\ORM\PersonRepository\ORMPersonRepository;
use Test\Ecotone\Dbal\Fixture\ORM\PersonRepository\RegisterPersonService;
use Test\Ecotone\Dbal\Fixture\ORM\SynchronousEventHandler\SaveMultipleEntitiesHandler;

/**
 * @internal
 */
final class ORMTest extends DbalMessagingTestCase
{
    public function test_support_for_orm(): void
    {
        $ecotone = $this->bootstrapEcotone();

        $ecotone->sendCommand(new RegisterPerson(100, 'Johny'));

        self::assertEquals(
            'Johny',
            $ecotone->sendQueryWithRouting('person.getName', metadata: ['aggregate.id' => 100])
        );
    }

    public function test_flushing_object_manager_on_command_bus()
    {
        $this->setupUserTable();
        $connectionFactory = $this->getORMConnectionFactory([__DIR__ . '/../Fixture/ORM/Person']);
        $ORMPersonRepository = new ORMPersonRepository($connectionFactory->getRegistry());

        $ecotone = EcotoneLite::bootstrapFlowTesting(
            containerOrAvailableServices: [
                DbalConnectionFactory::class => $connectionFactory,
                ORMPersonRepository::class => $ORMPersonRepository,
                RegisterPersonService::class => new RegisterPersonService(),
                SaveMultipleEntitiesHandler::class => new SaveMultipleEntitiesHandler(),
            ],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE]))
                ->withNamespaces([
                    'Test\Ecotone\Dbal\Fixture\ORM\PersonRepository',
                    'Test\Ecotone\Dbal\Fixture\ORM\SynchronousEventHandler',
                ]),
            pathToRootCatalog: __DIR__ . '/../../',
            addInMemoryStateStoredRepository: false
        );

        $ecotone->sendCommand(new RegisterPerson(100, 'Johnny'));
        $ecotone->sendCommandWithRoutingKey(Person::RENAME_COMMAND, 'Paul', metadata: ['aggregate.id' => 100]);

        self::assertEquals(
            'Paul',
            $ORMPersonRepository->get(100)->getName()
        );
        self::assertEquals(
            'Paul2',
            $ORMPersonRepository->get(101)->getName()
        );
    }

    public function test_disabling_flushing_object_manager_on_command_bus()
    {
        $this->setupUserTable();
        $connectionFactory = $this->getORMConnectionFactory([__DIR__ . '/../Fixture/ORM/Person']);
        $entityManager = $connectionFactory->getRegistry()->getManager();
        $ORMPersonRepository = new ORMPersonRepository($connectionFactory->getRegistry());

        $ecotone = EcotoneLite::bootstrapFlowTesting(
            containerOrAvailableServices: [
                DbalConnectionFactory::class => $connectionFactory,
                ORMPersonRepository::class => $ORMPersonRepository,
                RegisterPersonService::class => new RegisterPersonService(),
            ],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE]))
                ->withNamespaces([
                    'Test\Ecotone\Dbal\Fixture\ORM\PersonRepository',
                ])
                ->withExtensionObjects([
                    DbalConfiguration::createWithDefaults()
                        ->withClearAndFlushObjectManagerOnCommandBus(false),
                ]),
            pathToRootCatalog: __DIR__ . '/../../',
            addInMemoryStateStoredRepository: false
        );

        $ecotone->sendCommand(new RegisterPerson(100, 'Johnny'));
        $entityManager->clear();

        $this->expectException(InvalidArgumentException::class);

        $ORMPersonRepository->get(100);
    }

    public function test_object_manager_reconnects_on_command_bus()
    {
        $this->setupUserTable();
        $connectionFactory = $this->getORMConnectionFactory([__DIR__ . '/../Fixture/ORM/Person']);
        $entityManager = $connectionFactory->getRegistry()->getManager();
        $ORMPersonRepository = new ORMPersonRepository($connectionFactory->getRegistry());

        $ecotone = EcotoneLite::bootstrapFlowTesting(
            containerOrAvailableServices: [
                DbalConnectionFactory::class => $connectionFactory,
                ORMPersonRepository::class => $ORMPersonRepository,
                RegisterPersonService::class => new RegisterPersonService(),
            ],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE]))
                ->withNamespaces([
                    'Test\Ecotone\Dbal\Fixture\ORM\PersonRepository',
                ]),
            pathToRootCatalog: __DIR__ . '/../../',
            addInMemoryStateStoredRepository: false
        );

        $entityManager->close();
        $ecotone->sendCommand(new RegisterPerson(100, 'Johnny'));

        $this->assertNotNull(
            $ORMPersonRepository->get(100)
        );
    }

    private function bootstrapEcotone(): FlowTestSupport
    {
        $this->setupUserTable();

        return EcotoneLite::bootstrapFlowTesting(
            containerOrAvailableServices: [DbalConnectionFactory::class => $this->getORMConnectionFactory([__DIR__.'/../Fixture/ORM/Person'])],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withEnvironment('prod')
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE]))
                ->withNamespaces([
                    'Test\Ecotone\Dbal\Fixture\ORM\Person',
                ])
                ->withExtensionObjects([
                    DbalConfiguration::createWithDefaults()
                        ->withDoctrineORMRepositories(true, [Person::class]),
                ]),
            pathToRootCatalog: __DIR__ . '/../../',
            addInMemoryStateStoredRepository: false
        );
    }

    public function test_throwing_exception_when_setting_up_doctrine_orm_using_non_orm_registry_based_connection()
    {
        $ecotoneLite = EcotoneLite::bootstrapFlowTesting(
            [Person::class, MultipleInternalCommandsService::class],
            [new MultipleInternalCommandsService(), DbalConnectionFactory::class => DbalConnection::create($this->getConnection())],
            ServiceConfiguration::createWithDefaults()
                ->withExtensionObjects([
                    DbalConfiguration::createWithDefaults()
                        ->withDoctrineORMRepositories(true),
                    DbalBackedMessageChannelBuilder::create('async'),
                ])
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::ASYNCHRONOUS_PACKAGE, ModulePackageList::DBAL_PACKAGE])),
            addInMemoryStateStoredRepository: false
        );

        $this->expectException(InvalidArgumentException::class);

        $ecotoneLite->sendCommandWithRoutingKey('multipleInternalCommands', [['personId' => 99, 'personName' => 'Johny', 'exception' => false]]);
        $ecotoneLite->run('async', ExecutionPollingMetadata::createWithTestingSetup(amountOfMessagesToHandle: 2, failAtError: true));
    }
}
