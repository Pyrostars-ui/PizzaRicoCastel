<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pizza;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pizza>
 */
class PizzaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pizza::class);
    }

    /**
     * @return list<Pizza>
     */
    public function findAllOrderByPriceAsc(): array
    {
        $result = $this->createQueryBuilder('p')
            ->orderBy('p.priceLarge', 'ASC')
            ->getQuery()
            ->getResult();

        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            static fn (mixed $item): bool => $item instanceof Pizza,
        ));
    }

    /**
     * @return list<Pizza>
     */
    public function findSpecialOrderByPriceAsc(): array
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.isSpecial = :special')
            ->setParameter('special', true)
            ->orderBy('p.priceLarge', 'ASC')
            ->getQuery()
            ->getResult();

        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            static fn (mixed $item): bool => $item instanceof Pizza,
        ));
    }

    /**
     * Retourne le prix moyen du format Large (calcul direct en DQL pour éviter findAll).
     */
    public function findAvgPriceLarge(): ?float
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.priceLarge) as avgPriceLarge')
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_numeric($result)) {
            return null;
        }

        return (float) $result;
    }
}
