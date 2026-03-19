<?php

declare(strict_types=1);

namespace RLSQ\Console\Helper;

use RLSQ\Console\Output\OutputInterface;

class Table
{
    private array $headers = [];
    private array $rows = [];

    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function addRow(array $row): static
    {
        $this->rows[] = $row;
        return $this;
    }

    public function setRows(array $rows): static
    {
        $this->rows = $rows;
        return $this;
    }

    public function render(): void
    {
        $allRows = [];
        if (!empty($this->headers)) {
            $allRows[] = $this->headers;
        }
        $allRows = array_merge($allRows, $this->rows);

        if (empty($allRows)) {
            return;
        }

        // Calculer la largeur max de chaque colonne
        $colCount = max(array_map('count', $allRows));
        $widths = array_fill(0, $colCount, 0);

        foreach ($allRows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], mb_strlen((string) $cell));
            }
        }

        $separator = '+' . implode('+', array_map(fn (int $w) => str_repeat('-', $w + 2), $widths)) . '+';

        $this->output->writeln($separator);

        $rowIndex = 0;
        foreach ($allRows as $row) {
            $line = '|';
            foreach ($widths as $i => $w) {
                $cell = (string) ($row[$i] ?? '');
                $line .= ' ' . str_pad($cell, $w) . ' |';
            }
            $this->output->writeln($line);

            // Séparateur après les headers
            if ($rowIndex === 0 && !empty($this->headers)) {
                $this->output->writeln($separator);
            }

            $rowIndex++;
        }

        $this->output->writeln($separator);
    }
}
