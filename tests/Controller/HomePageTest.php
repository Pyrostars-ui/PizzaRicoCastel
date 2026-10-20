<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(HomeController::class)]
final class HomePageTest extends WebTestCase
{
    public function testHomePageRendersHeroWithBadgesAndCounters(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        // 1. Hero Section & Badges
        $responseContent = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Four au feu de bois 450°C', $responseContent);
        self::assertStringContainsString('Farine bio T00', $responseContent);
        self::assertStringContainsString('Depuis 1995 • Castelsarrasin', $responseContent);

        // 2. The 4 Live Metric Counters
        self::assertStringContainsString('data-counter-target="72"', $responseContent);
        self::assertStringContainsString('data-counter-target="450"', $responseContent);
        self::assertStringContainsString('data-counter-target="100"', $responseContent);
        self::assertStringContainsString('data-counter-target="4.9"', $responseContent);

        // 3. Sticky Filter & Search Bar
        self::assertStringContainsString('id="carte"', $responseContent);
        self::assertStringContainsString('id="pizzaSearch"', $responseContent);
        self::assertStringContainsString('Toutes nos pizzas', $responseContent);
        self::assertStringContainsString('Les 4 Architectures', $responseContent);

        // 4. Showcase des 4 Architectures de Cartes Radicales
        // Style 01
        self::assertStringContainsString('Architecture Style 01', $responseContent);
        self::assertStringContainsString('La Masterpiece Interactive &amp; Tactile', $responseContent);
        self::assertStringContainsString('La Tartufata Di Bosco', $responseContent);
        self::assertStringContainsString('tab-visual-btn', $responseContent);
        self::assertStringContainsString('tab-recipe-btn', $responseContent);
        self::assertStringContainsString('tab-profile-btn', $responseContent);

        // Style 02
        self::assertStringContainsString('Architecture Style 02', $responseContent);
        self::assertStringContainsString('Reine d&#039;Italie Suprême', $responseContent);
        self::assertStringContainsString('Margherita Verace STG', $responseContent);
        self::assertStringContainsString('custom-card-scrollbar', $responseContent);

        // Style 03
        self::assertStringContainsString('Architecture Style 03', $responseContent);
        self::assertStringContainsString('Burrata &amp; Pesto Genovese', $responseContent);
        self::assertStringContainsString('Quattro Formaggi Nobile', $responseContent);
        self::assertStringContainsString('Base Coulis', $responseContent);

        // Style 04
        self::assertStringContainsString('Architecture Style 04', $responseContent);
        self::assertStringContainsString('Parma &amp; Stracciatella', $responseContent);
        self::assertStringContainsString('Diavola Fumée Calabra', $responseContent);
        self::assertStringContainsString('rad-watermark-a', $responseContent);
        self::assertStringContainsString('rad-watermark-b', $responseContent);

        // 5. Les 3 Piliers
        self::assertStringContainsString('Notre Savoir-Faire', $responseContent);
        self::assertStringContainsString('Four à Bois Traditionnel', $responseContent);
        self::assertStringContainsString('Farine Tipo 00 &amp; Levain Naturel', $responseContent);
        self::assertStringContainsString('Produits AOP &amp; IGP Frais', $responseContent);

        // 6. Contact & Accès Rapide
        self::assertStringContainsString('Venez Nous Rendre Visite', $responseContent);
        self::assertStringContainsString('05 63 32 14 43', $responseContent);
        self::assertStringContainsString('7bis place des Belges', $responseContent);
        self::assertStringContainsString('Parking Gratuit', $responseContent);
    }
}
