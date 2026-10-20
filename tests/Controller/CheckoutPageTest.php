<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\OrderController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(OrderController::class)]
final class CheckoutPageTest extends WebTestCase
{
    /**
     * Valide que la route /commande/finaliser répond HTTP 200 et affiche le stepper 4 étapes.
     */
    public function testCheckoutRouteReturnsHttpOkAndRendersStepper(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();

        // Breadcrumb
        self::assertStringContainsString('Tunnel de Paiement', $content);

        // Stepper 4 étapes
        self::assertStringContainsString('Votre Panier', $content);
        self::assertStringContainsString('Mode &amp; Créneau', $content);
        self::assertStringContainsString('Coordonnées', $content);
        self::assertStringContainsString('Règlement', $content);

        // Étapes numérotées
        self::assertStringContainsString('Étape 1', $content);
        self::assertStringContainsString('Étape 3', $content);
        self::assertStringContainsString('Étape 4', $content);
    }

    /**
     * Valide que les 4 sections du tunnel sont présentes.
     */
    public function testCheckoutRendersAllFourSections(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Section 1: Mode de Réception
        self::assertStringContainsString('Mode de Réception', $content);
        self::assertStringContainsString('À Emporter', $content);
        self::assertStringContainsString('Click &amp; Collect', $content);
        self::assertStringContainsString('Livraison Locale Express', $content);
        self::assertStringContainsString('7bis place des Belges', $content);

        // Section 2: Créneau de Retrait
        self::assertStringContainsString('Créneau de Retrait', $content);
        self::assertStringContainsString('Four Biscotto di Sorrento à 485°C', $content);
        self::assertStringContainsString('Dès que possible', $content);

        // Section 3: Coordonnées
        self::assertStringContainsString('Coordonnées &amp; Suivi Four', $content);
        self::assertStringContainsString('Nom &amp; Prénom', $content);
        self::assertStringContainsString('Téléphone Mobile', $content);
        self::assertStringContainsString('Alerte SMS de sortie de four', $content);
        self::assertStringContainsString('Adresse Email', $content);
        self::assertStringContainsString('Note pour le Pizzaiolo', $content);

        // Section 4: Règlement
        self::assertStringContainsString('Règlement Sécurisé', $content);
        self::assertStringContainsString('Protocole SSL 256-Bit', $content);
        self::assertStringContainsString('Paiement en ligne sécurisé', $content);
        self::assertStringContainsString('Paiement au comptoir', $content);
    }

    /**
     * Valide que les créneaux horaires sont rendus.
     */
    public function testCheckoutRendersTimeSlots(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Créneaux disponibles ce soir', $content);
        self::assertStringContainsString('19h45', $content);
        self::assertStringContainsString('20h00', $content);
        self::assertStringContainsString('20h15', $content);
        self::assertStringContainsString('20h30', $content);
        self::assertStringContainsString('20h45', $content);
        self::assertStringContainsString('21h00', $content);
        self::assertStringContainsString('21h15', $content);
        self::assertStringContainsString('21h30', $content);

        // Boutons de créneau avec data-slot
        self::assertStringContainsString('data-slot="19h45"', $content);
        self::assertStringContainsString('data-slot="21h30"', $content);
    }

    /**
     * Valide que le récapitulatif panier sticky est présent avec les calculs financiers.
     */
    public function testCheckoutRendersOrderSummarySidebar(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Récapitulatif
        self::assertStringContainsString('Votre Commande', $content);
        self::assertStringContainsString('summaryItemsList', $content);
        self::assertStringContainsString('Code promo ou Club Rico', $content);

        // Décomposition financière
        self::assertStringContainsString('Sous-total articles', $content);
        self::assertStringContainsString('summarySubtotal', $content);
        self::assertStringContainsString('Dont TVA', $content);
        self::assertStringContainsString('summaryTax', $content);
        self::assertStringContainsString('Total TTC', $content);
        self::assertStringContainsString('summaryTotal', $content);

        // CTA
        self::assertStringContainsString('Confirmer la Commande', $content);
        self::assertStringContainsString('confirmOrderBtn', $content);

        // Trust badges
        self::assertStringContainsString('Pâte maturée 72 heures', $content);
        self::assertStringContainsString('SMS en temps réel', $content);
        self::assertStringContainsString('05 63 32 14 43', $content);

        // Statut pizzeria ouverte
        self::assertStringContainsString('Pizzeria Ouverte', $content);
        self::assertStringContainsString('18h30 - 22h30', $content);
    }

    /**
     * Valide que le JavaScript de chargement du panier est présent avec nonce CSP.
     */
    public function testCheckoutIncludesCartLoadingScriptWithCspNonce(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Script de chargement du panier depuis localStorage
        self::assertStringContainsString('pizza_rico_cart', $content);
        self::assertStringContainsString('localStorage', $content);
        self::assertStringContainsString('loadCart', $content);
        self::assertStringContainsString('renderSummary', $content);
        self::assertStringContainsString('updateTotals', $content);

        // Nonce CSP sur le script
        self::assertMatchesRegularExpression('/<script nonce="[^"]+">/', $content);
    }

    /**
     * Valide que les champs de paiement CB sont présents.
     */
    public function testCheckoutRendersCardPaymentFields(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('card-number', $content);
        self::assertStringContainsString('card-expiry', $content);
        self::assertStringContainsString('card-cvc', $content);
        self::assertStringContainsString('cardDetailsForm', $content);
        self::assertStringContainsString('credit_card', $content);
        self::assertStringContainsString('contactless', $content);
    }

    /**
     * Valide que les méthodes de paiement au comptoir sont présentes.
     */
    public function testCheckoutRendersCounterPaymentOption(): void
    {
        $client = self::createClient();
        $client->request('GET', '/commande/finaliser');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('value="at_counter"', $content);
        self::assertStringContainsString('value="card_online"', $content);
        self::assertStringContainsString('Espèces, Carte Bancaire', $content);
        self::assertStringContainsString('Swile, Edenred, Bimpli', $content);
    }
}
