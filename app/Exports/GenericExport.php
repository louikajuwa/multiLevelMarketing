<?php

namespace App\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Streams an Eloquent query to Excel/CSV in chunks — the full result set is never
 * held in memory. $rowMap turns each model into a scalar row.
 */
class GenericExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    /**
     * @param  string[]  $columns
     * @param  Closure(mixed):array<int,mixed>  $rowMap
     */
    public function __construct(
        private array $columns,
        private Builder $query,
        private Closure $rowMap,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    /** @return array<int,mixed> */
    public function map($row): array
    {
        return ($this->rowMap)($row);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
