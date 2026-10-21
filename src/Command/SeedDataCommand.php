<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\About;
use App\Entity\Boisson;
use App\Entity\Hero;
use App\Entity\Pizza;
use App\Entity\SEO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Commande Symfony pour peupler la base de données PostgreSQL avec les données
 * réelles nécessaires au design system "Artisanal Ember & Stone" et
 * aux 5 pages publiques (Pizzas, Hero, About, SEO).
 */
#[AsCommand(
    name: 'app:seed-data',
    description: 'Peuple la base de données avec les données initiales réelles (Pizzas, Hero, About, SEO, Boissons)',
)]
final class SeedDataCommand extends Command
{
    /**
     * @var list<array{
     *     name: string,
     *     ingredient: string,
     *     priceMedium: float,
     *     priceLarge: float,
     *     isSpecial: bool,
     *     image: string
     * }>
     */
    private const array PIZZAS_DATA = [
        [
            'name' => 'La Tartufata Di Bosco',
            'ingredient' => 'Crème de truffe noire d\'Ombrie, fior di latte fondant, champignons de sous-bois poêlés au romarin, Jambon de Parme AOP 24 mois affiné, pousses de roquette fraîche.',
            'priceMedium' => 14.50,
            'priceLarge' => 17.50,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3a62ead58286687746.jpg',
        ],
        [
            'name' => 'Reine d\'Italie Suprême',
            'ingredient' => 'Sauce tomate San Marzano DOP concassée à la main, Mozzarella di Bufala Campana fondante, jambon blanc rôti aux herbes Rovagnati, champignons de Paris frais émincés.',
            'priceMedium' => 12.00,
            'priceLarge' => 15.00,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3abad18c2153219638.jpg',
        ],
        [
            'name' => 'Margherita Verace STG',
            'ingredient' => 'Coulis de tomates San Marzano DOP, Mozzarella di Bufala d\'Agerola fondante, basilic génois frais, huile d\'olive extra-vierge toscane et fleur de sel de Trapani.',
            'priceMedium' => 10.50,
            'priceLarge' => 13.50,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3b1606dcc209029828.jpg',
        ],
        [
            'name' => 'Burrata & Pesto Genovese',
            'ingredient' => 'Burrata fraîche entière des Pouilles (125g) déposée à la sortie du four, tomates datterini confites, pesto de basilic au mortier, pignons de pin torréfiés.',
            'priceMedium' => 14.00,
            'priceLarge' => 17.50,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3b5e4838c968316230.jpg',
        ],
        [
            'name' => 'Quattro Formaggi Nobile',
            'ingredient' => 'Fior di latte d\'Agerola, Gorgonzola Cremoso DOP aux marbrures bleutées, Taleggio affiné de Valsassina, Parmigiano Reggiano 24 mois, filet de miel de fleurs sauvages du Tarn.',
            'priceMedium' => 12.90,
            'priceLarge' => 15.90,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3c0ed1663728896135.jpg',
        ],
        [
            'name' => 'Parma & Stracciatella',
            'ingredient' => 'Coulis San Marzano, Stracciatella di Bufala onctueuse déposée tiède, chiffonnade de Prosciutto di Parma DOP 18 mois, réduction de vinaigre balsamique IGP de Modène.',
            'priceMedium' => 14.90,
            'priceLarge' => 17.90,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3c59b6f91676051120.jpg',
        ],
        [
            'name' => 'Diavola Fumée Calabra',
            'ingredient' => 'Tomate San Marzano, Provola fumée au bois de hêtre d\'Agerola, Spianata piquante de Calabre, basilic frit et filet d\'huile d\'olive macérée aux piments séchés du Mezzogiorno.',
            'priceMedium' => 12.50,
            'priceLarge' => 15.50,
            'isSpecial' => true,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3c9236cfb986666994.jpg',
        ],
        [
            'name' => 'Calzone Tradizionale',
            'ingredient' => 'Pâton soufflé en chausson au four à bois, ricotta fraîche fouettée au poivre noir, fior di latte fondant, jambon cuit aux herbes Rovagnati et concassé de tomates San Marzano.',
            'priceMedium' => 13.00,
            'priceLarge' => 16.00,
            'isSpecial' => false,
            'image' => 'narek-petrosyan-0nj85erlpk-unsplash-1-6a1c3d8089c9a807027357.jpg',
        ],
    ];

