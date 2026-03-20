<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Dependency_Injection\Compiler_Pass;

use Doctrine\DBAL\Connection;
use Psr\Log\Logger_Interface;
use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
/** @internal */
final class Register_Migrations_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('doctrine.migrations.service_migrations_repository')) {
            return;
        }
        $migration_refs = [];
        foreach ($container->find_tagged_service_ids('doctrine_migrations.migration', true) as $id => $attributes) {
            $definition = $container->get_definition($id);
            $definition->set_bindings([Connection::class => new Bound_Argument(new Reference('doctrine.migrations.connection'), false), Logger_Interface::class => new Bound_Argument(new Reference('doctrine.migrations.logger'), false)]);
            $migration_refs[$id] = new Typed_Reference($id, $definition->get_class());
        }
        $container->get_definition('doctrine.migrations.service_migrations_repository')->replace_argument(0, new Service_Locator_Argument($migration_refs));
    }
}