<?php

namespace App\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QrCodeController extends AbstractController
{
    #[Route('/qr-code/{olympixId}', name: 'app_qr_code')]
    public function generateQrCode(string $olympixId): Response
    {
        try {
            // URL generieren, die im QR Code enthalten sein soll
            $url = $this->generateUrl('app_player_access', ['olympixId' => $olympixId], true);
            
            // QR Code erstellen
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data($url)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(200)
                ->margin(10)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->build();

            // Als PNG Response zurückgeben
            return new Response(
                $result->getString(),
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' => 'inline; filename="qr-code.png"',
                    'Cache-Control' => 'public, max-age=3600', // 1 Stunde Cache
                ]
            );
        } catch (\Exception $e) {
            // Fallback: Leeres PNG oder Fehler-Bild
            return new Response(
                '',
                404,
                ['Content-Type' => 'image/png']
            );
        }
    }
}