<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final class LiensPublicsExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'app.canonical_base_url')]
        private readonly string $baseUrl,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    #[AsTwigFunction(name: 'url_canonique')]
    public function urlCanonique(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            throw new \LogicException('Une requête est nécessaire pour construire le lien canonique.');
        }

        $base = rtrim($this->baseUrl, '/');
        $parts = parse_url($base);
        $host = strtolower($parts['host'] ?? '');
        $valid = false !== $parts
            && 'https' === ($parts['scheme'] ?? '')
            && '' !== $host
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && !str_contains($host, 'example.')
            && !str_ends_with($host, '.onrender.com')
            && !str_ends_with($host, '.vercel.app')
            && 'localhost' !== $host
            && false === filter_var($host, \FILTER_VALIDATE_IP);

        if (!$valid) {
            if ('prod' === $this->environment) {
                throw new \LogicException('Configurez CANONICAL_BASE_URL avec le domaine public HTTPS.');
            }
            $base = $request->getSchemeAndHttpHost().$request->getBaseUrl();
        }

        return $base.$request->getPathInfo();
    }
}
