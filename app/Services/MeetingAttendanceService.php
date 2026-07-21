<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\MeetingAttendanceEntry;
use App\Models\MeetingAttendanceSession;
use App\Models\MeetingPerson;
use App\Models\ProfesionalAmbiental;
use App\Models\Specialist;
use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use ZipArchive;
use Symfony\Component\Process\Process;

class MeetingAttendanceService
{
    public function templatePath(): string
    {
        $managedTemplate = DocumentTemplate::query()
            ->where('template_type', 'meeting_attendance')
            ->where('file_kind', 'xlsx')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($managedTemplate && $managedTemplate->ruta_archivo) {
            $storedPath = storage_path('app/' . ltrim($managedTemplate->ruta_archivo, '/'));

            if (is_file($storedPath)) {
                return $storedPath;
            }
        }

        return (string) config('meeting_attendance.template_path');
    }

    public function templateVersion(): string
    {
        $managedTemplate = DocumentTemplate::query()
            ->where('template_type', 'meeting_attendance')
            ->where('file_kind', 'xlsx')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($managedTemplate) {
            return (string) ($managedTemplate->nombre ?: $managedTemplate->updated_at?->format('YmdHi') ?: 'Plantilla asistencias');
        }

        return (string) config('meeting_attendance.template_version', '102-SIG-FR-007-V04');
    }

    public function generatePublicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (MeetingAttendanceSession::query()->where('public_token', $token)->exists());

