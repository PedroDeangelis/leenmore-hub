<?php

namespace App\Services\WorkerRoster;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * Renders the 명부양식 PDF. Prefers LibreOffice headless converting the
 * generated XLSX (matches Excel's print output exactly); when the binary is
 * missing or fails, falls back to mPDF rendering the roster as an HTML table
 * with the bundled Nanum Gothic fonts (legacy behaviour of the retired
 * leenmore-storage ReportGenerator).
 */
class RosterPdfRenderer
{
    /** Font family name registered with mPDF for the fallback layout. */
    public const FONT_FAMILY = 'nanumgothic';

    /**
     * @param  list<array<string, int|string>>  $rows  column letter => value, A–L
     */
    public function render(string $xlsxPath, string $pdfPath, string $projectName, string $workerName, array $rows): void
    {
        if ($this->renderViaLibreOffice($xlsxPath, $pdfPath)) {
            return;
        }

        $this->renderViaMpdf($pdfPath, $projectName, $workerName, $rows);
    }

    private function renderViaLibreOffice(string $xlsxPath, string $pdfPath): bool
    {
        $binary = $this->libreOfficeBinary();
        if ($binary === null) {
            return false;
        }

        $outDir = dirname($pdfPath);
        // Per-invocation profile dir so parallel conversions don't fight over a shared one.
        $profileDir = sys_get_temp_dir().'/lo-profile-'.bin2hex(random_bytes(6));

        $result = Process::timeout(120)->run([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '-env:UserInstallation=file://'.$profileDir,
            '--convert-to',
            'pdf',
            '--outdir',
            $outDir,
            $xlsxPath,
        ]);

        File::deleteDirectory($profileDir);

        if (! $result->successful()) {
            return false;
        }

        // LibreOffice writes <input-stem>.pdf into --outdir; move it if our
        // target name differs.
        $produced = rtrim($outDir, '/').'/'.pathinfo($xlsxPath, PATHINFO_FILENAME).'.pdf';
        if ($produced !== $pdfPath) {
            if (! is_file($produced) || ! @rename($produced, $pdfPath)) {
                return false;
            }
        }

        return is_file($pdfPath);
    }

    private function libreOfficeBinary(): ?string
    {
        // Shared hosting usually disables proc_open, and Symfony's Process throws
        // rather than failing soft — bail out early so mPDF takes over.
        if (! function_exists('proc_open')) {
            return null;
        }

        $configured = config('services.libreoffice.path');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        foreach (['libreoffice', 'soffice'] as $name) {
            $result = Process::run(['which', $name]);
            $path = trim($result->output());
            if ($result->successful() && $path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     */
    private function renderViaMpdf(string $pdfPath, string $projectName, string $workerName, array $rows): void
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        // Merge our font dir/data with mPDF's defaults — overriding outright
        // would drop its bundled fallback fonts (legacy CustomFontMpdfWriter note).
        $defaultFontDirs = (new ConfigVariables)->getDefaults()['fontDir'] ?? [];
        $defaultFontData = (new FontVariables)->getDefaults()['fontdata'] ?? [];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $tempDir,
            'fontDir' => array_merge($defaultFontDirs, [resource_path('fonts/nanum-gothic')]),
            'fontdata' => $defaultFontData + [
                self::FONT_FAMILY => [
                    'R' => 'NanumGothic-Regular.ttf',
                    'B' => 'NanumGothic-Bold.ttf',
                ],
            ],
            'default_font' => self::FONT_FAMILY,
        ]);

        $mpdf->WriteHTML(view('exports.worker-roster-pdf', [
            'projectName' => $projectName,
            'workerName' => $workerName,
            'rows' => $rows,
        ])->render());

        $mpdf->Output($pdfPath, Destination::FILE);

        if (! is_file($pdfPath)) {
            throw new RuntimeException('mPDF produced no file at '.$pdfPath);
        }
    }
}
