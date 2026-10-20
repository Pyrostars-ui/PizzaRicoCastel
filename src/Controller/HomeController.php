<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\News;
use App\Repository\AboutRepository;
use App\Repository\BoissonRepository;
use App\Repository\HeroRepository;
use App\Repository\NewsRepository;
use App\Repository\PizzaRepository;
use App\Repository\SEORepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur gérant les pages principales du site destinées aux visiteurs.
 */
final class HomeController extends AbstractController
{
    /**
     * @var list<array{
     *     id: string,
     *     name: string,
     *     price: float,
     *     description: string,
     *     image: string,
     *     category: string,
     *     badge: string
     * }>
     */
    public const array DOLCI_DATA = [
        [
            'id' => 'tiramisu-illy',
            'name' => 'Tiramisù au Café Illy',
            'price' => 6.00,
            'description' => 'Recette vénitienne authentique : biscuits savoiardi imbibés d\'expresso Illy, mascarpone battu à froid et cacao amer extra-brut.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAt5IbgwCMPyagOr6pPZciIZxroyyxE6mufSPqoWD3WT_ZJc94CuOKZupdfAYG-aYUSqb6rVzBKbQpxqv7CpwBAIXUd2DoWud0HuU-J3fak41JhSTt9QHtoybBzesYoaG3900tePrlpZSNxZfcgvGzNB6Bubk2jqXladir19ko05Lk5zL6LXiOkpOXV1SacbgphicuO-yqS5I4bj-ZIstGQNsU_-oWrx3ImMWBQpgsH1cfvZ3gU3Fofhw',
            'category' => 'dessert',
            'badge' => 'Fait Maison',
        ],
        [
            'id' => 'calzone-nutella',
            'name' => 'Calzone Nutella & Noisettes',
            'price' => 6.50,
            'description' => 'Pâton minute cuit au four à bois, fourré au Nutella coulant et éclats de noisettes IGP du Piémont torréfiées.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCI0h54nb0d3-x4tsEcs8ERyhi2zwMAIfTk3wTxij95EIBMeQPfbTfoZF6MAQq7PV5-E4GhZ48OrAPVilZyJmao6sb3qyIkPvob_JhtRIOfLblvMOvmcNxuAl4KFBUfWaCcSTEbXyc8iP_Zi9I9wGHZpgmqHXQIiLhVdMhoiLc6PcyzkMNgtD7Kl9eWQiCKWrNsStPveYYgFrUYPqIQOH8ctOjg-AQIdyTkvmZOa69jVYcFXehb-S0b-g',
            'category' => 'dessert',
            'badge' => 'Au Four à Bois',
        ],
        [
            'id' => 'panna-cotta-vanille',
            'name' => 'Panna Cotta Vanille',
            'price' => 5.50,
            'description' => 'Crème fraîche entière infusée à la gousse de vanille Bourbon de Madagascar, coulis maison de fruits rouges acidulés.',
            'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDI9O0fh_cURs90KpqrVVEgsxaJJuJ-ULvMif-jYA2Cexz5TEc6WcZocHL52Dm0-USCkwGEGTY-_3qwUjAlUvqwtE1i2pn5Un7rIqphRRUaNifOkaXHxyj7iJIZIEfxvY357jEoGhEpCQChs7oBXEalBqxa3L6eqcJdZlpj0MDUuaA8MQ5kvIuf04eThMwjt871E7bpYgmdNtuLfsUX0gWwubDHD8OUQNrcN6cGT6K3TdrZkTIBLnIlhg',
            'category' => 'dessert',
            'badge' => 'Vanille Bourbon',
        ],
    ];

    /**
     * Affiche la page d'accueil avec les pizzas spéciales, le héros, À propos et Actualités.
     */
    #[Route('/', name: 'app_home')]
    public function index(
        PizzaRepository $pizzaRepo,
        HeroRepository $heroRepo,
        AboutRepository $aboutRepo,
        NewsRepository $newsRepo,
        SEORepository $seoRepo,
    ): Response {
        $specialPizzas = $pizzaRepo->findSpecialOrderByPriceAsc();
        $allPizzas = $pizzaRepo->findAllOrderByPriceAsc();
        $hero = $heroRepo->findOneBy([], ['id' => 'ASC']);
        $about = $aboutRepo->findOneBy([]);
        $news = $newsRepo->findBy([], ['createdAt' => 'DESC'], 3);
        $seo = $seoRepo->findOneBy(['pageName' => 'home']);

        /** @var array<string, \App\Entity\Pizza> $pizzasBySlug */
        $pizzasBySlug = [];
        foreach ($allPizzas as $pizza) {
            $pizzasBySlug[$pizza->getSlug()] = $pizza;
        }

        return $this->render('home/index.html.twig', [
            'specialPizzas' => $specialPizzas,
            'allPizzas' => $allPizzas,
            'pizzasBySlug' => $pizzasBySlug,
            'hero' => $hero,
            'about' => $about,
            'news' => $news,
            'seo' => $seo,
        ]);
    }

