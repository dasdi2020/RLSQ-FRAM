<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use RLSQ\Console\Helper\Table;
use RLSQ\Console\Output\BufferedOutput;

class TableTest extends TestCase
{
    public function testRenderTable(): void
    {
        $output = new BufferedOutput();
        $table = new Table($output);

        $table->setHeaders(['Name', 'Age']);
        $table->addRow(['Alice', '30']);
        $table->addRow(['Bob', '25']);
        $table->render();

        $content = $output->getBuffer();

        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Age', $content);
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('Bob', $content);
        $this->assertStringContainsString('+', $content);
        $this->assertStringContainsString('|', $content);
    }

    public function testRenderWithoutHeaders(): void
    {
        $output = new BufferedOutput();
        $table = new Table($output);

        $table->setRows([['a', 'b'], ['c', 'd']]);
        $table->render();

        $content = $output->getBuffer();

        $this->assertStringContainsString('a', $content);
        $this->assertStringContainsString('d', $content);
    }

    public function testEmptyTable(): void
    {
        $output = new BufferedOutput();
        $table = new Table($output);
        $table->render();

        $this->assertSame('', $output->getBuffer());
    }
}
