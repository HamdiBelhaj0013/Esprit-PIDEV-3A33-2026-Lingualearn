<?php

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLessonStatus>
 *
 * @method UserLessonStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserLessonStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserLessonStatus[]    findAll()
 * @method UserLessonStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserLessonStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLessonStatus::class);
    }

    // Ajoute ici tes méthodes personnalisées si nécessaire
}
