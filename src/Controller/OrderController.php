<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SEORepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur gérant le tunnel de commande et le paiement (checkout).
 */
final class OrderController extends AbstractController
{
    /**
     * @var list<string>
     */
    private const array TIME_SLOTS = [
        '19h45', '20h00', '20h15', '20h30',
        '20h45', '21h00', '21h15', '21h30',
    ];

    /**
     * Affiche la page de finalisation de commande (checkout).
     */
    #[Route('/commande/finaliser', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function checkout(SEORepository $seoRepo): Response
    {
        $seo = $seoRepo->findOneBy(['pageName' => 'checkout']);

        return $this->render('order/checkout.html.twig', [
            'title' => 'Finaliser Votre Commande - Pizza Rico',
            'seo' => $seo,
            'hotline' => '05 63 32 14 43',
            'address' => [
                'street' => '7bis place des Belges',
                'postalCode' => '82100',
                'city' => 'Castelsarrasin',
            ],
            'timeSlots' => self::TIME_SLOTS,
            'deliveryFee' => 2.50,
            'freeDeliveryThreshold' => 35.00,
            'freeDeliveryFrom' => 25.00,
        ]);
    }
}
