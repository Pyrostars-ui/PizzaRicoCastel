<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Controller\Admin\AdminAboutController;
use App\Controller\Admin\AdminBoissonController;
use App\Controller\Admin\AdminHeroController;
use App\Controller\Admin\AdminMentionsLegalesController;
use App\Controller\Admin\AdminNewsController;
use App\Controller\Admin\AdminPizzaController;
use App\Controller\Admin\AdminSEOController;
use App\Controller\Admin\AdminUserController;
use App\Controller\Admin\DashboardController;
use App\Entity\Boisson;
use App\Entity\News;
use App\Entity\Pizza;
use App\Entity\User;
use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[CoversClass(SecurityHeadersSubscriber::class)]
#[CoversClass(User::class)]
#[CoversClass(Pizza::class)]
#[CoversClass(Boisson::class)]
#[CoversClass(News::class)]
final class SecurityHardeningTest extends TestCase
{
    /**
     * @param class-string $controllerClass
     */
    #[DataProvider('provideAdminControllers')]
    public function testAdminControllersEnforceRoleAdminAttribute(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);
        $attributes = $reflection->getAttributes(IsGranted::class);

        self::assertNotEmpty(
            $attributes,
            \sprintf('Le contrôleur %s doit posséder #[IsGranted("ROLE_ADMIN")].', $controllerClass),
        );

        $instance = $attributes[0]->newInstance();
        self::assertSame('ROLE_ADMIN', $instance->attribute);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideAdminControllers(): iterable
    {
        yield 'AdminAboutController' => [AdminAboutController::class];
        yield 'AdminBoissonController' => [AdminBoissonController::class];
        yield 'AdminHeroController' => [AdminHeroController::class];
        yield 'AdminMentionsLegalesController' => [AdminMentionsLegalesController::class];
        yield 'AdminNewsController' => [AdminNewsController::class];
        yield 'AdminPizzaController' => [AdminPizzaController::class];
        yield 'AdminSEOController' => [AdminSEOController::class];
        yield 'AdminUserController' => [AdminUserController::class];
        yield 'DashboardController' => [DashboardController::class];
    }

    public function testSecurityHeadersAndCspNonceAreInjected(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $kernel = self::createStub(HttpKernelInterface::class);
        $request = Request::create('https://pizzarico-castelsarrasin.example.fr/');
        $response = new Response('<html></html>');

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        $nonce = $request->attributes->get('csp_nonce');
        self::assertIsString($nonce);
        self::assertNotEmpty($nonce);

        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertNotEmpty($response->headers->get('Strict-Transport-Security'));

        $csp = $response->headers->get('Content-Security-Policy');
        self::assertIsString($csp);
        self::assertStringContainsString(\sprintf("'nonce-%s'", $nonce), $csp);
    }

    public function testUserIdentifierRequiresNonEmptyEmail(): void
    {
        $user = new User();
        $user->setEmail('contact@pizzarico.fr');

        self::assertSame('contact@pizzarico.fr', $user->getUserIdentifier());

        $emptyUser = new User();
        $this->expectException(\LogicException::class);
        $emptyUser->getUserIdentifier();
    }

    public function testEntitiesGenerateSlugSafely(): void
    {
        $pizza = new Pizza();
        $pizza->setName('Reine Spéciale !');
        $pizza->generateSlug();
        self::assertSame('reine-speciale', $pizza->getSlug());

        $boisson = new Boisson();
        $boisson->setName('Coca Cola 33cl');
        $boisson->generateSlug();
        self::assertSame('coca-cola-33cl', $boisson->getSlug());

        $news = new News();
        $news->setTitle('Soirée Four à Bois');
        $news->generateSlug();
        self::assertSame('soiree-four-a-bois', $news->getSlug());
    }
}
