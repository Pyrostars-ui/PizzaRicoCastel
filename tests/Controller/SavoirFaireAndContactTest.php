<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(HomeController::class)]
final class SavoirFaireAndContactTest extends WebTestCase
{
    public function testSavoirFairePageRendersCorrectly(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/notre-savoir-faire');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        // Vérification des 4 compteurs métriques
        $responseContent = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('485°C', $responseContent);
        self::assertStringContainsString('72h', $responseContent);
        self::assertStringContainsString('68%', $responseContent);
        self::assertStringContainsString('100%', $responseContent);

        // Vérification des 3 Piliers
        self::assertStringContainsString('Le Four à Bois Réfractaire', $responseContent);
        self::assertStringContainsString('La Maturation Lente de 72 Heures', $responseContent);
        self::assertStringContainsString('Trésors AOP &amp; IGP d\'Italie', $responseContent);

        // Vérification de la gestuelle Schiaffo
        self::assertStringContainsString('Technique du « Schiaffo »', $responseContent);
        self::assertStringContainsString('Le Geste Vif, Sans Compromis ni Rouleau', $responseContent);

        // Vérification de la timeline historique
        self::assertGreaterThan(0, $crawler->filter('#histoire')->count());
        self::assertStringContainsString('1995', $responseContent);
        self::assertStringContainsString('2012', $responseContent);
        self::assertStringContainsString('2024', $responseContent);

        // Vérification des portraits d'équipe
        self::assertStringContainsString('Rico Marchetti', $responseContent);
        self::assertStringContainsString('Marco Ferri', $responseContent);
        self::assertStringContainsString('Giulia Vandi', $responseContent);

        // Vérification de la bannière finale & CTA
        self::assertStringContainsString('Venez Goûter la Différence Ce Soir', $responseContent);
        self::assertStringContainsString('05 63 32 14 43', $responseContent);
    }

    public function testAProposRouteIsAnAliasForSavoirFaire(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);
        self::assertGreaterThan(0, $crawler->filter('#histoire')->count());
        self::assertStringContainsString('485°C', (string) $client->getResponse()->getContent());
    }

    public function testContactPageRendersCorrectly(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $responseContent = (string) $client->getResponse()->getContent();

        // En-tête et coordonnées réelles
        self::assertStringContainsString('Nous Trouver &amp;', $responseContent);
        self::assertStringContainsString('7bis place des Belges', $responseContent);
        self::assertStringContainsString('82100 Castelsarrasin', $responseContent);
        self::assertStringContainsString('05 63 32 14 43', $responseContent);

        // Statut et bento accessibilité
        self::assertStringContainsString('OUVERT CE SOIR', $responseContent);
        self::assertStringContainsString('18h30 - 22h30', $responseContent);
        self::assertStringContainsString('Parking Belges', $responseContent);
        self::assertStringContainsString('Accès Plain-Pied', $responseContent);

        // Carte et indicateurs spatiaux
        self::assertStringContainsString('Place des Belges, Castelsarrasin', $responseContent);
        self::assertStringContainsString('À 3 min du Pont Canal', $responseContent);

        // Formulaire de réservation
        self::assertGreaterThan(0, $crawler->filter('#reservationForm')->count());
        self::assertGreaterThan(0, $crawler->filter('#clientName')->count());
        self::assertGreaterThan(0, $crawler->filter('#clientPhone')->count());
        self::assertGreaterThan(0, $crawler->filter('#bookingDate')->count());
        self::assertGreaterThan(0, $crawler->filter('#bookingTime')->count());
        self::assertGreaterThan(0, $crawler->filter('#submitBtn')->count());

        // FAQ pratique
        self::assertStringContainsString('Questions Fréquentes', $responseContent);
        self::assertStringContainsString('Quels sont les moyens de paiement acceptés ?', $responseContent);
        self::assertStringContainsString('Comment fonctionne le retrait à emporter ?', $responseContent);
        self::assertStringContainsString('Proposez-vous la livraison à domicile ?', $responseContent);
        self::assertStringContainsString('Avez-vous des options végétariennes ou sans porc ?', $responseContent);
    }

    public function testContactFormPostRequest(): void
    {
        $client = self::createClient();
        $client->request('POST', '/contact', [
            'clientName' => 'Jean Dupont',
            'clientPhone' => '0612345678',
            'partyGuests' => '4',
            'bookingDate' => '2026-11-01',
            'bookingTime' => '20:30',
            'specialRequests' => 'Table près de la baie vitrée',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);
    }

    public function testSeoMetadataOnSavoirFaireAndContactPages(): void
    {
        $client = self::createClient();

        $crawlerSavoirFaire = $client->request('GET', '/notre-savoir-faire');
        self::assertResponseIsSuccessful();
        $savoirFaireTitle = $crawlerSavoirFaire->filter('title')->text();
        self::assertNotEmpty($savoirFaireTitle);
        self::assertStringContainsString('Pizza Rico', $savoirFaireTitle);

        $crawlerContact = $client->request('GET', '/contact');
        self::assertResponseIsSuccessful();
        $contactTitle = $crawlerContact->filter('title')->text();
        self::assertNotEmpty($contactTitle);
        self::assertStringContainsString('Pizza Rico', $contactTitle);
    }
}
