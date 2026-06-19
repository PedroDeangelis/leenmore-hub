<?php

namespace App\Http\Controllers;

use App\Imports\ShareholderImporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a sample shareholder-import CSV (headers + one example row) so users
 * know the expected column layout.
 */
class ShareholderTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the Korean headers correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ShareholderImporter::templateHeaders());
            fputcsv($out, ShareholderImporter::templateSample());
            fclose($out);
        }, 'shareholder-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
