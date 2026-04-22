<?php

namespace App\Http\Controllers\SALN;

use App\Http\Controllers\Controller;
use App\Models\SALN\SalnRecord;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class SalnPdfController extends Controller
{
    /**
     * Generate and stream the SALN PDF for the given record.
     *
     * Access rules:
     *  - Owner may download own SALN if approved or filed.
     *  - HR (saln.view_all) and committee (saln.review) may download any approved/filed SALN.
     */
    public function __invoke(SalnRecord $saln): Response
    {
        $user = Auth::user();

        $isOwner    = $saln->user_id === $user->id;
        $canViewAll = $user->hasPermission('saln.view_all');
        $canReview  = $user->hasPermission('saln.review');

        if (! ($isOwner || $canViewAll || $canReview)) {
            abort(403, 'You are not authorised to download this SALN.');
        }

        if (! in_array($saln->status, ['approved', 'filed'])) {
            abort(403, 'PDF export is only available for approved or filed SALN records.');
        }

        // Eager-load all sections needed by the Blade template
        $saln->load([
            'user:id,name,position',
            'filer:id,name',
            'realProperties',
            'personalProperties',
            'liabilities',
            'businessInterests',
            'relatives',
        ]);

        // Render Blade template to HTML
        $html = view('saln.pdf', ['saln' => $saln])->render();

        // Build PDF via mPDF (Legal portrait — standard government form size)
        try {
            $mpdf = new Mpdf([
                'mode'              => 'utf-8',
                'format'            => 'Legal',
                'orientation'       => 'P',
                'margin_left'       => 15,
                'margin_right'      => 15,
                'margin_top'        => 12,
                'margin_bottom'     => 12,
                'margin_footer'     => 5,
                'default_font'      => 'Arial',
                'default_font_size' => 8,
                'tempDir'           => storage_path('app/tmp'),
            ]);

            $mpdf->SetTitle(sprintf('SALN %d — %s', $saln->year, $saln->user->name));
            $mpdf->SetAuthor(config('app.agency_name', config('app.name')));
            $mpdf->SetCreator(config('app.name'));
            $mpdf->SetSubject('Statement of Assets, Liabilities and Net Worth');

            $mpdf->WriteHTML($html);

            $filename = sprintf(
                'SALN_%d_%s.pdf',
                $saln->year,
                str_replace(' ', '_', $saln->user->name)
            );

            // 'D' = force download, 'I' = inline (browser preview)
            return response($mpdf->Output('', 'S'), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);

        } catch (MpdfException $e) {
            abort(500, 'PDF generation failed: ' . $e->getMessage());
        }
    }
}
