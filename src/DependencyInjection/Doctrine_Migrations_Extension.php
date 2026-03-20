<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Dependency_Injection;

use function array_keys;
use function assert;
use Doctrine\Bundle\Migrations_Bundle\Collector\Migrations_Collector;
use Doctrine\Bundle\Migrations_Bundle\Collector\Migrations_Flattener;
use Doctrine\Migrations\Abstract_Migration;
use Doctrine\Migrations\Metadata\Storage\Metadata_Storage;
use Doctrine\Migrations\Metadata\Storage\Table_Metadata_Storage_Configuration;
use Doctrine\Migrations\Migrations_Repository;
use Doctrine\Migrations\Version\Migration_Factory;
use function explode;
use function implode;
use InvalidArgumentException;
use function is_array;
use RuntimeException;
use function sprintf;
use function strlen;
use function substr;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
/** @internal */
final class Doctrine_Migrations_Extension extends Extension
{
    /**
     * Responds to the migrations configuration parameter.
     *
     * {@inheritDoc}
     */
    public function load(array $configs, Container_Builder $container): void
    {
        $configuration = new Configuration();
        $config = $this->process_configuration($configuration, $configs);
        $locator = new File_Locator(__DIR__ . '/../../config/');
        $loader = new Php_File_Loader($container, $locator);
        $loader->load('services.php');
        if ($config['enable_service_migrations']) {
            $container->register_for_autoconfiguration(Abstract_Migration::class)->add_tag('doctrine_migrations.migration');
            if (!isset($config['services'][Migrations_Repository::class])) {
                $config['services'][Migrations_Repository::class] = 'doctrine.migrations.service_migrations_repository';
            }
        } else {
            $container->remove_definition('doctrine.migrations.service_migrations_repository');
            $container->remove_definition('doctrine.migrations.connection');
            $container->remove_definition('doctrine.migrations.logger');
        }
        $configuration_definition = $container->get_definition('doctrine.migrations.configuration');
        foreach ($config['migrations_paths'] as $ns => $path) {
            $path = $this->check_if_bundle_relative_path($path, $container);
            $configuration_definition->add_method_call('addMigrationsDirectory', [$ns, $path]);
        }
        foreach ($config['migrations'] as $migration_class) {
            $configuration_definition->add_method_call('addMigrationClass', [$migration_class]);
        }
        if ($config['organize_migrations'] !== false) {
            $configuration_definition->add_method_call('setMigrationOrganization', [$config['organize_migrations']]);
        }
        if ($config['custom_template'] !== null) {
            $configuration_definition->add_method_call('setCustomTemplate', [$config['custom_template']]);
        }
        $configuration_definition->add_method_call('setAllOrNothing', [$config['all_or_nothing']]);
        $configuration_definition->add_method_call('setCheckDatabasePlatform', [$config['check_database_platform']]);
        if ($config['enable_profiler']) {
            $this->register_collector($container);
        }
        $configuration_definition->add_method_call('setTransactional', [$config['transactional']]);
        $di_definition = $container->get_definition('doctrine.migrations.dependency_factory');
        if (!isset($config['services'][Migration_Factory::class])) {
            $config['services'][Migration_Factory::class] = 'doctrine.migrations.migrations_factory';
        }
        foreach ($config['services'] as $doctrine_id => $symfony_id) {
            $di_definition->add_method_call('setDefinition', [$doctrine_id, new Service_Closure_Argument(new Reference($symfony_id))]);
        }
        foreach ($config['factories'] as $doctrine_id => $symfony_id) {
            $di_definition->add_method_call('setDefinition', [$doctrine_id, new Reference($symfony_id)]);
        }
        if (isset($config['services'][Metadata_Storage::class])) {
            $container->remove_definition('doctrine_migrations.schema_filter_listener');
        } else {
            $filter_definition = $container->get_definition('doctrine_migrations.schema_filter_listener');
            $storage_configuration = $config['storage']['table_storage'];
            $storage_definition = new Definition(Table_Metadata_Storage_Configuration::class);
            $container->set_definition('doctrine.migrations.storage.table_storage', $storage_definition);
            $container->set_alias('doctrine.migrations.metadata_storage', 'doctrine.migrations.storage.table_storage');
            if ($storage_configuration['table_name'] === null) {
                $filter_definition->add_argument('doctrine_migration_versions');
            } else {
                $storage_definition->add_method_call('setTableName', [$storage_configuration['table_name']]);
                $filter_definition->add_argument($storage_configuration['table_name']);
            }
            if ($storage_configuration['version_column_name'] !== null) {
                $storage_definition->add_method_call('setVersionColumnName', [$storage_configuration['version_column_name']]);
            }
            if ($storage_configuration['version_column_length'] !== null) {
                $storage_definition->add_method_call('setVersionColumnLength', [$storage_configuration['version_column_length']]);
            }
            if ($storage_configuration['executed_at_column_name'] !== null) {
                $storage_definition->add_method_call('setExecutedAtColumnName', [$storage_configuration['executed_at_column_name']]);
            }
            if ($storage_configuration['execution_time_column_name'] !== null) {
                $storage_definition->add_method_call('setExecutionTimeColumnName', [$storage_configuration['execution_time_column_name']]);
            }
            $configuration_definition->add_method_call('setMetadataStorageConfiguration', [new Reference('doctrine.migrations.storage.table_storage')]);
            // Add tag to the filter for each Doctrine connection, so the table is ignored for multiple connections
            if ($container->has_parameter('doctrine.connections')) {
                /** @var array<string, string> $connections */
                $connections = $container->get_parameter('doctrine.connections');
                foreach (array_keys($connections) as $connection) {
                    $filter_definition->add_tag('doctrine.dbal.schema_filter', ['connection' => $connection]);
                }
            }
        }
        if ($config['em'] !== null && $config['connection'] !== null) {
            throw new InvalidArgumentException('You cannot specify both "connection" and "em" in the DoctrineMigrationsBundle configurations.');
        }
        $container->set_parameter('doctrine.migrations.preferred_em', $config['em']);
        $container->set_parameter('doctrine.migrations.preferred_connection', $config['connection']);
    }
    private function check_if_bundle_relative_path(string $path, Container_Builder $container): string
    {
        if (isset($path[0]) && $path[0] === '@') {
            $path_parts = explode('/', $path);
            $bundle_name = substr($path_parts[0], 1);
            $bundle_path = $this->get_bundle_path($bundle_name, $container);
            return $bundle_path . substr($path, strlen('@' . $bundle_name));
        }
        return $path;
    }
    private function get_bundle_path(string $bundle_name, Container_Builder $container): string
    {
        $bundle_metadata = $container->get_parameter('kernel.bundles_metadata');
        assert(is_array($bundle_metadata));
        if (!isset($bundle_metadata[$bundle_name])) {
            throw new RuntimeException(sprintf('The bundle "%s" has not been registered, available bundles: %s', $bundle_name, implode(', ', array_keys($bundle_metadata))));
        }
        return $bundle_metadata[$bundle_name]['path'];
    }
    private function register_collector(Container_Builder $container): void
    {
        $flattener_definition = new Definition(Migrations_Flattener::class);
        $container->set_definition('doctrine_migrations.migrations_flattener', $flattener_definition);
        $collector_definition = new Definition(Migrations_Collector::class, [new Reference('doctrine.migrations.dependency_factory'), new Reference('doctrine_migrations.migrations_flattener')]);
        $collector_definition->add_tag('data_collector', ['template' => '@DoctrineMigrations/Collector/migrations.html.twig', 'id' => 'doctrine_migrations', 'priority' => '249']);
        $container->set_definition('doctrine_migrations.migrations_collector', $collector_definition);
    }
    public function get_xsd_validation_base_path(): string
    {
        return __DIR__ . '/../../config/schema';
    }
    public function get_namespace(): string
    {
        return 'http://symfony.com/schema/dic/doctrine/migrations/3.0';
    }
}