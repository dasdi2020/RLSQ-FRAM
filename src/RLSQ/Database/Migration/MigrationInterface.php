<?php

declare(strict_types=1);

namespace RLSQ\Database\Migration;

use RLSQ\Database\Connection;

interface MigrationInterface
{
    public function up(Connection $connection): void;

    public function down(Connection $connection): void;

    public function getVersion(): string;

    public function getDescription(): string;
}
