<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Migrations_Repository;

use Doctrine\Migrations\Abstract_Migration;
use Doctrine\Migrations\Exception\Migration_Class_Not_Found;
use Doctrine\Migrations\Metadata\Available_Migration;
use Doctrine\Migrations\Metadata\Available_Migrations_Set;
use Doctrine\Migrations\Migrations_Repository;
use Doctrine\Migrations\Version\Version;
use Symfony\Contracts\Service\Service_Provider_Interface;
/** @internal */
final class Service_Migrations_Repository implements Migrations_Repository
{
    /** @var array<string, AvailableMigration> */
    private array $migrations = [];
    /** @param ServiceProviderInterface<AbstractMigration> $container */
    public function __construct(private readonly Service_Provider_Interface $container)
    {
    }
    public function has_migration(string $version): bool
    {
        return isset($this->migrations[$version]) || $this->container->has($version);
    }
    public function get_migration(Version $version): Available_Migration
    {
        $this->load_migration_from_container($version);
        return $this->migrations[(string) $version];
    }
    /**
     * Returns a non-sorted set of migrations.
     */
    public function get_migrations(): Available_Migrations_Set
    {
        foreach ($this->container->get_provided_services() as $id) {
            $this->load_migration_from_container(new Version($id));
        }
        return new Available_Migrations_Set($this->migrations);
    }
    private function load_migration_from_container(Version $version): void
    {
        $id = (string) $version;
        if (isset($this->migrations[$id])) {
            return;
        }
        if (!$this->container->has($id)) {
            throw Migration_Class_Not_Found::new($id);
        }
        $this->migrations[$id] = new Available_Migration($version, $this->container->get($id));
    }
}