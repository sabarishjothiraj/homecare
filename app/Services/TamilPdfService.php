<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * PDF generation for documents containing Tamil text.
 *
 * DomPDF (used elsewhere in this app) has no OpenType layout engine: given
 * Tamil it silently falls back to a Latin base font and emits no Tamil
 * glyphs at all. Tamil needs real shaping — pre-base vowel signs such as
 * ெ ே ை are stored after their consonant but must render before it, and
 * virama clusters need substitution. mPDF ships an Indic shaper, so Tamil
 * documents go through this service instead.
 */
class TamilPdfService
{
    /**
     * Font family name referenced from the Blade templates.
     */
    public const FONT = 'hindmadurai';

    /**
     * Build a configured mPDF instance with the Tamil font registered.
     */
    protected function makeMpdf(array $overrides = []): Mpdf
    {
        $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        return new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'fontDir' => array_merge($fontDirs, [storage_path('fonts')]),
            'fontdata' => $fontData + [
                self::FONT => [
                    'R' => 'HindMadurai-Regular.ttf',
                    'B' => 'HindMadurai-Bold.ttf',
                    // 0xFF enables the full OpenType layout engine, which is
                    // what actually reorders and ligates the Tamil clusters.
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => self::FONT,
            'default_font_size' => 9,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('app/mpdf'),
        ], $overrides));
    }

    /**
     * Render a Blade view to a PDF file under storage/app/public.
     *
     * @return string path relative to the public disk
     */
    public function renderToFile(string $view, array $data, string $relativePath): string
    {
        $this->ensureTempDir();

        $mpdf = $this->makeMpdf();
        $mpdf->SetTitle($data['document_title'] ?? 'Agreement');
        $mpdf->WriteHTML(view($view, $data)->render());

        $absolute = storage_path('app/public/' . $relativePath);

        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }

        $mpdf->Output($absolute, Destination::FILE);

        return $relativePath;
    }

    /**
     * Render a Blade view and return the raw PDF bytes.
     */
    public function renderToString(string $view, array $data): string
    {
        $this->ensureTempDir();

        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * mPDF needs a writable scratch directory for its font cache.
     */
    protected function ensureTempDir(): void
    {
        $dir = storage_path('app/mpdf');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
