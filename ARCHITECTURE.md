# DoctrineMigrationsBundle Architecture

## Purpose

A Symfony bundle that integrates the Doctrine Migrations library, wiring it into
the DI container, the web profiler, and the Symfony console.

## Directory Structure

```
src/
  Doctrine_Migrations_Bundle.php                        — bundle entry point
  DependencyInjection/
    Configuration.php                                    — defines `doctrine_migrations` config tree
    Doctrine_Migrations_Extension.php                   — loads/processes configuration
    CompilerPass/
      Configure_Dependency_Factory_Pass.php             — wires the DependencyFactory with DI services
      Register_Migrations_Pass.php                      — registers migration classes found via DI tags
  Collector/
    Migrations_Collector.php                            — collects migration status for the web profiler
    Migrations_Flattener.php                            — flattens migration lists from multiple EMs
  EventListener/
    Schema_Filter_Listener.php                          — prevents migration tables appearing in schema diff
  MigrationsRepository/
    Service_Migrations_Repository.php                   — loads migrations from the DI container
config/
  services.php                                          — service definitions
```

## Key Design Decisions

- **`DependencyFactory` wiring**: the Doctrine Migrations `DependencyFactory`
  is constructed by the `Configure_Dependency_Factory_Pass`, injecting Symfony
  services (entity managers, loggers, connection) without coupling migrations
  to the Symfony container directly.
- **Service-based migrations**: migrations can be registered as Symfony services
  (tagged with `doctrine_migrations.migration`), enabling constructor injection
  of services — `Service_Migrations_Repository` collects these at runtime.
- **Schema filter**: `Schema_Filter_Listener` subscribes to the Doctrine
  `postGenerateSchema` event to hide migration version tables from `doctrine:schema:*`
  commands, preventing false-positive diffs.
- **Profiler integration**: `Migrations_Collector` gathers migration status per
  entity manager and exposes it as a profiler data collector panel.

## Extension Points

- Use service-tagged migrations (constructor injection) via the
  `doctrine_migrations.migration` tag.
- Configure multiple entity managers via the `doctrine_migrations.em` config key.

## Dependency Flow

```
Symfony Kernel
  └── DoctrineMigrationsBundle
        ├── DoctrineMigrationsExtension  → DependencyFactory (per EM)
        ├── ServiceMigrationsRepository  → tagged migration services
        ├── MigrationsCollector          → Symfony Profiler
        └── Console commands             → doctrine/migrations library
```