    /**
     * @var list<array{
     *     pageName: string,
     *     metaTitle: string,
     *     metaDescription: string
     * }>
     */
    private const array SEO_DATA = [
        [
            'pageName' => 'home',
            'metaTitle' => 'Pizza Rico | Pizzeria Napolitaine Artisanale au Feu de Bois - Castelsarrasin',
            'metaDescription' => 'Pizzeria artisanale à Castelsarrasin depuis 1995. Pizzas napolitaines traditionnelles cuites au feu de bois à 450°C, maturation 72h et ingrédients AOP italiens.',
        ],
        [
            'pageName' => 'pizzas',
            'metaTitle' => 'Nos Pizzas Artisanales au Feu de Bois | Pizza Rico Castelsarrasin',
            'metaDescription' => 'Découvrez notre carte de pizzas napolitaines artisanales : Tartufata, Reine Suprême, Burrata, Diavola et Calzone. Formats 29cm et 33cm.',
        ],
        [
            'pageName' => 'la-carte',
            'metaTitle' => 'La Carte Complète & Commande en Ligne | Pizza Rico',
            'metaDescription' => 'Commandez vos pizzas napolitaines, desserts maison et boissons italiennes en ligne avec retrait rapide au fournil à Castelsarrasin.',
        ],
        [
            'pageName' => 'about',
            'metaTitle' => 'Notre Savoir-Faire & L\'Histoire depuis 1995 | Pizza Rico',
            'metaDescription' => 'L\'histoire de la famille Rico, la maturation 72h, la technique du Schiaffo et la cuisson au four à bois 450°C en pierre de lave.',
        ],
        [
            'pageName' => 'notre-savoir-faire',
            'metaTitle' => 'Notre Savoir-Faire Artisanal & Ingrédients AOP | Pizza Rico',
            'metaDescription' => 'Immersion au cœur de notre fournil napolitain : 3 piliers d\'excellence, produits DOP d\'Italie et passion artisanale.',
        ],
        [
            'pageName' => 'boissons',
            'metaTitle' => 'Nos Boissons & Vins Italiens | Pizza Rico Castelsarrasin',
            'metaDescription' => 'Sélection de boissons italiennes, bières Peroni & Moretti, vins Chianti DOCG, Lambrusco et sodas pour accompagner vos pizzas.',
        ],
        [
            'pageName' => 'contact',
            'metaTitle' => 'Contact, Accès & Réservations | Pizza Rico Castelsarrasin',
            'metaDescription' => 'Retrouvez-nous au 7bis place des Belges à Castelsarrasin. Réservation de table, commande à emporter au 05 63 32 14 43.',
        ],
        [
            'pageName' => 'checkout',
            'metaTitle' => 'Finaliser Votre Commande | Click & Collect Pizza Rico',
            'metaDescription' => 'Choisissez votre créneau de retrait au fournil et réglez votre commande de pizzas artisanales.',
        ],
    ];

