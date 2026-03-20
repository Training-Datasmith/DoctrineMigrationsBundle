<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Dependency_Injection\Compiler_Pass;

use function array_keys;
use function assert;
use function count;
use Doctrine\Migrations\Dependency_Factory;
use function implode;
use InvalidArgumentException;
use function is_array;
use function is_string;
use RuntimeException;
use function sprintf;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/** @internal */
final class Configure_Dependency_Factory_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has('doctrine')) {
            throw new RuntimeException('DoctrineMigrationsBundle requires DoctrineBundle to be enabled.');
        }
        $di_definition = $container->get_definition('doctrine.migrations.dependency_factory');
        $preferred_connection = $container->get_parameter('doctrine.migrations.preferred_connection');
        assert(is_string($preferred_connection) || $preferred_connection === null);
        // explicitly use configured connection
        if ($preferred_connection !== null) {
            $this->validate_preferred_connection($container, $preferred_connection);
            $loader_definition = $container->get_definition('doctrine.migrations.connection_registry_loader');
            $loader_definition->set_argument(1, $preferred_connection);
            $di_definition->set_factory([Dependency_Factory::class, 'fromConnection']);
            $di_definition->set_argument(1, new Reference('doctrine.migrations.connection_registry_loader'));
            return;
        }
        $preferred_em = $container->get_parameter('doctrine.migrations.preferred_em');
        assert(is_string($preferred_em) || $preferred_em === null);
        // explicitly use configured entity manager
        if ($preferred_em !== null) {
            $this->validate_preferred_em($container, $preferred_em);
            $loader_definition = $container->get_definition('doctrine.migrations.entity_manager_registry_loader');
            $loader_definition->set_argument(1, $preferred_em);
            $di_definition->set_factory([Dependency_Factory::class, 'fromEntityManager']);
            $di_definition->set_argument(1, new Reference('doctrine.migrations.entity_manager_registry_loader'));
            return;
        }
        // try to use any/default entity manager
        if ($container->has_parameter('doctrine.entity_managers') && is_array($container->get_parameter('doctrine.entity_managers')) && count($container->get_parameter('doctrine.entity_managers')) > 0) {
            $di_definition->set_factory([Dependency_Factory::class, 'fromEntityManager']);
            $di_definition->set_argument(1, new Reference('doctrine.migrations.entity_manager_registry_loader'));
            return;
        }
        // fallback on any/default connection
        $di_definition->set_factory([Dependency_Factory::class, 'fromConnection']);
        $di_definition->set_argument(1, new Reference('doctrine.migrations.connection_registry_loader'));
    }
    private function validate_preferred_connection(Container_Builder $container, string $preferred_connection): void
    {
        /** @var array<string, string> $allowedConnections */
        $allowed_connections = $container->get_parameter('doctrine.connections');
        if (!isset($allowed_connections[$preferred_connection])) {
            throw new InvalidArgumentException(sprintf('The "%s" connection is not defined. Did you mean one of the following: %s', $preferred_connection, implode(', ', array_keys($allowed_connections))));
        }
    }
    private function validate_preferred_em(Container_Builder $container, string $preferred_em): void
    {
        if (!$container->has_parameter('doctrine.entity_managers') || !is_array($container->get_parameter('doctrine.entity_managers')) || count($container->get_parameter('doctrine.entity_managers')) === 0) {
            throw new InvalidArgumentException(sprintf('The "%s" entity manager is not defined. It seems that you do not have configured any entity manager in the DoctrineBundle.', $preferred_em));
        }
        /** @var array<string, string> $allowedEms */
        $allowed_ems = $container->get_parameter('doctrine.entity_managers');
        if (!isset($allowed_ems[$preferred_em])) {
            throw new InvalidArgumentException(sprintf('The "%s" entity manager is not defined. Did you mean one of the following: %s', $preferred_em, implode(', ', array_keys($allowed_ems))));
        }
    }
}