        return $token;
    }

    public function syncInternalPeopleDirectory(): void
    {
        User::query()
            ->with('roles')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessPanel() && filled($user->documento))
            ->each(function (User $user): void {
                $this->upsertInternalMeetingPerson(
                    sourceType: 'user',
                    sourceId: (int) $user->id,
                    documentNumber: (string) $user->documento,
                    fullName: (string) $user->name,
                    organizationArea: $user->roleSlugs()->map(fn ($slug) => str_replace('_', ' ', (string) $slug))->implode(', ') ?: 'Equipo interno',
                    phone: null,
                    emailOrAddress: (string) $user->email,
                );
            });

        Specialist::query()
            ->whereNotNull('documento')
            ->where('documento', '<>', '')
            ->get()
            ->each(function (Specialist $specialist): void {
                $this->upsertInternalMeetingPerson(
                    sourceType: 'specialist',
                    sourceId: (int) $specialist->id,
                    documentNumber: (string) $specialist->documento,
                    fullName: (string) $specialist->nombre,
                    organizationArea: $specialist->especialidad ?: 'Especialista',
                    phone: $specialist->telefono,
                    emailOrAddress: $specialist->correo,
                );
            });

        ProfesionalAmbiental::query()
            ->whereNotNull('documento')
            ->where('documento', '<>', '')
            ->get()
            ->each(function (ProfesionalAmbiental $ambiental): void {
                $this->upsertInternalMeetingPerson(
                    sourceType: 'profesional_ambiental',
                    sourceId: (int) $ambiental->id,
                    documentNumber: (string) $ambiental->documento,
                    fullName: (string) $ambiental->nombre,
                    organizationArea: 'Profesional ambiental',
                    phone: $ambiental->telefono,
                    emailOrAddress: $ambiental->correo,
                );
            });
    }

    public function normalizeDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public function qrSvgDataUri(string $url): string
    {
        $renderer = new ImageRenderer(new RendererStyle(280), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $svg = $writer->writeString($url);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function registerAttendance(MeetingAttendanceSession $session, array $data): MeetingAttendanceEntry
    {
        if (!$session->acceptsRegistrations()) {
            throw new \RuntimeException('La sesión de asistencia ya no está disponible.');
        }

        $normalizedDocument = $this->normalizeDocument((string) ($data['document_number'] ?? ''));
        if (!$normalizedDocument) {
            throw new \RuntimeException('El documento es obligatorio.');
        }

        return DB::transaction(function () use ($session, $data, $normalizedDocument): MeetingAttendanceEntry {
            $duplicate = MeetingAttendanceEntry::query()
                ->where('session_id', $session->id)
                ->where('document_number_normalized', $normalizedDocument)
                ->exists();

            if ($duplicate) {
                throw new \RuntimeException('Esta persona ya registró su asistencia en esta reunión.');
            }

            $person = $this->resolveOrCreatePerson($data, $normalizedDocument);

            $signaturePath = $this->storeSignature((string) ($data['signature_data'] ?? ''));
            $sequence = (int) MeetingAttendanceEntry::query()
                ->where('session_id', $session->id)
                ->max('sequence_number');

            return MeetingAttendanceEntry::query()->create([
                'session_id' => $session->id,
                'person_id' => $person->id,
                'document_number' => (string) ($data['document_number'] ?? ''),
                'document_number_normalized' => $normalizedDocument,
                'full_name' => trim((string) ($data['full_name'] ?? '')),
                'organization_area' => trim((string) ($data['organization_area'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'email_or_address' => trim((string) ($data['email_or_address'] ?? '')) ?: null,
                'signature_path' => $signaturePath,
                'sequence_number' => $sequence + 1,
                'registered_at' => now(),
            ]);
        });
    }

    public function resolveOrCreatePerson(array $data, string $normalizedDocument): MeetingPerson
    {
        $match = $this->findInternalMatch($normalizedDocument);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $organizationArea = trim((string) ($data['organization_area'] ?? '')) ?: null;
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;
        $emailOrAddress = trim((string) ($data['email_or_address'] ?? '')) ?: null;

        $person = MeetingPerson::query()
            ->where('document_number_normalized', $normalizedDocument)
            ->first();

        $payload = [
            'document_number' => (string) ($data['document_number'] ?? $normalizedDocument),
            'document_number_normalized' => $normalizedDocument,
            'full_name' => $fullName,
            'organization_area' => $organizationArea,
            'phone' => $phone,
            'email_or_address' => $emailOrAddress,
            'person_kind' => $match ? ($person && $person->person_kind === 'external' ? 'mixed' : 'internal') : ($person?->person_kind ?? 'external'),
            'internal_source_type' => $match['type'] ?? null,
            'internal_source_id' => $match['id'] ?? null,
        ];

        if ($person) {
            $person->forceFill($payload)->save();

            return $person->fresh();
        }

        return MeetingPerson::query()->create($payload);
    }

    public function findInternalMatch(string $normalizedDocument): ?array
    {
        if ($normalizedDocument === '') {
            return null;
        }

        $user = User::query()->where('documento', $normalizedDocument)->first();
        if ($user) {
            return ['type' => 'user', 'id' => $user->id];
        }

        $specialist = Specialist::query()->where('documento', $normalizedDocument)->first();
        if ($specialist) {
            return ['type' => 'specialist', 'id' => $specialist->id];
        }

        $ambiental = ProfesionalAmbiental::query()->where('documento', $normalizedDocument)->first();
        if ($ambiental) {
            return ['type' => 'profesional_ambiental', 'id' => $ambiental->id];
        }

        return null;
    }

    public function buildOfficialXlsx(MeetingAttendanceSession $session): string
    {
        $templatePath = $this->templatePath();
        if (!is_file($templatePath)) {
            throw new \RuntimeException('No se encontró la plantilla oficial de asistencia.');
        }

        $workDir = $this->makeTempDir('meeting-attendance-' . $session->id);
        $xlsxPath = $workDir . '/registro_asistencia_' . $session->id . '.xlsx';

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheetByName('SIG') ?: $spreadsheet->getActiveSheet();

        $sheet->setCellValue('C1', (string) ($session->objetivo ?? ''));
        $sheet->setCellValue('C2', optional($session->fecha)->format('d/m/Y') ?: '');
        $sheet->setCellValue('C3', (string) ($session->lugar ?? ''));
        $sheet->setCellValue('G2', $this->formatSessionTime($session->hora_inicio));
        $sheet->setCellValue('G3', $this->formatSessionTime($session->hora_terminacion));
        $this->applyWrappedCellLayout($sheet, 'C1:G1');
        $this->applyWrappedCellLayout($sheet, 'C2:E2');
        $this->applyWrappedCellLayout($sheet, 'C3:E3');
        $this->updateWrappedRowHeight($sheet, 1, [
            'C:G' => (string) ($session->objetivo ?? ''),
        ], 28.95);
        $this->updateWrappedRowHeight($sheet, 3, [
            'C:E' => (string) ($session->lugar ?? ''),
        ], 15.6);

        $entries = $session->entries()->get();
        $this->ensureRowCapacity($sheet, max(14, $entries->count()));

        foreach ($entries as $index => $entry) {
            $row = 6 + $index;
            $sheet->setCellValue('A' . $row, $entry->sequence_number);
            $sheet->setCellValue('B' . $row, (string) $entry->full_name);
            $sheet->setCellValue('D' . $row, (string) ($entry->organization_area ?? ''));
            $sheet->setCellValue('E' . $row, (string) ($entry->phone ?? ''));
            $sheet->setCellValue('F' . $row, (string) ($entry->email_or_address ?? ''));
            $this->applyWrappedCellLayout($sheet, "B{$row}:F{$row}");
            $this->updateWrappedRowHeight($sheet, $row, [
                'B:C' => (string) $entry->full_name,
                'D:D' => (string) ($entry->organization_area ?? ''),
                'E:E' => (string) ($entry->phone ?? ''),
                'F:F' => (string) ($entry->email_or_address ?? ''),
            ], 25.05);
            $this->insertSignature($sheet, $entry, $row);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $this->preserveTemplateHeaderFooterAssets($templatePath, $xlsxPath);

        return $xlsxPath;
    }

    public function buildOfficialPdf(MeetingAttendanceSession $session): string
    {
        $xlsxPath = $this->buildOfficialXlsx($session);
        $outputDir = dirname($xlsxPath);
        $templatePath = $this->templatePath();
        $soffice = (string) config('meeting_attendance.soffice_path');
        $process = new Process([
            $soffice,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            $xlsxPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('No se pudo generar el PDF oficial: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $xlsxPath);
        if (!$pdfPath || !is_file($pdfPath)) {
            throw new \RuntimeException('La conversión a PDF no produjo archivo de salida.');
        }

        $this->stampPdfWithTemplateBranding($templatePath, $pdfPath);

        return $pdfPath;
    }

    public function buildSessionSummary(MeetingAttendanceSession $session): array
    {
        $entries = $session->entries()->with('person')->get();

        return [
            'id' => $session->id,
            'status' => $session->registration_status,
            'count' => $entries->count(),
            'entries' => $entries->map(function (MeetingAttendanceEntry $entry): array {
                return [
                    'id' => $entry->id,
                    'sequence_number' => $entry->sequence_number,
                    'full_name' => $entry->full_name,
                    'document_number' => $entry->document_number,
                    'organization_area' => $entry->organization_area,
                    'registered_at' => optional($entry->registered_at)->toDateTimeString(),
                ];
            })->all(),
        ];
    }

    private function upsertInternalMeetingPerson(
        string $sourceType,
        int $sourceId,
        string $documentNumber,
        string $fullName,
        ?string $organizationArea,
        ?string $phone,
        ?string $emailOrAddress,
    ): void {
        $normalizedDocument = $this->normalizeDocument($documentNumber);

        if (!$normalizedDocument) {
            return;
        }

        $person = MeetingPerson::query()
            ->where('document_number_normalized', $normalizedDocument)
            ->first();

        $payload = [
            'document_number' => $documentNumber,
            'document_number_normalized' => $normalizedDocument,
            'full_name' => trim($fullName),
            'organization_area' => filled($organizationArea) ? trim((string) $organizationArea) : ($person?->organization_area),
            'phone' => filled($phone) ? trim((string) $phone) : ($person?->phone),
            'email_or_address' => filled($emailOrAddress) ? trim((string) $emailOrAddress) : ($person?->email_or_address),
            'person_kind' => $person && $person->person_kind === 'external' ? 'mixed' : 'internal',
            'internal_source_type' => $sourceType,
            'internal_source_id' => $sourceId,
        ];

        if ($person) {
            $person->forceFill($payload)->save();
            return;
        }

        MeetingPerson::query()->create($payload);
    }

    private function ensureRowCapacity($sheet, int $requiredRows): void
    {
        $baseRow = 19;
        $available = 14;
        if ($requiredRows <= $available) {
            return;
        }

        $extra = $requiredRows - $available;
        $insertAt = $baseRow + 1;
        $sheet->insertNewRowBefore($insertAt, $extra);

        for ($offset = 0; $offset < $extra; $offset++) {
            $targetRow = $insertAt + $offset;
            foreach (range('A', 'G') as $column) {
                $sheet->duplicateStyle($sheet->getStyle($column . $baseRow), $column . $targetRow);
            }
            $sheet->rowDimensions[$targetRow]->setRowHeight($sheet->getRowDimension($baseRow)->getRowHeight());
            $sheet->mergeCells('B' . $targetRow . ':C' . $targetRow);
        }
    }

    private function insertSignature($sheet, MeetingAttendanceEntry $entry, int $row): void
    {
        if (!$entry->signature_path) {
            return;
        }

        $path = storage_path('app/' . $entry->signature_path);
        if (!is_file($path)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates('G' . $row);
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(2);
        $drawing->setHeight(20);
        $drawing->setWorksheet($sheet);
    }

    private function applyWrappedCellLayout($sheet, string $range): void
    {
        $sheet->getStyle($range)
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    private function updateWrappedRowHeight($sheet, int $row, array $rangesToValues, float $minimumHeight): void
    {
        $lineCount = 1;

        foreach ($rangesToValues as $range => $value) {
            $lineCount = max($lineCount, $this->estimateWrappedLines($value, $this->characterCapacityForRange($sheet, $range)));
        }

        $height = max($minimumHeight, 13.8 * $lineCount + 6);
        $sheet->getRowDimension($row)->setRowHeight($height);
    }

    private function estimateWrappedLines(?string $value, int $capacity): int
    {
        $capacity = max(8, $capacity);
        $value = trim((string) $value);

        if ($value === '') {
            return 1;
        }

        $paragraphs = preg_split('/\R/u', $value) ?: [$value];
        $lines = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                $lines++;
                continue;
            }

            $currentLength = 0;
            foreach (preg_split('/\s+/u', $paragraph) ?: [$paragraph] as $word) {
                $wordLength = max(1, mb_strlen((string) $word));

                if ($currentLength === 0) {
                    $lines += (int) ceil($wordLength / $capacity);
                    $currentLength = $wordLength % $capacity;
                    if ($currentLength === 0) {
                        $currentLength = $capacity;
                    }
                    continue;
                }

                if (($currentLength + 1 + $wordLength) <= $capacity) {
                    $currentLength += 1 + $wordLength;
                    continue;
                }

                $lines++;
                $lines += max(0, (int) ceil($wordLength / $capacity) - 1);
                $currentLength = $wordLength % $capacity;
                if ($currentLength === 0) {
                    $currentLength = $capacity;
                }
            }
        }

        return max(1, $lines);
    }

    private function characterCapacityForRange($sheet, string $range): int
    {
        [$startColumn, $endColumn] = array_pad(explode(':', $range, 2), 2, null);
        $startColumn = preg_replace('/[^A-Z]/i', '', (string) $startColumn) ?: 'A';
        $endColumn = preg_replace('/[^A-Z]/i', '', (string) ($endColumn ?: $startColumn)) ?: $startColumn;
        $startIndex = Coordinate::columnIndexFromString($startColumn);
        $endIndex = Coordinate::columnIndexFromString($endColumn);
        $capacity = 0;

        for ($columnIndex = $startIndex; $columnIndex <= $endIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $width = $sheet->getColumnDimension($column)->getWidth();

            if (!is_numeric($width) || $width <= 0) {
                $width = $sheet->getDefaultColumnDimension()->getWidth();
            }

            if (!is_numeric($width) || $width <= 0) {
                $width = 10;
            }

            $capacity += (int) floor((float) $width);
        }

        return max(8, $capacity - 2);
    }

    private function preserveTemplateHeaderFooterAssets(string $templatePath, string $xlsxPath): void
    {
        $templateZip = new ZipArchive();
        $outputZip = new ZipArchive();

        if ($templateZip->open($templatePath) !== true || $outputZip->open($xlsxPath) !== true) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $sheetXmlPath = 'xl/worksheets/sheet1.xml';
        $sheetRelsPath = 'xl/worksheets/_rels/sheet1.xml.rels';
        $templateSheetXml = $templateZip->getFromName($sheetXmlPath);
        $outputSheetXml = $outputZip->getFromName($sheetXmlPath);
        $templateVml = $templateZip->getFromName('xl/drawings/vmlDrawing1.vml');
        $templateVmlRels = $templateZip->getFromName('xl/drawings/_rels/vmlDrawing1.vml.rels');

        if ($templateSheetXml === false || $outputSheetXml === false || $templateVml === false || $templateVmlRels === false) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $sheetDom = new \DOMDocument();
        $templateSheetDom = new \DOMDocument();
        if (!$sheetDom->loadXML($outputSheetXml) || !$templateSheetDom->loadXML($templateSheetXml)) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $relsDom = new \DOMDocument();
        $relsXml = $outputZip->getFromName($sheetRelsPath);
        if ($relsXml === false) {
            $relsDom->loadXML('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        } elseif (!$relsDom->loadXML($relsXml)) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $relsXPath = new \DOMXPath($relsDom);
        $relsXPath->registerNamespace('pkg', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $existingHeaderFooterRelationship = null;
        foreach ($relsXPath->query('/pkg:Relationships/pkg:Relationship') as $relationshipNode) {
            $type = $relationshipNode->attributes?->getNamedItem('Type')?->nodeValue;
            $target = $relationshipNode->attributes?->getNamedItem('Target')?->nodeValue;

            if (
                $type === 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing'
                && is_string($target)
                && str_contains($target, 'vmlDrawingHF')
            ) {
                $existingHeaderFooterRelationship = $relationshipNode;
                break;
            }
        }

        if (!$existingHeaderFooterRelationship) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $existingTarget = $existingHeaderFooterRelationship->attributes?->getNamedItem('Target')?->nodeValue;
        $existingVmlPath = 'xl/drawings/' . basename((string) $existingTarget);
        $existingVmlRelsPath = 'xl/drawings/_rels/' . basename((string) $existingTarget) . '.rels';

        $vmlRelsDom = new \DOMDocument();
        if (!$vmlRelsDom->loadXML($templateVmlRels)) {
            $templateZip->close();
            $outputZip->close();

            return;
        }

        $vmlRelsXPath = new \DOMXPath($vmlRelsDom);
        $vmlRelsXPath->registerNamespace('pkg', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($vmlRelsXPath->query('/pkg:Relationships/pkg:Relationship') as $mediaRelationship) {
            $target = $mediaRelationship->attributes?->getNamedItem('Target')?->nodeValue;
            if (!$target) {
                continue;
            }

            $templateMediaPath = 'xl/media/' . basename($target);
            $templateMedia = $templateZip->getFromName($templateMediaPath);
            if ($templateMedia === false) {
                continue;
            }

            $extension = pathinfo($templateMediaPath, PATHINFO_EXTENSION) ?: 'png';
            $newMediaName = 'header_footer_' . pathinfo($templateMediaPath, PATHINFO_FILENAME) . '.' . $extension;
            $outputMediaPath = 'xl/media/' . $newMediaName;

            $outputZip->addFromString($outputMediaPath, $templateMedia);
            $mediaRelationship->setAttribute('Target', '../media/' . $newMediaName);
        }

        $this->removeStaleInjectedHeaderFooterAssets($outputZip, $relsDom);

        $outputZip->addFromString($sheetRelsPath, $relsDom->saveXML());
        $outputZip->addFromString($existingVmlPath, $templateVml);
        $outputZip->addFromString($existingVmlRelsPath, $vmlRelsDom->saveXML());

        $this->ensureContentTypesForHeaderFooterAssets($outputZip);

        $templateZip->close();
        $outputZip->close();
    }

    private function ensureContentTypesForHeaderFooterAssets(ZipArchive $zip): void
    {
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if ($contentTypesXml === false) {
            return;
        }

        $dom = new \DOMDocument();
        if (!$dom->loadXML($contentTypesXml)) {
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');

        $hasVml = $xpath->query('/ct:Types/ct:Default[@Extension="vml"]')->length > 0;
        if (!$hasVml) {
            $node = $dom->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Default');
            $node->setAttribute('Extension', 'vml');
            $node->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.vmlDrawing');
            $dom->documentElement?->appendChild($node);
        }

        $hasPng = $xpath->query('/ct:Types/ct:Default[@Extension="png"]')->length > 0;
        if (!$hasPng) {
            $node = $dom->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Default');
            $node->setAttribute('Extension', 'png');
            $node->setAttribute('ContentType', 'image/png');
            $dom->documentElement?->appendChild($node);
        }

        $zip->addFromString('[Content_Types].xml', $dom->saveXML());
    }

    private function removeStaleInjectedHeaderFooterAssets(ZipArchive $zip, \DOMDocument $relsDom): void
    {
        $relsXPath = new \DOMXPath($relsDom);
        $relsXPath->registerNamespace('pkg', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($relsXPath->query('/pkg:Relationships/pkg:Relationship') as $relationshipNode) {
            $target = $relationshipNode->attributes?->getNamedItem('Target')?->nodeValue;
            if (!is_string($target) || !str_contains($target, 'vmlDrawing_header_footer')) {
                continue;
            }

            $relationshipNode->parentNode?->removeChild($relationshipNode);
            $zip->deleteName('xl/drawings/' . basename($target));
            $zip->deleteName('xl/drawings/_rels/' . basename($target) . '.rels');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            return;
        }

        $sheetDom = new \DOMDocument();
        if (!$sheetDom->loadXML($sheetXml)) {
            return;
        }

        $sheetXPath = new \DOMXPath($sheetDom);
        $sheetXPath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheetXPath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        foreach ($sheetXPath->query('/main:worksheet/main:legacyDrawingHF') as $legacyNode) {
            $rid = $legacyNode->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')?->nodeValue;
            if ($rid === 'rId3') {
                $legacyNode->parentNode?->removeChild($legacyNode);
            }
        }

        $sheetRelsXml = $relsDom->saveXML();
        $sheetRelsCheckDom = new \DOMDocument();
        if ($sheetRelsXml && $sheetRelsCheckDom->loadXML($sheetRelsXml)) {
            $checkXPath = new \DOMXPath($sheetRelsCheckDom);
            $checkXPath->registerNamespace('pkg', 'http://schemas.openxmlformats.org/package/2006/relationships');
            $headerFooterRelationship = $checkXPath->query('/pkg:Relationships/pkg:Relationship[contains(@Target, "vmlDrawingHF")]')->item(0);

            if ($headerFooterRelationship) {
                $relationshipId = $headerFooterRelationship->attributes?->getNamedItem('Id')?->nodeValue;
                $legacyNodes = $sheetXPath->query('/main:worksheet/main:legacyDrawingHF');
                if ($legacyNodes->length > 0 && $relationshipId) {
                    $legacyNodes->item(0)?->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $relationshipId);
                }
            }
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetDom->saveXML());
    }

    private function stampPdfWithTemplateBranding(string $templatePath, string $pdfPath): void
    {
        $assets = $this->extractTemplateHeaderFooterImages($templatePath);
        if (!$assets['header'] || !$assets['footer']) {
            return;
        }

        $python = (string) config('meeting_attendance.python_path', 'python3');
        $stampedPath = preg_replace('/\.pdf$/i', '_stamped.pdf', $pdfPath);
        if (!$stampedPath) {
            return;
        }

        $scriptPath = base_path('scripts/stamp_meeting_attendance_pdf.py');
        $process = new Process([
            $python,
            $scriptPath,
            $pdfPath,
            $stampedPath,
            $assets['header']['path'],
            (string) $assets['header']['width'],
            (string) $assets['header']['height'],
            $assets['footer']['path'],
            (string) $assets['footer']['width'],
            (string) $assets['footer']['height'],
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful() || !is_file($stampedPath)) {
            return;
        }

        File::move($stampedPath, $pdfPath);
    }

    private function extractTemplateHeaderFooterImages(string $templatePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== true) {
            return ['header' => null, 'footer' => null];
        }

        $vml = $zip->getFromName('xl/drawings/vmlDrawing1.vml');
        $rels = $zip->getFromName('xl/drawings/_rels/vmlDrawing1.vml.rels');
        if ($vml === false || $rels === false) {
            $zip->close();
            return ['header' => null, 'footer' => null];
        }

        $mapping = [];
        if (preg_match_all('/<Relationship[^>]+Id="([^"]+)"[^>]+Target="..\/media\/([^"]+)"/', $rels, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $mapping[$match[1]] = $match[2];
            }
        }

        $assets = ['header' => null, 'footer' => null];
        if (preg_match_all('/<v:shape[^>]+id="(LH|LF)".*?width:([0-9.]+)pt;height:([0-9.]+)pt;.*?<v:imagedata o:relid="([^"]+)"/s', $vml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $slot = $match[1] === 'LH' ? 'header' : 'footer';
                $mediaName = $mapping[$match[4]] ?? null;
                if (!$mediaName) {
                    continue;
                }

                $binary = $zip->getFromName('xl/media/' . $mediaName);
                if ($binary === false) {
                    continue;
                }

                $tempPath = storage_path('app/tmp/' . Str::uuid() . '_' . basename($mediaName));
                File::ensureDirectoryExists(dirname($tempPath));
                File::put($tempPath, $binary);

                $assets[$slot] = [
                    'path' => $tempPath,
                    'width' => (float) $match[2],
                    'height' => (float) $match[3],
                ];
            }
        }

        $zip->close();

        return $assets;
    }

    private function formatSessionTime($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return substr($value, 0, 5);
        }
    }

    private function storeSignature(string $dataUrl): string
    {
        if (!preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            throw new \RuntimeException('La firma no tiene un formato válido.');
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false) {
            throw new \RuntimeException('No se pudo decodificar la firma.');
        }

        $path = 'meeting-attendance/signatures/' . Str::uuid() . '.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = storage_path('app/tmp/' . $prefix . '-' . Str::uuid());
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
