<?php

namespace App\Services;

use App\Models\CertificateAward;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateGenerationService
{
    /**
     * Generate PDF certificate for the award.
     */
    public function generatePdf(CertificateAward $award): string
    {
        $user = $award->user;
        $certificate = $award->certificate;
        $template = $certificate->template;

        // Compile details
        $studentName = $user->name;
        $title = $award->title;
        $description = $award->description ?? '';
        $date = $award->issued_at->format('F d, Y');
        $code = $award->code;

        // Build public verification URL (frontend verification page)
        $verifyUrl = rtrim(config('app.frontend_url'), '/') . '/certificates/verify/' . $award->verification_token;

        // Visual styles fallback
        $primaryColor = $template->branding['primary_color'] ?? '#0F172A';
        $secondaryColor = $template->branding['secondary_color'] ?? '#10B981';
        $signatures = $template->signatures ?? [
            ['name' => 'Dr. Abdullah Mohammad', 'title' => 'Academy Director', 'image_path' => null],
            ['name' => 'Sr. Fatima Al-Zahra', 'title' => 'Academy Coordinator', 'image_path' => null],
        ];
        $layout = $template->layout ?? 'landscape';

        // Render template using a blade view
        $pdf = Pdf::loadView('certificates.pdf', [
            'studentName' => $studentName,
            'title' => $title,
            'description' => $description,
            'date' => $date,
            'code' => $code,
            'verifyUrl' => $verifyUrl,
            'primaryColor' => $primaryColor,
            'secondaryColor' => $secondaryColor,
            'signatures' => $signatures,
            'layout' => $layout,
        ]);

        $pdf->setPaper('a4', $layout);

        // Save file to public storage
        $fileName = 'certificates/' . $award->uuid . '.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        // Update database path
        $pdfPath = '/storage/' . $fileName;
        $award->update(['pdf_path' => $pdfPath]);

        return $pdfPath;
    }
}
