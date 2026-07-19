<?php

namespace App\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeController extends AbstractController
{
    public function __construct(
        private GameRepository $gameRepository,
    ) {}

    #[Route('/qr-code/game/{gameId}', name: 'app_qr_code_game', requirements: ['gameId' => '\d+'])]
    public function generateGameQrCode(int $gameId): Response
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game) {
            return new Response('', 404, ['Content-Type' => 'image/png']);
        }

        if ($game->isQuizGame()) {
            $url = $this->generateUrl('app_quiz_mobile', ['gameId' => $gameId], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif ($game->isStopwatchGame()) {
            $url = $this->generateUrl('app_stopwatch_mobile', ['gameId' => $gameId], UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            $url = $this->generateUrl('app_player_access', ['olympixId' => $game->getOlympix()->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->renderQrPng($url);
    }

    #[Route('/qr-code/{olympixId}', name: 'app_qr_code', requirements: ['olympixId' => '\d+'])]
    public function generateQrCode(string $olympixId): Response
    {
        try {
            // URL generieren, die im QR Code enthalten sein soll (absolute URL inkl. Domain!)
            $url = $this->generateUrl('app_player_access', ['olympixId' => $olympixId], UrlGeneratorInterface::ABSOLUTE_URL);

            return $this->renderQrPng($url);
        } catch (\Exception $e) {
            // Fallback: Leeres PNG oder Fehler-Bild
            return new Response(
                '',
                404,
                ['Content-Type' => 'image/png']
            );
        }
    }

    private function renderQrPng(string $url): Response
    {
        // endroid/qr-code v6: Builder wird über den Konstruktor konfiguriert
        $builder = new Builder(
            writer: new PngWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = $builder->build();

        return new Response(
            $result->getString(),
            200,
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="qr-code.png"',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}