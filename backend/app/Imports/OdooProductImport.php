<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OdooProductImport implements WithMultipleSheets
{
    private $sheetImports = [];

    public function __construct(int $sheetCount = 10)
    {
        $count = max(1, $sheetCount);
        for ($i = 0; $i < $count; $i++) {
            $this->sheetImports[$i] = new OdooProductMainSheetImport();
        }
    }

    public function sheets(): array
    {
        return $this->sheetImports;
    }

    public function getSummary()
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'images_added' => 0,
            'changes' => [],
            'errors'  => []
        ];

        foreach ($this->sheetImports as $import) {
            $stats = $import->getStats();
            $summary['created'] += $stats['created'] ?? 0;
            $summary['updated'] += $stats['updated'] ?? 0;
            $summary['failed']  += $stats['failed'] ?? 0;
            $summary['images_added'] += $stats['images_added'] ?? 0;
            if (!empty($stats['changes'])) {
                $summary['changes'] = array_merge($summary['changes'], $stats['changes']);
            }
            if (!empty($stats['errors'])) {
                $summary['errors'] = array_merge($summary['errors'], array_unique($stats['errors']));
            }
        }

        return $summary;
    }
}
