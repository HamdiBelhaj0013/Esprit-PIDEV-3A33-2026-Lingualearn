<?php

namespace App\Media;

use setasign\Fpdi\Tcpdf\Fpdi;

final class PdfWatermarkService
{
    /**
     * @param string $inputPdfAbsolutePath  Chemin PDF original
     * @param string $outputPdfAbsolutePath Chemin PDF watermarké généré
     * @param string $watermarkText         Texte watermark (ex: "Téléchargé par X - date")
     */
    public function watermark(string $inputPdfAbsolutePath, string $outputPdfAbsolutePath, string $watermarkText): void
    {
        if (!is_file($inputPdfAbsolutePath)) {
            throw new \RuntimeException('PDF source introuvable: ' . $inputPdfAbsolutePath);
        }

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($inputPdfAbsolutePath);

        // réglages simples (tu peux ajuster)
        $pdf->SetCreator('YourPlatform');
        $pdf->SetAuthor('YourPlatform');
        $pdf->SetTitle('Watermarked PDF');

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            // Watermark semi transparent
            // TCPDF supporte SetAlpha
            $pdf->SetAlpha(0.15);
            $pdf->SetFont('helvetica', 'B', 26);

            // position (au milieu)
            $pdf->SetTextColor(0, 0, 0);

            // Rotation watermark: TCPDF a StartTransform/Rotate
            $pdf->StartTransform();
            $pdf->Rotate(35, $size['width'] / 2, $size['height'] / 2);

            // texte centré
            $pdf->SetXY(0, $size['height'] / 2);
            $pdf->Cell($size['width'], 0, $watermarkText, 0, 1, 'C');

            $pdf->StopTransform();
            $pdf->SetAlpha(1);
        }

        // Sauvegarde sur disque
        $pdf->Output($outputPdfAbsolutePath, 'F');
    }
}