<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    /**
     * Renderiza um template PHP e devolve o PDF pronto para stream.
     *
     * @param string $templateAbsPath  Caminho absoluto do template PHP.
     * @param array  $data             Variáveis extraídas para o template.
     * @return string                  Bytes do PDF.
     */
    public function renderFromTemplate(string $templateAbsPath, array $data = []): string
    {
        $html = $this->captureTemplate($templateAbsPath, $data);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function captureTemplate(string $absPath, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $absPath;
        return (string)ob_get_clean();
    }
}
