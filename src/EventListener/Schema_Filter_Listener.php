<?php

declare (strict_types=1);
namespace Doctrine\Bundle\Migrations_Bundle\Event_Listener;

use Doctrine\DBAL\Schema\Abstract_Asset;
use Doctrine\DBAL\Schema\Abstract_Named_Object;
use Doctrine\DBAL\Schema\Name\Optionally_Qualified_Name;
use Doctrine\ORM\Tools\Console\Command\Schema_Tool\Update_Command;
use Doctrine\ORM\Tools\Console\Command\Validate_Schema_Command;
use Symfony\Component\Console\Event\Console_Command_Event;
/**
 * Acts as a schema filter that hides the migration metadata table except
 * when the execution context is that of command inside the migrations
 * namespace.
 *
 * @internal
 */
final class Schema_Filter_Listener
{
    public function __construct(private readonly string $configuration_table_name)
    {
    }
    private bool $enabled = false;
    /** @param AbstractAsset<OptionallyQualifiedName>|AbstractNamedObject<OptionallyQualifiedName>|string $asset */
    public function __invoke(Abstract_Asset|Abstract_Named_Object|string $asset): bool
    {
        if (!$this->enabled) {
            return true;
        }
        if ($asset instanceof Abstract_Asset) {
            $asset = $asset instanceof Abstract_Named_Object ? $asset->get_object_name()->to_string() : $asset->get_name();
        }
        return $asset !== $this->configuration_table_name;
    }
    public function on_console_command(Console_Command_Event $event): void
    {
        $command = $event->get_command();
        if (!$command instanceof Validate_Schema_Command && !$command instanceof Update_Command) {
            return;
        }
        $this->enabled = true;
    }
}