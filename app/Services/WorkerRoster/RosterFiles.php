<?php

namespace App\Services\WorkerRoster;

/**
 * The generated 명부양식 files for one worker: paths relative to the private
 * `local` disk, plus the clean filename stem (no storage suffixes) to expose
 * as the download / attachment name.
 */
final readonly class RosterFiles
{
    public function __construct(
        public string $xlsxPath,
        public ?string $pdfPath,
        public string $filenameStem,
    ) {}
}
