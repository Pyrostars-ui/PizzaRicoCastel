# Project: Pizza Rico Redesign — Artisanal Ember & Stone

## Architecture
- **Framework & Runtime** : Symfony 7.4 LTS, PHP 8.4 (`declare(strict_types=1);`), FrankenPHP Worker Mode (aucun état statique mutable, ResetInterface si requis).
- **Environnement** : Docker-Only (`docker compose exec -T app <cmd>`).
- **Frontend & Design System** : Thème sombre "Artisanal Ember & Stone", Tailwind CDN avec nonce CSP dynamique (`SecurityHeadersSubscriber`), Google Fonts (`Playfair Display`, `Plus Jakarta Sans`), `Material Symbols Outlined`.
- **Architecture Twig** : Découpage modulaire en composants réutilisables (`templates/Components/`), aucune donnée métier codée en dur.
- **Backend & Persistance** : Entités Doctrine (`Pizza`, `Boisson`, `About`, `Hero`, `SEO`, `News`).

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Design System & Tokens | Thème "Artisanal Ember & Stone" (palette volcanique, Playfair Display, Plus Jakarta Sans, ombres braises, CSP nonce) | M1 | DESIGN.md / Survey |
| 2 | TopNavBar & Footer unifiés | Barre fixe floutée avec logo, liens actifs, hotline 05 63 32 14 43, badge panier et footer 4 colonnes complet | M1 | Pages 1-5 / Survey |
| 3 | Doctrine Seeding & Data Binding | Peuplement base PostgreSQL (pizzas des 4 architectures, hero, about, boissons) et liaison contrôleurs | M1 | Explorer 3 / Survey |
| 4 | Hero Dramatique 450°C | Section d'accueil à 450°C avec halo braise, badges traditionnels et 4 compteurs (72h, 450°C, 100%, 4.9/5) | M2 | Page 1 / Survey |
| 5 | 4 Architectures Radicales | Showcase des 4 styles de cartes (Onglets interactifs, Storyboard défilant, Split 50/50, Filigrane typographique) | M2 | Page 1 / Survey |
| 6 | La Carte Complète & Filtres | Catalogue asymétrique (pizzas, dolci, boissons) avec filtres par catégorie, recherche libre et base tomate/crème | M3 | Page 2 / Survey |
| 7 | Sélecteur Format 29cm/33cm | Bascule instantanée de diamètre avec recalcul dynamique du prix (+2.50€) sur les cartes et boutons CTA | M3 | Pages 1-2 / Survey |
| 8 | Panier Interactif & Calculs | Sidebar panier en temps réel : ajout/retrait d'articles, sous-total HT, TVA 10%, Total TTC et délai 15-20 min | M3 | Page 2 / Survey |
| 9 | Savoir-Faire 72h & Histoire 1995 | Récit des 3 piliers (four 485°C, maturation 72h, AOP), spotlight geste "Le Schiaffo", timeline 1995-2024 et portraits équipe | M4 | Page 3 / Survey |
| 10 | Contact, Accès & Réservations | Statut fournil ouvert, horaires complets, bento parking/PMR, carte sombre centrée 7bis place des Belges, formulaire de réservation | M4 | Page 4 / Survey |
| 11 | Finaliser Commande & Checkout | Stepper en 4 étapes, sélection mode retrait (emporter vs livraison), créneau 15 min, coordonnées client SMS four, paiement CB/Comptoir | M5 | Page 5 / Survey |
| 12 | Validation Statique & Conformité | Validation syntaxe Twig (lint:twig), conteneur Symfony (lint:container), PHPStan niveau 8 (0 erreur), suite PHPUnit | M6 | ORIGINAL_REQUEST / Survey |
| 13 | Audit Visuel Browser E2E | Vérification Playwright / browser sur Desktop (1280px) et Mobile (390px) : HTTP 200, contrastes, fluidité, zéro erreur console | M6 | ORIGINAL_REQUEST / Survey |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Design System, Base Layout & Data Seeding | `templates/base.html.twig`, Tailwind CDN + CSP nonces, Google Fonts, Material Symbols, `Components/navbar/`, `Components/footer/`, commande/fixtures Doctrine pour peupler les données requises | none | DONE |
| M2 | Page Accueil & Showcase 4 Architectures (/) | Gabarit `templates/home/index.html.twig`, Hero 450°C, 4 compteurs, barre de recherche/filtres sticky, 4 styles de cartes radicales liés aux entités `Pizza` et `Hero` | M1 | DONE |
| M3 | Page La Carte & Panier (/la-carte) | Gabarit `templates/home/pizzas.html.twig` (route `/la-carte` et `/nos-pizzas`), grille 12 colonnes, filtres catégories/base, variation 29cm/33cm, panier interactif avec TVA 10% | M1 | DONE |
| M4 | Savoir-Faire & Contact (/notre-savoir-faire, /contact) | Gabarits `templates/home/about.html.twig` (3 piliers, Schiaffo, timeline, équipe) et `templates/home/contact.html.twig` (accès, horaires, carte sombre, formulaire réservation) | M1 | DONE |
| M5 | Checkout & Paiement (/commande/finaliser) | Gabarit `templates/order/checkout.html.twig` (route `/commande/finaliser`), tunnel 4 étapes, créneaux 15 min, coordonnées client, choix paiement | M1, M3 | DONE |
| M6 | Validation Statique & Audit Visuel Browser | Linters (`lint:twig`, `lint:container`), PHPStan niveau 8, tests PHPUnit et audit visuel navigateur (Desktop 1280px / Mobile 390px) | M2, M3, M4, M5 | DONE |

