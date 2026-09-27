<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class CertificatePdfService
{
    public function generate(Certificate $certificate): void
    {
        $html = view('certificates.pdf', [
            'certificate' => $certificate,
        ])->render();

        $pdf = new Mpdf([
            'default_font' => 'sun-exta',
        ]);

        $pdf->WriteHTML($html);

        $content = $pdf->Output('', Destination::STRING_RETURN);

        $stored = Storage::disk('private')->put(
            $certificate->pdf_path,
            $content,
        );

        if (! $stored) {
            Storage::disk('private')->delete($certificate->pdf_path);

            throw new \RuntimeException('修了証 PDF の保存に失敗しました。');
        }
    }
}
