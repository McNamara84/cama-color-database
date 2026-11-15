<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFixture extends AbstractMigration
{
    public function change(): void
    {
        $this->table('migration_fixture')->addColumn('label', 'string', ['limit' => 50])->create();
    }
}
