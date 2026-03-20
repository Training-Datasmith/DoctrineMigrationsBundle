<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle;

use function dirname;
use Doctrine\Bundle\Migrations_Bundle\Dependency_Injection\Compiler_Pass\Configure_Dependency_Factory_Pass;
use Doctrine\Bundle\Migrations_Bundle\Dependency_Injection\Compiler_Pass\Register_Migrations_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
final class Doctrine_Migrations_Bundle extends Bundle
{
    public function build(Container_Builder $container): void
    {
        $container->add_compiler_pass(new Configure_Dependency_Factory_Pass());
        $container->add_compiler_pass(new Register_Migrations_Pass());
    }
    public function get_path(): string
    {
        return dirname(__DIR__);
    }
}