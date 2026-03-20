<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Collector;

use function array_map;
use DateTimeImmutable;
use Doctrine\Migrations\Metadata\Available_Migration;
use Doctrine\Migrations\Metadata\Available_Migrations_List;
use Doctrine\Migrations\Metadata\Executed_Migration;
use Doctrine\Migrations\Metadata\Executed_Migrations_List;
use ReflectionClass;
/** @internal */
final class Migrations_Flattener
{
    /**
     * @return array{
     *    version: string,
     *    is_new: true,
     *    is_unavailable: bool,
     *    description: string,
     *    executed_at: null,
     *    execution_time: null,
     *    file: string|false,
     * }[]
     */
    public function flatten_available_migrations(Available_Migrations_List $migrations_list): array
    {
        return array_map(static fn(Available_Migration $migration): array => ['version' => (string) $migration->get_version(), 'is_new' => true, 'is_unavailable' => false, 'description' => $migration->get_migration()->get_description(), 'executed_at' => null, 'execution_time' => null, 'file' => (new ReflectionClass($migration->get_migration()))->get_file_name()], $migrations_list->get_items());
    }
    /**
     * @return array{
     *    version: string,
     *    is_new: false,
     *    is_unavailable: bool,
     *    description: string|null,
     *    executed_at: DateTimeImmutable|null,
     *    execution_time: float|null,
     *    file: string|false|null,
     * }[]
     */
    public function flatten_executed_migrations(Executed_Migrations_List $migrations_list, Available_Migrations_List $available_migrations): array
    {
        return array_map(static function (Executed_Migration $migration) use ($available_migrations): array {
            $available_migration = $available_migrations->has_migration($migration->get_version()) ? $available_migrations->get_migration($migration->get_version())->get_migration() : null;
            return ['version' => (string) $migration->get_version(), 'is_new' => false, 'is_unavailable' => $available_migration === null, 'description' => $available_migration?->get_description(), 'executed_at' => $migration->get_executed_at(), 'execution_time' => $migration->get_execution_time(), 'file' => $available_migration !== null ? (new ReflectionClass($available_migration))->get_file_name() : null];
        }, $migrations_list->get_items());
    }
}