<?php

namespace App\Support;

use App\Models\SalarySlip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Shared builder for the salary slip page / PDF (admin, employee web and API). */
class SalarySlipDocument
{
    public static function data(SalarySlip $slip, bool $pdf = false, ?string $backUrl = null): array
    {
        $slip->loadMissing(['employee.designation', 'submittedBy', 'processedBy']);

        return [
            'slip' => $slip,
            'company' => config('invoice'),
            'amountInWords' => AmountInWords::rupees((float) $slip->net_salary),
            'pdf' => $pdf,
            'backUrl' => $backUrl,
            'logoSrc' => $pdf ? self::logoDataUri() : asset('images/logo-dark.png'),
        ];
    }

    public static function filename(SalarySlip $slip): string
    {
        $slip->loadMissing('employee');
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $slip->employee?->name) ?: 'employee', '-');

        return strtolower(sprintf('salary-slip-%s-%04d-%02d.pdf', $name, $slip->year, $slip->month));
    }

    /** Same dompdf settings as the quotation / invoice PDFs (subset fonts keep the file small). */
    public static function pdf(SalarySlip $slip)
    {
        return Pdf::loadView('salary.slip', self::data($slip, true))
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 96)
            ->setWarnings(false);
    }

    /**
     * PDF as an HTTP response. Inline by default so in-app viewers, WebViews
     * and browsers can display it; $download = true forces a file download.
     */
    public static function response(SalarySlip $slip, bool $download = false): Response
    {
        $content = self::pdf($slip)->output();
        $filename = self::filename($slip);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The logo embedded as base64, so it never depends on where the hosting
     * puts the public folder (e.g. public_html on cPanel) or on dompdf's chroot.
     */
    private static function logoDataUri(): ?string
    {
        foreach ([
            public_path('images/logo-dark.png'),
            base_path('public/images/logo-dark.png'),
            base_path('design/logo-dark.png'),
        ] as $path) {
            if (is_file($path) && is_readable($path)) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }
}
