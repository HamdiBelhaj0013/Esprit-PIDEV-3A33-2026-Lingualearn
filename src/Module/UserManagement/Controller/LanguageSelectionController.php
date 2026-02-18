<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\UserLanguage;
use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use App\Module\PedagogicalContent\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LanguageSelectionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Step 1 – Show all enabled PlatformLanguages the user can enroll in.
     */
    #[Route('/learn', name: 'language_select', methods: ['GET'])]
    public function select(): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        // Only show enabled platform languages
        $allLanguages = $this->entityManager
            ->getRepository(PlatformLanguage::class)
            ->findBy(['isEnabled' => true], ['name' => 'ASC']);

        // IDs the user is already enrolled in
        $enrolledIds = array_map(
            fn(UserLanguage $ul) => $ul->getPlatformLanguage()->getId(),
            $user->getUserLanguages()->toArray()
        );

        return $this->render('pedagogical_content/language_select.html.twig', [
            'languages'   => $allLanguages,
            'enrolledIds' => $enrolledIds,
        ]);
    }

    /**
     * Step 2 – Enroll the user in a chosen PlatformLanguage.
     */
    #[Route('/learn/{id}/enroll', name: 'language_enroll', methods: ['POST'])]
    public function enroll(Request $request, PlatformLanguage $platformLanguage): Response
    {
        if (!$this->isCsrfTokenValid('enroll_' . $platformLanguage->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('language_select');
        }

        if (!$platformLanguage->isEnabled()) {
            $this->addFlash('warning', 'This language is not available.');
            return $this->redirectToRoute('language_select');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        // Already enrolled?
        foreach ($user->getUserLanguages() as $existing) {
            if ($existing->getPlatformLanguage() === $platformLanguage) {
                $this->addFlash('info', 'You are already enrolled in ' . $platformLanguage->getName() . '.');
                return $this->redirectToRoute('language_courses', ['id' => $platformLanguage->getId()]);
            }
        }

        $userLanguage = new UserLanguage();
        $userLanguage->setUser($user);
        $userLanguage->setPlatformLanguage($platformLanguage);
        $userLanguage->setProficiencyLevel('A1');
        $userLanguage->setIsNative(false);

        $this->entityManager->persist($userLanguage);
        $this->entityManager->flush();

        $this->addFlash('success', 'You are now enrolled in ' . $platformLanguage->getName() . '! Start learning below.');

        return $this->redirectToRoute('language_courses', ['id' => $platformLanguage->getId()]);
    }

    /**
     * Step 3 – Show published courses for an enrolled PlatformLanguage.
     */
    #[Route('/learn/{id}/courses', name: 'language_courses', methods: ['GET'])]
    public function courses(PlatformLanguage $platformLanguage, Request $request): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        // Guard: user must be enrolled in this platform language
        $enrolled = false;
        foreach ($user->getUserLanguages() as $ul) {
            if ($ul->getPlatformLanguage()->getId() === $platformLanguage->getId()) {
                $enrolled = true;
                break;
            }
        }

        if (!$enrolled) {
            $this->addFlash('warning', 'You must enroll in ' . $platformLanguage->getName() . ' before accessing its courses.');
            return $this->redirectToRoute('language_select');
        }

        $levelFilter   = $request->query->get('level');
        $allowedLevels = ['beginner', 'intermediate', 'advanced'];

        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Course::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.platformLanguage = :pl')
            ->setParameter('status', 'published')
            ->setParameter('pl', $platformLanguage)
            ->orderBy('c.publishedAt', 'DESC');

        if ($levelFilter && in_array($levelFilter, $allowedLevels, true)) {
            $qb->andWhere('c.level = :level')->setParameter('level', $levelFilter);
        }

        $courses = $qb->getQuery()->getResult();

        return $this->render('pedagogical_content/language_courses.html.twig', [
            'language'    => $platformLanguage,
            'courses'     => $courses,
            'levelFilter' => $levelFilter,
            'levels'      => $allowedLevels,
        ]);
    }

    /**
     * Unenroll from a PlatformLanguage.
     */
    #[Route('/learn/{id}/unenroll', name: 'language_unenroll', methods: ['POST'])]
    public function unenroll(Request $request, PlatformLanguage $platformLanguage): Response
    {
        if (!$this->isCsrfTokenValid('unenroll_' . $platformLanguage->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('language_select');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        foreach ($user->getUserLanguages() as $ul) {
            if ($ul->getPlatformLanguage()->getId() === $platformLanguage->getId()) {
                $this->entityManager->remove($ul);
                $this->entityManager->flush();
                $this->addFlash('info', 'You have unenrolled from ' . $platformLanguage->getName() . '.');
                return $this->redirectToRoute('language_select');
            }
        }

        $this->addFlash('warning', 'You were not enrolled in that language.');
        return $this->redirectToRoute('language_select');
    }
    #[Route('/learn/course/{id}', name: 'course_show', methods: ['GET'])]
    public function courseShow(\App\Module\PedagogicalContent\Entity\Course $course): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        $platformLanguage = $course->getPlatformLanguage();

        // Guard: must be enrolled
        $enrolled = false;
        foreach ($user->getUserLanguages() as $ul) {
            if ($ul->getPlatformLanguage()->getId() === $platformLanguage->getId()) {
                $enrolled = true;
                break;
            }
        }

        if (!$enrolled) {
            $this->addFlash('warning', 'You must enroll in ' . $platformLanguage->getName() . ' to access this course.');
            return $this->redirectToRoute('language_select');
        }

        return $this->render('pedagogical_content/course_show.html.twig', [
            'course'   => $course,
            'language' => $platformLanguage,
            'lessons'  => $course->getLessons(),
        ]);
    }

    /**
     * Interactive lesson viewer.
     * User must be enrolled in the lesson's course language.
     */
    #[Route('/learn/lesson/{id}', name: 'lesson_show', methods: ['GET'])]
    public function lessonShow(\App\Module\PedagogicalContent\Entity\Lesson $lesson): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        $course           = $lesson->getCourse();
        $platformLanguage = $course->getPlatformLanguage();

        // Guard: must be enrolled
        $enrolled = false;
        foreach ($user->getUserLanguages() as $ul) {
            if ($ul->getPlatformLanguage()->getId() === $platformLanguage->getId()) {
                $enrolled = true;
                break;
            }
        }

        if (!$enrolled) {
            $this->addFlash('warning', 'You must enroll in ' . $platformLanguage->getName() . ' to view this lesson.');
            return $this->redirectToRoute('language_select');
        }

        // Ordered lesson list for prev/next navigation
        $lessons = $course->getLessons()->toArray();
        $currentIndex = array_search($lesson, $lessons);
        $prevLesson = $currentIndex > 0 ? $lessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < count($lessons) - 1 ? $lessons[$currentIndex + 1] : null;

        return $this->render('pedagogical_content/lesson_show.html.twig', [
            'lesson'      => $lesson,
            'course'      => $course,
            'language'    => $platformLanguage,
            'prevLesson'  => $prevLesson,
            'nextLesson'  => $nextLesson,
            'lessonIndex' => $currentIndex + 1,
            'totalLessons'=> count($lessons),
        ]);
    }
}
