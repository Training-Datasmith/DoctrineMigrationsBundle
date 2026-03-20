<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Collector;

use function count;
use Doctrine\DBAL\Exception;
use Doctrine\Migrations\Dependency_Factory;
use Doctrine\Migrations\Metadata\Storage\Table_Metadata_Storage_Configuration;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Throwable;
/** @internal */
final class Migrations_Collector extends Data_Collector
{
    public function __construct(private readonly Dependency_Factory $dependency_factory, private readonly Migrations_Flattener $flattener)
    {
    }
    public function collect(Request $request, Response $response, Throwable|null $exception = null): void
    {
        if ($this->data !== []) {
            return;
        }
        $metadata_storage = $this->dependency_factory->get_metadata_storage();
        $plan_calculator = $this->dependency_factory->get_migration_plan_calculator();
        try {
            $executed_migrations = $metadata_storage->get_executed_migrations();
        } catch (Exception $dbal_exception) {
            $this->dependency_factory->get_logger()->error('error while trying to collect executed migrations', ['exception' => $dbal_exception]);
            return;
        }
        $available_migrations = $plan_calculator->get_migrations();
        $this->data['available_migrations_count'] = count($available_migrations);
        $unavailable_migrations = $executed_migrations->unavailable_subset($available_migrations);
        $this->data['unavailable_migrations_count'] = count($unavailable_migrations);
        $new_migrations = $available_migrations->new_subset($executed_migrations);
        $this->data['new_migrations'] = $this->flattener->flatten_available_migrations($new_migrations);
        $this->data['executed_migrations'] = $this->flattener->flatten_executed_migrations($executed_migrations, $available_migrations);
        $this->data['storage'] = $metadata_storage::class;
        $configuration = $this->dependency_factory->get_configuration();
        $storage = $configuration->get_metadata_storage_configuration();
        if ($storage instanceof Table_Metadata_Storage_Configuration) {
            $this->data['table'] = $storage->get_table_name();
            $this->data['column'] = $storage->get_version_column_name();
        }
        $connection = $this->dependency_factory->get_connection();
        $this->data['driver'] = $connection->get_driver()::class;
        $this->data['name'] = $connection->get_database();
        $this->data['namespaces'] = $configuration->get_migration_directories();
    }
    public function get_name(): string
    {
        return 'doctrine_migrations';
    }
    /** @return array<string, mixed>|Data */
    public function get_data(): array|Data
    {
        return $this->data;
    }
    public function reset(): void
    {
        $this->data = [];
    }
}