    /**
     * @var list<array{
     *     name: string,
     *     price: float,
     *     category: string,
     *     capacity: string,
     *     image: string
     * }>
     */
    private const array BOISSONS_DATA = [
        [
            'name' => 'Coca-Cola Original / Zéro',
            'price' => 2.00,
            'category' => 'Sodas',
            'capacity' => '33cl',
            'image' => 'panel_canettes.jpg',
        ],
        [
            'name' => 'Orangina & Oasis Tropical',
            'price' => 2.00,
            'category' => 'Sodas',
            'capacity' => '33cl',
            'image' => 'panel_canettes.jpg',
        ],
        [
            'name' => 'Lipton Ice Tea & Fanta',
            'price' => 2.00,
            'category' => 'Sodas',
            'capacity' => '33cl',
            'image' => 'panel_canettes.jpg',
        ],
        [
            'name' => 'San Pellegrino Fines Bulles',
            'price' => 2.50,
            'category' => 'Eaux',
            'capacity' => '50cl / 1L',
            'image' => 'panel_eaux.jpg',
        ],
        [
            'name' => 'Sanpellegrino Aranciata Rossa',
            'price' => 2.50,
            'category' => 'Eaux',
            'capacity' => '33cl',
            'image' => 'panel_eaux.jpg',
        ],
        [
            'name' => 'Bouteilles Maxi (Coca-Cola, Oasis, Ice Tea)',
            'price' => 3.50,
            'category' => 'Eaux',
            'capacity' => '1.5L',
            'image' => 'panel_eaux.jpg',
        ],
        [
            'name' => 'Peroni & Heineken Bouteille',
            'price' => 3.50,
            'category' => 'Bières',
            'capacity' => '33cl',
            'image' => 'panel_bieres.jpg',
        ],
        [
            'name' => 'Birra Moretti L\'Autentica',
            'price' => 4.00,
            'category' => 'Bières',
            'capacity' => '33cl',
            'image' => 'panel_bieres.jpg',
        ],
        [
            'name' => 'Desperados & 1664 Blonde',
            'price' => 4.00,
            'category' => 'Bières',
            'capacity' => '33cl',
            'image' => 'panel_bieres.jpg',
        ],
        [
            'name' => 'Lambrusco Dell\'Emilia Rouge & Rosé',
            'price' => 9.50,
            'category' => 'Vins',
            'capacity' => '75cl',
            'image' => 'panel_vins.jpg',
        ],
        [
            'name' => 'Bardolino Chiaretto Rosé DOC',
            'price' => 11.00,
            'category' => 'Vins',
            'capacity' => '75cl',
            'image' => 'panel_vins.jpg',
        ],
        [
            'name' => 'Chianti DOCG Toscane',
            'price' => 12.50,
            'category' => 'Vins',
            'capacity' => '75cl',
            'image' => 'panel_vins.jpg',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Écrase et réinitialise les données existantes (Pizza, Hero, About, SEO, Boisson) avant de réinsérer',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pizza Rico — Peuplement des Données Métier (Artisanal Ember & Stone)');

        $force = (bool) $input->getOption('force');

        $pizzaCount = $this->entityManager->getRepository(Pizza::class)->count([]);
        $heroCount = $this->entityManager->getRepository(Hero::class)->count([]);
        $aboutCount = $this->entityManager->getRepository(About::class)->count([]);
        $seoCount = $this->entityManager->getRepository(SEO::class)->count([]);
        $boissonCount = $this->entityManager->getRepository(Boisson::class)->count([]);

        $totalExisting = $pizzaCount + $heroCount + $aboutCount + $seoCount + $boissonCount;

        if ($totalExisting > 0 && !$force) {
            $io->warning(\sprintf(
                'Des données existent déjà en base (%d pizzas, %d hero, %d about, %d SEO, %d boissons). Utilisez l\'option --force pour écraser et réinsérer.',
                $pizzaCount,
                $heroCount,
                $aboutCount,
                $seoCount,
                $boissonCount,
            ));

            return Command::FAILURE;
        }

        if ($force && $totalExisting > 0) {
            $io->comment('Nettoyage des tables Pizza, Hero, About, SEO et Boisson...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Pizza p')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Hero h')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\About a')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\SEO s')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Boisson b')->execute();
            $this->entityManager->clear();
            $io->note('Données existantes purgées avec succès.');
        }

        // 1. Insertion des Pizzas
        $io->section('1. Insertion des 8 Pizzas Artisanales');
        $pizzaRows = [];

        foreach (self::PIZZAS_DATA as $data) {
            $pizza = new Pizza();
            $pizza->setName($data['name']);
            $pizza->setIngredient($data['ingredient']);
            $pizza->setPriceMedium($data['priceMedium']);
            $pizza->setPriceLarge($data['priceLarge']);
            $pizza->setIsSpecial($data['isSpecial']);
            $pizza->setImage($data['image']);
            $pizza->setUpdatedAt(new \DateTimeImmutable());

            $slug = $this->slugger->slug($data['name'])->lower()->toString();
            $pizza->setSlug($slug);

            $this->entityManager->persist($pizza);

            $pizzaRows[] = [
                $data['name'],
                $slug,
                number_format($data['priceMedium'], 2, ',', ' ').' €',
                number_format($data['priceLarge'], 2, ',', ' ').' €',
                $data['isSpecial'] ? 'Oui (Vitrine)' : 'Non (Carte)',
                $data['image'],
            ];
        }

        $this->entityManager->flush();

        $io->table(
            ['Nom', 'Slug', 'Format 29cm', 'Format 33cm', 'Vitrine', 'Image Asset'],
            $pizzaRows,
        );

        // 2. Insertion du Hero
        $io->section('2. Configuration de la Bannière Hero (450°C)');
        $hero = new Hero();
        $hero->setTitle('L\'Art de la Véritable Pizza Napolitaine au Feu de Bois');
        $hero->setSubTitle('Pâte artisanale maturée 72h & ingrédients frais importés d\'Italie depuis 1995 à Castelsarrasin. Une alchimie pure de passion et de tradition.');
        $hero->setBackground('https://lh3.googleusercontent.com/aida-public/AB6AXuDiWvVIoC2OMEQhN9LClhU0BFDmt9LYYIk9Vi6k3RytDB3VsftMoKE7X3K2nwXEXihOT_K07uoHpaQSEaNGbI6PfvnygrWW24fAHkaLd11cUjKjrwSwIGUI3GoE_R47QTaiOwQIXPvbmzC0BatCM9bLuB0pqlLohr4aIy5j9J-qAH8geHrZWAzftzR1YLwfHDIM05Hc9T-ohTALQpTu955TXFeCqUHvBAaq85SfiW7AhGq5sI215It8yj866dVi_Winic1QImpBemtx');
        $hero->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($hero);
        $this->entityManager->flush();

        $io->definitionList(
            ['Titre' => $hero->getTitle()],
            ['Sous-titre' => $hero->getSubTitle()],
            ['Image de fond' => (string) $hero->getBackground()],
        );

        // 3. Insertion de la section About
        $io->section('3. Récit du Savoir-Faire & Histoire 1995 (About)');
        $about = new About();
        $about->setTitle('Notre Savoir-Faire & L\'Histoire');
        $about->setContent(
            'Fondée en 1995 par la famille Rico au 7bis place des Belges à Castelsarrasin, Pizza Rico cultive l\'excellence de la tradition napolitaine. '
            .'Notre empâtement repose sur une maturation lente de 72 heures avec un levain mère naturel et des farines italiennes Tipo 00. '
            .'Chaque disque est étiré exclusivement à la main selon la technique séculaire du "Schiaffo", puis saisi en 85 secondes à 450°C dans notre four à bois à voûte en pierre de lave. '
            .'Nos ingrédients sont directement importés de terroirs d\'exception : tomates San Marzano DOP de Campanie, Mozzarella di Bufala, Prosciutto di Parma 24 mois et farines du moulin Caputo.'
        );

        $this->entityManager->persist($about);
        $this->entityManager->flush();

        $io->definitionList(
            ['Titre' => $about->getTitle()],
            ['Extrait' => mb_substr($about->getContent(), 0, 120).'...'],
        );

        // 4. Insertion des métadonnées SEO
        $io->section('4. Configuration des Balises SEO Dynamiques');
        $seoRows = [];

        foreach (self::SEO_DATA as $seoItem) {
            $seo = new SEO();
            $seo->setPageName($seoItem['pageName']);
            $seo->setMetaTitle($seoItem['metaTitle']);
            $seo->setMetaDescription($seoItem['metaDescription']);

            $this->entityManager->persist($seo);

            $seoRows[] = [
                $seoItem['pageName'],
                $seoItem['metaTitle'],
                $seoItem['metaDescription'],
            ];
        }

        $this->entityManager->flush();

        $io->table(
            ['Page Name', 'Meta Title', 'Meta Description'],
            $seoRows,
        );

        // 5. Insertion des Boissons
        $io->section('5. Insertion des 12 Boissons & Vins Italiens');
        $boissonRows = [];

        foreach (self::BOISSONS_DATA as $data) {
            $boisson = new Boisson();
            $boisson->setName($data['name']);
            $boisson->setPrice($data['price']);
            $boisson->setCategory($data['category']);
            $boisson->setCapacity($data['capacity']);
            $boisson->setImage($data['image']);

            $this->entityManager->persist($boisson);

            $boissonRows[] = [
                $data['name'],
                number_format($data['price'], 2, ',', ' ').' €',
                $data['category'],
                $data['capacity'],
                $data['image'],
            ];
        }

        $this->entityManager->flush();

        $io->table(
            ['Nom', 'Prix', 'Catégorie', 'Contenance', 'Image Asset'],
            $boissonRows,
        );

        $io->success('Toutes les données initiales ont été insérées avec succès en base de données PostgreSQL !');

        return Command::SUCCESS;
    }
}
