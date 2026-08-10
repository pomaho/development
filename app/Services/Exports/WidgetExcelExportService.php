<?php

declare(strict_types=1);

namespace App\Services\Exports;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WidgetExcelExportService
{
    private const HEADER_BG = 'FF1E293B';
    private const HEADER_FG = 'FFFFFFFF';
    private const TOTAL_BG  = 'FFE2E8F0';
    private const ALT_BG    = 'FFF8FAFC';

    public function export(
        string $filename,
        array $recruiterLeads,
        array $breakdown,
        array $projectCityVacancy,
        array $taskStatistics,
        ?array $avitoCabinetBreakdown = null,
        ?array $shiftDateLeads = null,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->recruiterLeadsSheet($spreadsheet, $recruiterLeads);
        $this->sourceBreakdownSheet($spreadsheet, $breakdown);
        $this->teamCitySheet($spreadsheet, $breakdown);
        $this->projectCityVacancySheet($spreadsheet, $projectCityVacancy);
        $this->taskStatisticsSheet($spreadsheet, $taskStatistics);

        if ($avitoCabinetBreakdown !== null) {
            $this->avitoCabinetSheet($spreadsheet, $avitoCabinetBreakdown);
        }

        if ($shiftDateLeads !== null) {
            $this->shiftDateLeadsSheet($spreadsheet, $shiftDateLeads);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function recruiterLeadsSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Рекрутеры');

        $headers = ['Рекрутер', 'Назначено лидов', 'Передано менеджеру'];
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        foreach ($data['recruiters'] ?? [] as $recruiter) {
            $sheet->setCellValue("A{$row}", $recruiter['name']);
            $sheet->setCellValue("B{$row}", $recruiter['leads_count']);
            $sheet->setCellValue("C{$row}", $recruiter['transferred_to_manager_count']);
            if ($row % 2 === 0) {
                $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
            }
            $row++;
        }

        $this->writeTotalRow($sheet, $row, [
            'Итого',
            $data['total_leads_count'] ?? 0,
            $data['transferred_to_manager_count'] ?? 0,
        ]);

        $this->autoWidth($sheet, count($headers));
    }

    private function sourceBreakdownSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('По источникам');

        $sourceCols = $data['source_columns'] ?? [];
        $headers = ['Источник', 'Кол-во лидов'];
        $this->writeHeader($sheet, $headers, 1);

        $totals = [];
        foreach ($data['recruiters'] ?? [] as $recruiter) {
            foreach ($recruiter['teams'] ?? [] as $team) {
                foreach ($team['cities'] ?? [] as $city) {
                    foreach ($sourceCols as $src) {
                        $totals[$src] = ($totals[$src] ?? 0) + ($city['sources'][$src] ?? 0);
                    }
                }
            }
        }

        arsort($totals);

        $row = 2;
        foreach ($totals as $source => $count) {
            $sheet->setCellValue("A{$row}", $source);
            $sheet->setCellValue("B{$row}", $count);
            if ($row % 2 === 0) {
                $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
            }
            $row++;
        }

        $this->writeTotalRow($sheet, $row, ['Итого', array_sum($totals)]);
        $this->autoWidth($sheet, count($headers));
    }

    private function teamCitySheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Команды и города');

        $sourceCols = $data['source_columns'] ?? [];
        $headers = ['Рекрутер', 'Команда', 'Город', 'Кол-во лидов', ...$sourceCols];
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        foreach ($data['recruiters'] ?? [] as $recruiter) {
            foreach ($recruiter['teams'] ?? [] as $team) {
                foreach ($team['cities'] ?? [] as $city) {
                    $sheet->setCellValue("A{$row}", $recruiter['name']);
                    $sheet->setCellValue("B{$row}", $team['name']);
                    $sheet->setCellValue("C{$row}", $city['name']);
                    $sheet->setCellValue("D{$row}", $city['leads_count']);
                    foreach ($sourceCols as $i => $src) {
                        $col = $this->colLetter(4 + $i);
                        $sheet->setCellValue("{$col}{$row}", $city['sources'][$src] ?? 0);
                    }
                    if ($row % 2 === 0) {
                        $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
                    }
                    $row++;
                }
            }
        }

