<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

final class CertificateDownloadController extends Controller
{
    public function __invoke(Certificate $certificate)
    {
        $this->authorize('download', $certificate);

        if (! Storage::disk('private')->exists($certificate->pdf_path)) {
            abort(404);
        }

        return Storage::disk('private')->download(
            $certificate->pdf_path,
            'certificate.pdf',
        );
    }
}
