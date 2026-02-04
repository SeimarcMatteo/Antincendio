<?php

namespace App\Exports;

use App\Models\GiacenzaPresidio;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GiacenzePresidiExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return GiacenzaPresidio::with('tipoEstintore')->get();
    }

    public function headings(): array
    {
        return ['Categoria', 'Tipo Estintore', 'Quantità'];
    }

    public function map($row): array
    {
        return [
            $row->categoria,
            $row->tipoEstintore->sigla ?? '-',
            $row->quantita,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F87171']]],
            'A'  => ['alignment' => ['horizontal' => 'left']],
            'C'  => ['alignment' => ['horizontal' => 'center']],
        ];
    }
}
