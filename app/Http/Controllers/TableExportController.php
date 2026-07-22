<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a table from a chat reply as a formatted spreadsheet.
 *
 * A CSV loses the shape of the table, so the workbook keeps the header, the
 * borders and sensible column widths, and arrives looking like what was on
 * screen.
 */
class TableExportController extends Controller
{
    private const MAX_COLUMN_WIDTH = 60;

    public function xlsx(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array', 'min:1', 'max:50'],
            'rows.*.*' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:80'],
        ]);

        $rows = $validated['rows'];
        $spreadsheet = $this->build($rows, $validated['title'] ?? 'Table');

        $filename = 'phakhaolao-table-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows  header first
     */
    private function build(array $rows, string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title) ?: 'Table', 0, 31));

        $columnCount = max(array_map('count', $rows));

        foreach ($rows as $rowOffset => $cells) {
            foreach (range(0, $columnCount - 1) as $columnOffset) {
                // Written as text so a value like "3-4" is not read as a date.
                $sheet->setCellValueExplicit(
                    [$columnOffset + 1, $rowOffset + 1],
                    (string) ($cells[$columnOffset] ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = count($rows);
        $all = "A1:{$lastColumn}{$lastRow}";

        $sheet->getStyle($all)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4D4D8']],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '27272A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F4F4F5'],
            ],
        ]);

        $sheet->getStyle("A2:A{$lastRow}")->getFont()->setBold(true);

        // Keep the header visible when scrolling a long matrix.
        $sheet->freezePane('A2');

        // Width from the longest value in each column, bounded so one long cell
        // cannot stretch the sheet off screen.
        foreach (range(0, $columnCount - 1) as $columnOffset) {
            $longest = 0;

            foreach ($rows as $cells) {
                $longest = max($longest, mb_strlen((string) ($cells[$columnOffset] ?? '')));
            }

            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnOffset + 1))
                ->setWidth(min(max($longest + 2, 14), self::MAX_COLUMN_WIDTH));
        }

        return $spreadsheet;
    }
}
