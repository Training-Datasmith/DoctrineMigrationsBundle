<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Dependency_Injection;

use function array_filter;
use function array_keys;
use function constant;
use function count;
use function in_array;
use function is_string;
use ReflectionClass;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
/** @internal */
final class Configuration implements Configuration_Interface
{
    /** @return TreeBuilder<'array'> */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder('doctrine_migrations');
        $root_node = $tree_builder->get_root_node();
        $organize_migration_modes = $this->get_organize_migrations_modes();
        $root_node->fix_xml_config('migration', 'migrations')->fix_xml_config('migrations_path', 'migrations_paths')->children()->boolean_node('enable_service_migrations')->info('Whether to enable fetching migrations from the service container.')->default_false()->end()->array_node('migrations_paths')->info('A list of namespace/path pairs where to look for migrations.')->default_value([])->use_attribute_as_key('namespace')->prototype('scalar')->end()->end()->array_node('services')->info('A set of services to pass to the underlying doctrine/migrations library, allowing to change its behaviour.')->use_attribute_as_key('service')->default_value([])->validate()->if_true(static fn(array $v): bool => count(array_filter(array_keys($v), static fn(string $doctrine_service): bool => !str_starts_with($doctrine_service, 'Doctrine\Migrations\\'))) !== 0)->then_invalid('Valid services for the DoctrineMigrationsBundle must be in the "Doctrine\Migrations" namespace.')->end()->prototype('scalar')->end()->end()->array_node('factories')->info('A set of callables to pass to the underlying doctrine/migrations library as services, allowing to change its behaviour.')->use_attribute_as_key('factory')->default_value([])->validate()->if_true(static fn(array $v): bool => count(array_filter(array_keys($v), static fn(string $doctrine_service): bool => !str_starts_with($doctrine_service, 'Doctrine\Migrations\\'))) !== 0)->then_invalid('Valid callables for the DoctrineMigrationsBundle must be in the "Doctrine\Migrations" namespace.')->end()->prototype('scalar')->end()->end()->array_node('storage')->add_defaults_if_not_set()->info('Storage to use for migration status metadata.')->children()->array_node('table_storage')->add_defaults_if_not_set()->info('The default metadata storage, implemented as a table in the database.')->children()->scalar_node('table_name')->default_value(null)->cannot_be_empty()->end()->scalar_node('version_column_name')->default_value(null)->end()->scalar_node('version_column_length')->default_value(null)->end()->scalar_node('executed_at_column_name')->default_value(null)->end()->scalar_node('execution_time_column_name')->default_value(null)->end()->end()->end()->end()->end()->array_node('migrations')->info('A list of migrations to load in addition to the one discovered via "migrations_paths".')->prototype('scalar')->end()->default_value([])->end()->scalar_node('connection')->info('Connection name to use for the migrations database.')->default_value(null)->end()->scalar_node('em')->info('Entity manager name to use for the migrations database (available when doctrine/orm is installed).')->default_value(null)->end()->scalar_node('all_or_nothing')->info('Run all migrations in a transaction.')->default_value(false)->end()->scalar_node('check_database_platform')->info('Adds an extra check in the generated migrations to allow execution only on the same platform as they were initially generated on.')->default_value(true)->end()->scalar_node('custom_template')->info('Custom template path for generated migration classes.')->default_value(null)->end()->scalar_node('organize_migrations')->default_value(false)->info('Organize migrations mode. Possible values are: "BY_YEAR", "BY_YEAR_AND_MONTH", false')->validate()->if_true(static function ($v) use ($organize_migration_modes): bool {
            if ($v === false) {
                return false;
            }
            return !is_string($v) || !in_array(strtoupper($v), $organize_migration_modes, true);
        })->then_invalid('Invalid organize migrations mode value %s')->end()->validate()->if_string()->then(static fn(string $v): string => constant('Doctrine\Migrations\Configuration\Configuration::VERSIONS_ORGANIZATION_' . strtoupper($v)))->end()->end()->boolean_node('enable_profiler')->info('Whether or not to enable the profiler collector to calculate and visualize migration status. This adds some queries overhead.')->default_false()->end()->boolean_node('transactional')->info('Whether or not to wrap migrations in a single transaction.')->default_true()->end()->end();
        return $tree_builder;
    }
    /**
     * Find organize migrations modes for their names
     *
     * @return string[]
     */
    private function get_organize_migrations_modes(): array
    {
        $const_prefix = 'VERSIONS_ORGANIZATION_';
        $prefix_len = strlen($const_prefix);
        $ref_class = new ReflectionClass('Doctrine\Migrations\Configuration\Configuration');
        $consts_array = array_keys($ref_class->get_constants());
        $names_array = [];
        foreach ($consts_array as $constant) {
            if (!str_starts_with($constant, $const_prefix)) {
                continue;
            }
            $names_array[] = substr($constant, $prefix_len);
        }
        return $names_array;
    }
}