    /**
     * Affiche la carte complète des pizzas, desserts et boissons avec le panier interactif.
     */
    #[Route('/la-carte', name: 'app_carte', methods: ['GET'])]
    #[Route('/nos-pizzas', name: 'app_pizzas', methods: ['GET'])]
    public function carte(
        PizzaRepository $pizzaRepo,
        BoissonRepository $boissonRepo,
        SEORepository $seoRepo,
    ): Response {
        $pizzas = $pizzaRepo->findAllOrderByPriceAsc();
        $boissons = $boissonRepo->findAllOrderByPriceAsc();
        $seo = $seoRepo->findOneBy(['pageName' => 'la-carte']) ?? $seoRepo->findOneBy(['pageName' => 'pizzas']);

        return $this->render('home/pizzas.html.twig', [
            'pizzas' => $pizzas,
            'boissons' => $boissons,
            'dolci' => self::DOLCI_DATA,
            'title' => 'La Carte des Créations au Feu de Bois - Pizza Rico',
            'seo' => $seo,
        ]);
    }

    /**
     * Affiche la page listant toutes les boissons disponibles.
     */
    #[Route('/nos-boissons', name: 'app_boissons')]
    public function boissons(BoissonRepository $boissonRepo, SEORepository $seoRepo): Response
    {
        $boissons = $boissonRepo->findAllOrderByPriceAsc();
        $seo = $seoRepo->findOneBy(['pageName' => 'boissons']);

        return $this->render('home/boissons.html.twig', [
            'boissons' => $boissons,
            'title' => 'Nos Boissons - Pizza Rico',
            'seo' => $seo,
        ]);
    }

    /**
     * Affiche le détail d'une actualité.
     */
    #[Route('/actualite/{slug}', name: 'app_news_detail', methods: ['GET'])]
    public function newsDetail(News $news): Response
    {
        return $this->render('home/news_detail.html.twig', [
            'item' => $news,
            'title' => $news->getTitle().' - Pizza Rico',
        ]);
    }

    /**
     * Affiche la page "Notre Savoir-Faire & L'Histoire".
     * Accessible via /notre-savoir-faire et /a-propos.
     */
    #[Route('/notre-savoir-faire', name: 'app_savoir_faire', methods: ['GET'])]
    #[Route('/a-propos', name: 'app_about', methods: ['GET'])]
    public function about(AboutRepository $aboutRepo, SEORepository $seoRepo): Response
    {
        $about = $aboutRepo->findOneBy([]);
        $seo = $seoRepo->findOneBy(['pageName' => 'notre-savoir-faire'])
            ?? $seoRepo->findOneBy(['pageName' => 'about']);

        return $this->render('home/about.html.twig', [
            'about' => $about,
            'title' => 'Notre Savoir-Faire & L\'Histoire - Pizza Rico',
            'seo' => $seo,
        ]);
    }

    /**
     * Affiche la page Contact, Accès & Réservations.
     */
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(SEORepository $seoRepo): Response
    {
        $seo = $seoRepo->findOneBy(['pageName' => 'contact']);

        return $this->render('home/contact.html.twig', [
            'title' => 'Contact, Accès & Réservations - Pizza Rico',
            'seo' => $seo,
            'hotline' => '05 63 32 14 43',
            'address' => [
                'street' => '7bis place des Belges',
                'postalCode' => '82100',
                'city' => 'Castelsarrasin',
                'country' => 'France',
                'parking' => 'Parking gratuit à 20 mètres (>80 places)',
            ],
            'hours' => [
                'days' => 'Mardi au Dimanche',
                'lunch' => '11:45 • 14:00',
                'dinner' => '18:30 • 22:30',
                'closed' => 'Lundi',
            ],
        ]);
    }

    /**
     * Affiche la liste de toutes les actualités.
     */
    #[Route('/actualites', name: 'app_news')]
    public function news(NewsRepository $newsRepo, SEORepository $seoRepo): Response
    {
        $news = $newsRepo->findBy([], ['createdAt' => 'DESC']);
        $seo = $seoRepo->findOneBy(['pageName' => 'news']);

        return $this->render('home/news.html.twig', [
            'news' => $news,
            'title' => 'Nos Actualités - Pizza Rico',
            'seo' => $seo,
        ]);
    }
}