        $this->autoWidth($sheet, count($headers));
    }

    private function projectCityVacancySheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Проект-Город-Вакансия');

        $sourceCols = $data['source_columns'] ?? [];
        $hasTeams = !empty($data['teams']);

        if ($hasTeams) {
            $headers = ['Команда', 'Проект', 'Город', 'Вакансия', 'Кол-во лидов', ...$sourceCols];
        } else {
            $headers = ['Проект', 'Город', 'Вакансия', 'Кол-во лидов', ...$sourceCols];
        }
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        $items = $hasTeams ? ($data['teams'] ?? []) : ($data['projects'] ?? []);

        foreach ($items as $teamOrProject) {
            $projects = $hasTeams ? ($teamOrProject['projects'] ?? []) : [$teamOrProject];
            foreach ($projects as $project) {
                foreach ($project['cities'] ?? [] as $city) {
                    foreach ($city['vacancies'] ?? [] as $vacancy) {
                        $col = 'A';
                        if ($hasTeams) {
                            $sheet->setCellValue("{$col}{$row}", $teamOrProject['name']);
                            $col = 'B';
                        }
                        $sheet->setCellValue("{$col}{$row}", $project['name']);
                        $col = $hasTeams ? 'C' : 'B';
                        $sheet->setCellValue("{$col}{$row}", $city['name']);
                        $col = $hasTeams ? 'D' : 'C';
                        $sheet->setCellValue("{$col}{$row}", $vacancy['name']);
                        $col = $hasTeams ? 'E' : 'D';
                        $sheet->setCellValue("{$col}{$row}", $vacancy['leads_count']);
                        $offset = $hasTeams ? 5 : 4;
                        foreach ($sourceCols as $i => $src) {
                            $c = $this->colLetter($offset + $i);
                            $sheet->setCellValue("{$c}{$row}", $vacancy['sources'][$src] ?? 0);
                        }
                        if ($row % 2 === 0) {
                            $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
                        }
                        $row++;
                    }
                }
            }
        }

        $this->autoWidth($sheet, count($headers));
    }

    private function taskStatisticsSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Задачи');

        $headers = ['Группа', 'Сотрудник', 'Закрыто', 'Просрочено (закрытые)', 'Открыто', 'Просрочено (открытые)', '% просроченных'];
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        foreach ($data as $group) {
            foreach ($group['users'] ?? [] as $user) {
                $sheet->setCellValue("A{$row}", $group['group_name']);
                $sheet->setCellValue("B{$row}", $user['responsible_name'] ?? "ID {$user['responsible_user_id']}");
                $sheet->setCellValue("C{$row}", $user['completed_count']);
                $sheet->setCellValue("D{$row}", $user['completed_overdue_count']);
                $sheet->setCellValue("E{$row}", $user['open_count']);
                $sheet->setCellValue("F{$row}", $user['open_overdue_count']);
                $sheet->setCellValue("G{$row}", $user['overdue_rate']);
                if ($row % 2 === 0) {
                    $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
                }
                $row++;
            }
        }

        $this->autoWidth($sheet, count($headers));
    }

    private function avitoCabinetSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Кабинеты Авито');

        $headers = ['Кабинет Авито', 'Лидов', 'Встал в график'];
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        $totalCount = 0;
        $totalSuccess = 0;
        foreach ($data['cabinets'] ?? [] as $cabinet) {
            $sheet->setCellValue("A{$row}", $cabinet['name']);
            $sheet->setCellValue("B{$row}", $cabinet['total_count']);
            $sheet->setCellValue("C{$row}", $cabinet['success_count']);
            $totalCount += $cabinet['total_count'];
            $totalSuccess += $cabinet['success_count'];
            if ($row % 2 === 0) {
                $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
            }
            $row++;
        }

        $this->writeTotalRow($sheet, $row, ['Итого', $totalCount, $totalSuccess]);
        $this->autoWidth($sheet, count($headers));
    }

    private function shiftDateLeadsSheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Вышедшие в смену');

        $headers = ['Сделка', 'Дата смены', 'Город', 'Команда', 'Менеджер', 'Рекрутер'];
        $this->writeHeader($sheet, $headers, 1);

        $row = 2;
        foreach ($data['leads'] ?? [] as $lead) {
            $sheet->setCellValue("A{$row}", $lead['name']);
            $sheet->setCellValue("B{$row}", $lead['shift_date']);
            $sheet->setCellValue("C{$row}", $lead['city']);
            $sheet->setCellValue("D{$row}", $lead['team']);
            $sheet->setCellValue("E{$row}", $lead['manager']);
            $sheet->setCellValue("F{$row}", $lead['recruiter']);
            if ($row % 2 === 0) {
                $this->fillRow($sheet, $row, count($headers), self::ALT_BG);
            }
            $row++;
        }

        $this->autoWidth($sheet, count($headers));
    }

    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers, int $row): void
    {
        foreach ($headers as $i => $header) {
            $col = $this->colLetter($i);
            $cell = $sheet->setCellValue("{$col}{$row}", $header);
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::HEADER_FG]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function writeTotalRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $i => $value) {
            $col = $this->colLetter($i);
            $sheet->setCellValue("{$col}{$row}", $value);
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_BG]],
            ]);
        }
    }

    private function fillRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, int $colCount, string $argb): void
    {
        $lastCol = $this->colLetter($colCount - 1);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
        ]);
    }

    private function autoWidth(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colCount): void
    {
        for ($i = 0; $i < $colCount; $i++) {
            $sheet->getColumnDimension($this->colLetter($i))->setAutoSize(true);
        }
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }

    public static function filename(Carbon $from, Carbon $to): string
    {
        return "otchet-{$from->format('d.m.Y')}-{$to->format('d.m.Y')}.xlsx";
    }
}
