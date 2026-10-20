<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DashboardController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Controller\RegistrationController;
use App\Controller\SecurityController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(HomeController::class)]
#[CoversClass(OrderController::class)]
#[CoversClass(HealthController::class)]
#[CoversClass(SecurityController::class)]
#[CoversClass(RegistrationController::class)]
#[CoversClass(DashboardController::class)]
final class PublicRoutesSmokeTest extends WebTestCase
{
    #[DataProvider('providePublicUrls')]
    public function testPublicRoutesReturnHttpOk(string $url): void
    {
        $client = self::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePublicUrls(): iterable
    {
        yield 'Accueil' => ['/'];
        yield 'Healthcheck' => ['/health'];
        yield 'La carte' => ['/la-carte'];
        yield 'Nos pizzas' => ['/nos-pizzas'];
        yield 'Nos boissons' => ['/nos-boissons'];
        yield 'À propos' => ['/a-propos'];
        yield 'Notre savoir-faire' => ['/notre-savoir-faire'];
        yield 'Contact' => ['/contact'];
        yield 'Checkout' => ['/commande/finaliser'];
        yield 'Actualités' => ['/actualites'];
        yield 'Mentions légales' => ['/mentions-legales'];
        yield 'Connexion' => ['/login'];
        yield 'Inscription' => ['/register'];
    }

    public function testAdminDashboardRedirectsAnonymousUserToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }
}
