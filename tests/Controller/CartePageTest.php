<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(HomeController::class)]
final class CartePageTest extends WebTestCase
{
    /**
     * Valide que la route officielle /la-carte répond HTTP 200 et affiche le ruban narratif.
     */
    public function testCarteRouteReturnsHttpOkAndRendersExpectedAtmosphere(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/la-carte');

        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();

        // Ruban narratif & Badges
        self::assertStringContainsString('Four Rôtissoire à 450°C', $content);
        self::assertStringContainsString('Maturation Lente 72h', $content);
        self::assertStringContainsString("DOP &amp; Terroirs d'Italie", $content);
        self::assertStringContainsString('La Carte des Créations', $content);
        self::assertStringContainsString('au Feu de Bois', $content);

        // Compteurs rapides & Faits
        self::assertStringContainsString('15 - 20 min', $content);
        self::assertStringContainsString('Fournil Ouvert', $content);
        self::assertStringContainsString('À Emporter', $content);
        self::assertStringContainsString('Castelsarrasin • 82100', $content);
    }

    /**
     * Valide que la route alias /nos-pizzas répond également HTTP 200 avec le même catalogue.
     */
    public function testNosPizzasRouteReturnsHttpOkAsRouteAlias(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/nos-pizzas');

        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('La Carte des Créations', $content);
        self::assertStringContainsString('Pizzas Cuites au Feu de Bois', $content);
        self::assertStringContainsString('Dolci Fatti in Casa', $content);
        self::assertStringContainsString('Boissons &amp; Vins Italiens', $content);
    }

    /**
     * Valide que les pizzas issues de PostgreSQL sont affichées avec le sélecteur bi-format 29cm/33cm.
     */
    public function testCataloguePizzasRenderDualPricesAndBiFormatSelector(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/la-carte');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Pizzas phares en base
        self::assertStringContainsString('La Tartufata Di Bosco', $content);
        self::assertStringContainsString('Reine d&#039;Italie Suprême', $content);
        self::assertStringContainsString('Margherita Verace STG', $content);
        self::assertStringContainsString('Burrata &amp; Pesto Genovese', $content);

        // Sélecteur bi-format 29cm vs 33cm et attributs dynamiques
        self::assertStringContainsString('Petite 29cm', $content);
        self::assertStringContainsString('Grande 33cm', $content);
        self::assertStringContainsString('data-base-price=', $content);
        self::assertStringContainsString('data-base="creme"', $content);
        self::assertStringContainsString('data-base="tomate"', $content);
        self::assertStringContainsString('Ajouter au panier', $content);
    }

    /**
     * Valide que les sections Dolci maison et Boissons sont correctement rendues.
     */
    public function testDolciAndBoissonsSectionsAreRendered(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/la-carte');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // 3 Dolci Maison issus de DOLCI_DATA
        self::assertStringContainsString('Tiramisù au Café Illy', $content);
        self::assertStringContainsString('Calzone Nutella &amp; Noisettes', $content);
        self::assertStringContainsString('Panna Cotta Vanille', $content);
        self::assertStringContainsString('6.00 €', $content);
        self::assertStringContainsString('6.50 €', $content);
        self::assertStringContainsString('5.50 €', $content);

        // Boissons & Vins
        self::assertStringContainsString('Boissons &amp; Vins Italiens', $content);
        self::assertStringContainsString('Birra Moretti', $content);
    }

    /**
     * Valide la présence du panier persistant sticky, des calculs financiers et de la hotline.
     */
    public function testStickyCartSidebarAndDirectPhoneOrder(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/la-carte');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Sidebar Panier
        self::assertStringContainsString('id="cart-sidebar"', $content);
        self::assertStringContainsString('data-cart-sidebar', $content);
        self::assertStringContainsString('id="cartCountBadge"', $content);
        self::assertStringContainsString('id="cartItemsList"', $content);
        self::assertStringContainsString('id="cartEmptyNotice"', $content);

        // Décomposition financière
        self::assertStringContainsString('Sous-total articles', $content);
        self::assertStringContainsString('id="cartSubtotal"', $content);
        self::assertStringContainsString('TVA (10%)', $content);
        self::assertStringContainsString('id="cartTax"', $content);
        self::assertStringContainsString('Total TTC', $content);
        self::assertStringContainsString('id="cartTotal"', $content);

        // CTA Confirmer la Commande + code promo
        self::assertStringContainsString('id="orderSubmitBtn"', $content);
        self::assertStringContainsString('Confirmer la Commande', $content);
        self::assertStringContainsString('id="orderSubmitPrice"', $content);
        self::assertStringContainsString('Code promo ou Club Rico', $content);
        self::assertStringContainsString('id="promoCodeInput"', $content);
        self::assertStringContainsString('id="applyPromoBtn"', $content);

        // Commande téléphonique avec vrai numéro Pizza Rico
        self::assertStringContainsString('05 63 32 14 43', $content);
        self::assertStringContainsString('href="tel:0563321443"', $content);

        // Charte qualité
        self::assertStringContainsString('La Charte Qualité Pizza Rico', $content);
        self::assertStringContainsString('repos contrôlé de 72h', $content);
    }

    /**
     * Valide la présence de la barre de filtres interactive et du champ de recherche.
     */
    public function testFilterBarAndLiveSearchElements(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/la-carte');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Barre de filtres et recherche
        self::assertStringContainsString('id="menuSearch"', $content);
        self::assertStringContainsString('Rechercher une pizza', $content);
        self::assertStringContainsString('id="filterAllBases"', $content);
        self::assertStringContainsString('id="filterTomate"', $content);
        self::assertStringContainsString('id="filterCreme"', $content);
        self::assertStringContainsString('Nos Spécialités', $content);
        self::assertStringContainsString('Desserts Maison', $content);

        // Script avec Nonce CSP dynamique
        self::assertMatchesRegularExpression('/<script nonce="[^"]+">/', $content);
        self::assertStringContainsString('localStorage.setItem(\'pizza_rico_cart\'', $content);
        self::assertStringContainsString('renderCart()', $content);
    }
}
