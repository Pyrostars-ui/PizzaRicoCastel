<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;

/**
 * Extension Twig personnalisée pour gérer les liens actifs dans la navigation.
 * Permet d'ajouter facilement une classe CSS "active" sur le lien de la page courante.
 */
class ActiveLinkExtension
{
    /**
     * Constructeur de l'extension.
     * L'injection de dépendances de Symfony fournit automatiquement le RequestStack.
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * Vérifie si la route passée en paramètre correspond à la route courante.
     * Retourne la chaîne 'active' si c'est le cas, ce qui est utile pour les classes CSS.
     *
     * @param string $routeName Le nom de la route à tester
     *
     * @return string 'active' si la route correspond, une chaîne vide sinon
     */
    #[AsTwigFilter(name: 'is_active_route')]
    public function isActiveRoute(string $routeName): string
    {
        // Récupère le nom de la route de la requête actuelle
        $currentRoute = $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        // Compare avec la route testée et retourne la classe CSS appropriée
        return $currentRoute === $routeName ? 'active' : '';
    }
}