## Interface Contracts
### base.html.twig ↔ Pages Twig
- `base.html.twig` expose les blocs : `{% block title %}`, `{% block meta %}`, `{% block stylesheets %}`, `{% block importmap %}`, `{% block body %}`, `{% block javascripts %}`.
- Le conteneur `<main>` est pleine largeur fluide (`w-full overflow-x-hidden min-h-screen bg-background text-on-surface`).
- Nonce CSP injecté automatiquement sur tout script/style inline via `app.request.attributes.get('csp_nonce')`.

### Contrôleurs ↔ Gabarits Twig
- `HomeController::index()` : transmet `pizzas` (liste d'entités Pizza), `hero` (entité Hero ou fallback), `categories`, `cartCount`.
- `HomeController::carte()` (`/la-carte` / `/nos-pizzas`) : transmet `pizzas`, `boissons`, `categories`, `cartCount`.
- `HomeController::savoirFaire()` (`/notre-savoir-faire` / `/a-propos`) : transmet `about` (entité About ou fallback).
- `HomeController::contact()` (`/contact`) : transmet coordonnées, horaires, statut ouverture.
- `OrderController::checkout()` (`/commande/finaliser`) : gère l'affichage du stepper et du panier à finaliser.

### Client-side Interactivity (Panier & Sélecteur 29cm/33cm)
- Attributs HTML standardisés sur les cartes : `data-pizza-id="{{ pizza.id }}"`, `data-price-medium="{{ pizza.priceMedium }}"`, `data-price-large="{{ pizza.priceLarge }}"`.
- Panier géré côté client (localStorage / session) synchronisé avec le badge `#cartCount` de la Navbar.

## Code Layout
- `templates/base.html.twig` : Gabarit racine intégrant Tailwind CDN, configuration Ember & Stone, polices et symboles.
- `templates/Components/navbar/navbar.html.twig` : En-tête responsive unifié.
- `templates/Components/footer/footer.html.twig` : Pied de page unifié 4 colonnes.
- `templates/home/index.html.twig` : Page 1 (Accueil & Showcase 4 styles).
- `templates/home/carte.html.twig` ou `pizzas.html.twig` : Page 2 (La Carte & Panier).
- `templates/home/about.html.twig` : Page 3 (Notre Savoir-Faire & Histoire).
- `templates/home/contact.html.twig` : Page 4 (Contact, Accès & Réservations).
- `templates/order/checkout.html.twig` : Page 5 (Finaliser commande & Paiement).
- `templates/Components/checkout/` : 6 composants modulaires (stepper, pickup_delivery_toggle, time_slots_grid, customer_form, payment_methods, order_summary_sidebar).
- `src/Controller/HomeController.php` & `src/Controller/OrderController.php` : Contrôleurs typés PHP 8.4 avec attributs `#[Route]`.
- `src/Command/SeedDataCommand.php` : Commande Symfony pour insérer les données réelles (8 pizzas, hero, about, seo) dans PostgreSQL.
