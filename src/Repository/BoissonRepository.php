<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Boisson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Boisson>
 */
class BoissonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boisson::class);
    }

    /**
     * @return list<Boisson>
     */
    public function findAllOrderByPriceAsc(): array
    {
        $result = $this->createQueryBuilder('b')
            ->orderBy('b.price', 'ASC')
            ->getQuery()
            ->getResult();

        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            static fn (mixed $item): bool => $item instanceof Boisson,
        ));
    }
}
