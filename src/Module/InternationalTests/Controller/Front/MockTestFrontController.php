<?php

namespace App\Module\InternationalTests\Controller\Front;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Entity\TestResult;
use App\Module\InternationalTests\Repository\MockTestRepository;
use App\Module\InternationalTests\Repository\TestQuestionRepository;
use App\Module\InternationalTests\Repository\TestResultRepository;
use App\Module\InternationalTests\Service\CertificateService;
use App\Module\InternationalTests\Service\ExamTimeGuardService;
use App\Module\InternationalTests\Service\GeminiWritingService;
use App\Module\InternationalTests\Service\TestPerformanceAnalyzer;
use App\Module\PedagogicalContent\Repository\PlatformLanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Module\InternationalTests\Service\GeminiListeningService;
use App\Module\InternationalTests\Service\GeminiSpeakingService;
use App\Module\InternationalTests\Service\DeepgramTranscriptionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Module\UserManagement\Entity\User;



#[Route('/mock-tests')]
#[IsGranted('ROLE_USER')]
class MockTestFrontController extends AbstractController
{
    /**
     * Retourne l'utilisateur authentifié typé en tant que User (et non UserInterface).
     * Corrige l'erreur PHPStan : UserInterface n'a pas de méthode getId().
     */
    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur doit être connecté et être une instance de User.');
        }
        return $user;
    }

    private const PASS_SCORE         = 10;
    private const TOTAL_SCORE        = 20;
    private const QUESTIONS_PER_TEST = 10;

    // ─── Injection des services métier avancés ───
    public function __construct(
        private readonly ExamTimeGuardService   $timeGuard,
        private readonly TestPerformanceAnalyzer $performanceAnalyzer,
        private readonly GeminiWritingService    $geminiService,
        private readonly GeminiListeningService  $geminiListeningService,
        private readonly GeminiSpeakingService   $geminiSpeakingService,
        private readonly DeepgramTranscriptionService       $deepgramService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // PAGE 1 : Sélection de la langue
    // ═══════════════════════════════════════════════════════════

    #[Route('', name: 'mock_tests_index', methods: ['GET'])]
    public function index(MockTestRepository $mockTestRepo): Response
    {
        $allActiveTests     = $mockTestRepo->findBy(['isActive' => true]);
        $languagesWithTests = [];
        foreach ($allActiveTests as $test) {
            $lang = $test->getPlatformLanguage();
            if ($lang && !isset($languagesWithTests[$lang->getId()])) {
                $languagesWithTests[$lang->getId()] = $lang;
            }
        }

        return $this->render('internationaltests/mocktest_front/index.html.twig', [
            'user'      => $this->getUser(),
            'languages' => array_values($languagesWithTests),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 2 : Sélection du niveau
    // ═══════════════════════════════════════════════════════════

    #[Route('/language/{langId}', name: 'mock_tests_levels', methods: ['GET'])]
    public function levels(
        int                        $langId,
        MockTestRepository         $mockTestRepo,
        TestResultRepository       $resultRepo,
        PlatformLanguageRepository $langRepo
    ): Response {
        $language = $langRepo->find($langId);
        if (!$language) {
            throw $this->createNotFoundException('Language not found.');
        }

        $user             = $this->getAuthenticatedUser();
        $countByLevel     = $mockTestRepo->countActiveByLevelAndLanguage($langId);
        $bestScoreByLevel = [];
        $unlockedLevels   = [];

        foreach (MockTest::LEVELS as $idx => $level) {
            $best                     = $resultRepo->getBestScoreByUserAndLevel($user->getId(), $level, $langId);
            $bestScoreByLevel[$level] = $best;

            if ($idx === 0) {
                $unlockedLevels[$level] = true;
                continue;
            }
            $prevLevel              = MockTest::LEVELS[$idx - 1];
            $prevBest               = $bestScoreByLevel[$prevLevel] ?? null;
            $unlockedLevels[$level] = ($prevBest !== null && $prevBest >= self::PASS_SCORE);
        }

        return $this->render('internationaltests/mocktest_front/levels.html.twig', [
            'user'             => $this->getUser(),
            'language'         => $language,
            'levels'           => MockTest::LEVELS,
            'countByLevel'     => $countByLevel,
            'bestScoreByLevel' => $bestScoreByLevel,
            'unlockedLevels'   => $unlockedLevels,
            'passScore'        => self::PASS_SCORE,
            'totalScore'       => self::TOTAL_SCORE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 3 : Liste des tests
    // ═══════════════════════════════════════════════════════════

    #[Route('/language/{langId}/level/{level}', name: 'mock_tests_by_level', methods: ['GET'])]
    public function byLevel(
        int                        $langId,
        string                     $level,
        MockTestRepository         $mockTestRepo,
        TestResultRepository       $resultRepo,
        PlatformLanguageRepository $langRepo
    ): Response {
        if (!in_array($level, MockTest::LEVELS)) {
            throw $this->createNotFoundException("Level '$level' does not exist.");
        }

        $language = $langRepo->find($langId);
        if (!$language) {
            throw $this->createNotFoundException('Language not found.');
        }

        $levelIndex = array_search($level, MockTest::LEVELS);
        if ($levelIndex > 0) {
            $prevLevel = MockTest::LEVELS[$levelIndex - 1];
            $prevBest  = $resultRepo->getBestScoreByUserAndLevel(
                $this->getAuthenticatedUser()->getId(), $prevLevel, $langId
            );
            if ($prevBest === null || $prevBest < self::PASS_SCORE) {
                $this->addFlash('error',
                    'You need at least ' . self::PASS_SCORE . '/' . self::TOTAL_SCORE
                    . ' in ' . $prevLevel . ' to unlock ' . $level . '.'
                );
                return $this->redirectToRoute('mock_tests_levels', ['langId' => $langId]);
            }
        }

        $mockTests  = $mockTestRepo->findActiveByLevelAndLanguage($level, $langId);
        $bestScores = [];
        foreach ($mockTests as $test) {
            $bestScores[$test->getId()] = $resultRepo->getBestScoreByUserAndTest(
                $this->getAuthenticatedUser()->getId(), $test->getId()
            );
        }

        return $this->render('internationaltests/mocktest_front/list.html.twig', [
            'user'       => $this->getUser(),
            'language'   => $language,
            'level'      => $level,
            'mockTests'  => $mockTests,
            'bestScores' => $bestScores,
            'passScore'  => self::PASS_SCORE,
            'totalScore' => self::TOTAL_SCORE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 4 : Briefing avant le test
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/start', name: 'mock_tests_start', methods: ['GET'])]
    public function start(
        MockTest               $mockTest,
        TestQuestionRepository $questionRepo
    ): Response {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $availableCount = count($questionRepo->findWithFilters(
            null, null, $mockTest->getId(), true
        ));

        return $this->render('internationaltests/mocktest_front/start.html.twig', [
            'user'            => $this->getUser(),
            'mockTest'        => $mockTest,
            'availableCount'  => $availableCount,
            'questionsToTake' => min(self::QUESTIONS_PER_TEST, $availableCount),
            'totalScore'      => self::TOTAL_SCORE,
            'passScore'       => self::PASS_SCORE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 5 : Passage du test (dispatch)
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/take', name: 'mock_tests_take', methods: ['GET'])]
    public function take(
        MockTest               $mockTest,
        TestQuestionRepository $questionRepo,
        Request                $request
    ): Response {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $testCategory = $mockTest->getTestCategory();

        if ($testCategory === MockTest::TYPE_WRITING) {
            return $this->redirectToRoute('mock_tests_take_writing', ['id' => $mockTest->getId()]);
        }
        if ($testCategory === MockTest::TYPE_SPEAKING) {
            return $this->redirectToRoute('mock_tests_take_speaking', ['id' => $mockTest->getId()]);
        }
        if ($testCategory === MockTest::TYPE_LISTENING) {
            return $this->redirectToRoute('mock_tests_take_listening', ['id' => $mockTest->getId()]);
        }

        $questions = $questionRepo->findRandomQuestions($mockTest->getId(), self::QUESTIONS_PER_TEST);

        if (empty($questions)) {
            $this->addFlash('error', 'No questions available for this test yet.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        $session = $request->getSession();
        $session->set('mock_test_questions_' . $mockTest->getId(), array_map(fn($q) => $q->getId(), $questions));
        $session->set('mock_test_started_at_' . $mockTest->getId(), time());

        return $this->render('internationaltests/mocktest_front/take.html.twig', [
            'user'       => $this->getUser(),
            'mockTest'   => $mockTest,
            'questions'  => $questions,
            'totalScore' => self::TOTAL_SCORE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGES 5b/5c/5d : Writing / Speaking / Listening
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/take-writing', name: 'mock_tests_take_writing', methods: ['GET'])]
    public function takeWriting(MockTest $mockTest, Request $request): Response
    {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $session = $request->getSession();

        // Generate writing topic using Gemini AI
        $level = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        $topicData = $this->geminiService->generateWritingTopic($level, $languageName);

        // Store topic in session
        $session->set('writing_topic_' . $mockTest->getId(), $topicData);
        $session->set('mock_test_started_at_' . $mockTest->getId(), time());

        return $this->render('internationaltests/mocktest_front/take_writing.html.twig', [
            'user'       => $this->getUser(),
            'mockTest'   => $mockTest,
            'topicData'  => $topicData,
            'totalScore' => self::TOTAL_SCORE,
        ]);
    }

    #[Route('/{id}/take-speaking', name: 'mock_tests_take_speaking', methods: ['GET'])]
    public function takeSpeaking(MockTest $mockTest, Request $request): Response
    {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $session = $request->getSession();
        $session->set('mock_test_started_at_' . $mockTest->getId(), time());
        // L'historique et le sujet seront initialisés via AJAX (speakingStart)

        return $this->render('internationaltests/mocktest_front/take_speaking.html.twig', [
            'user'       => $this->getUser(),
            'mockTest'   => $mockTest,
            'totalScore' => self::TOTAL_SCORE,
        ]);
    }

// ── NOUVELLE ROUTE : démarrage AJAX ────────────────────────────

    #[Route('/{id}/speaking-start', name: 'mock_tests_speaking_start', methods: ['GET'])]
    public function speakingStart(MockTest $mockTest, Request $request): JsonResponse
    {
        $level        = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        $startData = $this->geminiSpeakingService->startConversation($level, $languageName);

        // Initialiser l'historique en session
        $session = $request->getSession();
        $history = [['role' => 'gemini', 'text' => $startData['welcome'] . ' ' . $startData['firstQuestion']]];
        $session->set('speaking_history_' . $mockTest->getId(), $history);
        $session->set('speaking_subject_' . $mockTest->getId(), $startData['subject']);

        return new JsonResponse([
            'success'       => true,
            'subject'       => $startData['subject'],
            'welcome'       => $startData['welcome'],
            'firstQuestion' => $startData['firstQuestion'],
            'instructions'  => $startData['instructions'] ?? '',
        ]);
    }

// ── NOUVELLE ROUTE : transcription AssemblyAI ──────────────────

    #[Route('/{id}/speaking-transcribe', name: 'mock_tests_speaking_transcribe', methods: ['POST'])]
    public function speakingTranscribe(MockTest $mockTest, Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $audioData = $data['audio'] ?? null;

        if (!$audioData) {
            return new JsonResponse(['success' => false, 'error' => 'No audio data received'], 400);
        }

        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';
        $result       = $this->deepgramService->transcribe($audioData, $languageName);

        // Calculer métriques de fluidité
        $fluencyMetrics = [];
        if ($result['success'] && !empty($result['words']) && $result['duration']) {
            $fluencyMetrics = $this->deepgramService->computeFluencyMetrics(
                $result['words'],
                (float) $result['duration']
            );
        }

        return new JsonResponse([
            'success'        => $result['success'],
            'text'           => $result['text'],
            'confidence'     => $result['confidence'],
            'fluencyMetrics' => $fluencyMetrics,
            'error'          => $result['error'],
        ]);
    }

// ── NOUVELLE ROUTE : échange de conversation ──────────────────

    #[Route('/{id}/speaking-chat', name: 'mock_tests_speaking_chat', methods: ['POST'])]
    public function speakingChat(MockTest $mockTest, Request $request): JsonResponse
    {
        $data           = json_decode($request->getContent(), true);
        $userAnswer     = trim($data['userAnswer']     ?? '');
        $exchangeNumber = (int) ($data['exchangeNumber'] ?? 1);
        $fluencyMetrics = $data['fluencyMetrics']      ?? [];

        if (empty($userAnswer)) {
            return new JsonResponse(['success' => false, 'error' => 'Empty answer'], 400);
        }

        $session      = $request->getSession();
        $history      = $session->get('speaking_history_' . $mockTest->getId(), []);
        $level        = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        // Ajouter la réponse du user à l'historique
        $history[] = [
            'role'           => 'user',
            'text'           => $userAnswer,
            'fluencyMetrics' => $fluencyMetrics,
        ];

        // Gemini réagit et pose la question suivante
        $geminiResponse = $this->geminiSpeakingService->continueConversation(
            $history, $userAnswer, $exchangeNumber, $level, $languageName
        );

        // Ajouter la réaction de Gemini à l'historique
        $geminiText = $geminiResponse['reaction'];
        if (!empty($geminiResponse['nextQuestion']) && $geminiResponse['nextQuestion'] !== 'null') {
            $geminiText .= ' ' . $geminiResponse['nextQuestion'];
        }
        $history[] = ['role' => 'gemini', 'text' => $geminiText];

        // Sauvegarder l'historique mis à jour
        $session->set('speaking_history_' . $mockTest->getId(), $history);

        return new JsonResponse([
            'success'      => true,
            'reaction'     => $geminiResponse['reaction'],
            'nextQuestion' => $geminiResponse['nextQuestion'] ?? null,
            'isFinished'   => $geminiResponse['isFinished']   ?? false,
        ]);
    }

    #[Route('/{id}/take-listening', name: 'mock_tests_take_listening', methods: ['GET'])]
    public function takeListening(MockTest $mockTest, Request $request): Response
    {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $level        = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        // Génération du contenu Listening par Gemini (texte + questions mixtes)
        $listeningContent = $this->geminiListeningService->generateListeningContent($level, $languageName);

        // Nombre de replays selon niveau
        $maxReplays = match($level) {
            MockTest::LEVEL_BEGINNER     => 3,
            MockTest::LEVEL_INTERMEDIATE => 2,
            MockTest::LEVEL_ADVANCED     => 1,
            default                      => 2,
        };

        // Stocker en session (comme le writing topic)
        $session = $request->getSession();
        $session->set('listening_content_' . $mockTest->getId(), $listeningContent);
        $session->set('mock_test_started_at_' . $mockTest->getId(), time());

        return $this->render('internationaltests/mocktest_front/take_listening.html.twig', [
            'user'             => $this->getUser(),
            'mockTest'         => $mockTest,
            'listeningContent' => $listeningContent,
            'maxReplays'       => $maxReplays,
            'totalScore'       => self::TOTAL_SCORE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SOUMISSION QCM — avec ExamTimeGuardService ✅
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/submit', name: 'mock_tests_submit', methods: ['POST'])]
    public function submit(
        MockTest               $mockTest,
        Request                $request,
        TestQuestionRepository $questionRepo,
        EntityManagerInterface $em,
        CertificateService     $certificateService
    ): Response {
        // ── Validation CSRF ──
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mock_test_submit_' . $mockTest->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── MÉTIER AVANCÉ #2 : Vérification acceptabilité temporelle ──
        if (!$this->timeGuard->isSubmissionAcceptable($mockTest, $request)) {
            $this->addFlash('error', 'Submission refused: time limit exceeded by more than 10 minutes.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── Récupération session ──
        $session     = $request->getSession();
        $questionIds = $session->get('mock_test_questions_' . $mockTest->getId(), []);

        if (empty($questionIds)) {
            $this->addFlash('error', 'Session expired. Please restart the test.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── Calcul du score brut ──
        $submittedAnswers = $request->request->all('answers') ?? [];
        $rawTotalPoints   = 0;
        $rawEarned        = 0;
        $detailedResults  = [];

        foreach ($questionIds as $qId) {
            $question = $questionRepo->find($qId);
            if (!$question) continue;

            $rawTotalPoints += $question->getPoints();
            $userAnswerRaw   = $submittedAnswers[$qId] ?? null;
            $userAnswer      = is_array($userAnswerRaw)
                ? implode('|', array_map('trim', $userAnswerRaw))
                : $userAnswerRaw;

            $isCorrect = $question->isAnswerCorrect($userAnswer);
            if ($isCorrect) $rawEarned += $question->getPoints();

            $detailedResults[] = [
                'questionId'      => $qId,
                'questionText'    => $question->getQuestionText(),
                'questionType'    => $question->getQuestionType(),
                'readingPassage'  => $question->getReadingPassage(),
                'options'         => $question->getOptions(),
                'userAnswer'      => $userAnswer,
                'correctAnswer'   => $question->getCorrectAnswer(),
                'isCorrect'       => $isCorrect,
                'points'          => $question->getPoints(),
                'sectionCategory' => $question->getSectionCategory(),
            ];
        }

        $scoreOn20 = $rawTotalPoints > 0
            ? round(($rawEarned / $rawTotalPoints) * self::TOTAL_SCORE, 2)
            : 0;

        // ── MÉTIER AVANCÉ #2 : Validation et ajustement temporel ──
        $timeReport    = $this->timeGuard->validateSubmission($mockTest, $request, $scoreOn20);
        $elapsedData   = $this->timeGuard->getElapsedTime($mockTest, $request);
        $finalScore    = $timeReport['adjustedScore']; // Score après éventuelle pénalité

        // ── Rapport de faiblesse par section ──
        $weaknessBySection = [];
        foreach ($detailedResults as $r) {
            $sec = $r['sectionCategory'];
            if (!isset($weaknessBySection[$sec])) {
                $weaknessBySection[$sec] = ['correct' => 0, 'total' => 0];
            }
            $weaknessBySection[$sec]['total']++;
            if ($r['isCorrect']) $weaknessBySection[$sec]['correct']++;
        }

        $aiWeaknessReport = [];
        foreach ($weaknessBySection as $section => $data) {
            $pct = round(($data['correct'] / $data['total']) * 100);
            $aiWeaknessReport[] = [
                'section' => $section,
                'score'   => $pct,
                'status'  => $pct >= 50 ? 'good' : 'needs_improvement',
                'correct' => $data['correct'],
                'total'   => $data['total'],
            ];
        }

        // ── Sauvegarde TestResult avec données temporelles ──
        $testResult = new TestResult();
        $testResult->setMockTest($mockTest);
        $testResult->setUser($this->getAuthenticatedUser());
        $testResult->setOverallScore($finalScore);
        $testResult->setAiWeaknessReport($aiWeaknessReport);
        $testResult->setDateTaken(new \DateTime());

        // Stocker le rapport temporel dans aiCorrection (réutilisation du champ JSON)
        $testResult->setAiCorrection([
            'timeReport'  => $timeReport,
            'elapsedTime' => $elapsedData,
            'rawScore'    => $scoreOn20,
        ]);

        $em->persist($testResult);
        $em->flush();

        // ── Vérification et génération automatique du certificat ──
        $langId = $mockTest->getPlatformLanguage()?->getId();
        if ($langId) {
            $certificateService->checkAndGenerateCertificate($this->getAuthenticatedUser(), $langId);
        }

        $session->remove('mock_test_questions_' . $mockTest->getId());
        $session->remove('mock_test_started_at_' . $mockTest->getId());
        $session->set('mock_test_result_detail_' . $testResult->getId(), $detailedResults);

        return $this->redirectToRoute('mock_tests_result', [
            'id'       => $mockTest->getId(),
            'resultId' => $testResult->getId(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 6 : Résultats
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/result/{resultId}', name: 'mock_tests_result', methods: ['GET'])]
    public function result(
        MockTest             $mockTest,
        int                  $resultId,
        TestResultRepository $resultRepo,
        Request              $request,
        CertificateService   $certificateService
    ): Response {
        $testResult = $resultRepo->find($resultId);

        if (!$testResult || $testResult->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot view this result.');
        }

        $session         = $request->getSession();
        $detailedResults = $session->get('mock_test_result_detail_' . $resultId, []);
        $passed          = $testResult->getOverallScore() >= self::PASS_SCORE;

        $levelIndex = array_search($mockTest->getLevel(), MockTest::LEVELS);
        $nextLevel  = isset(MockTest::LEVELS[$levelIndex + 1]) ? MockTest::LEVELS[$levelIndex + 1] : null;
        $langId     = $mockTest->getPlatformLanguage()?->getId();

        // Extraire le rapport temporel depuis aiCorrection
        $aiCorrection = $testResult->getAiCorrection() ?? [];
        $timeReport   = $aiCorrection['timeReport'] ?? null;
        $elapsedTime  = $aiCorrection['elapsedTime'] ?? null;
        $rawScore     = $aiCorrection['rawScore'] ?? $testResult->getOverallScore();

        // Vérifier si un certificat existe pour cette langue
        $certificate = null;
        if ($langId) {
            $certificate = $certificateService->generateOrGetCertificate($this->getAuthenticatedUser(), $langId);
        }

        return $this->render('internationaltests/mocktest_front/result.html.twig', [
            'user'            => $this->getUser(),
            'mockTest'        => $mockTest,
            'testResult'      => $testResult,
            'detailedResults' => $detailedResults,
            'passed'          => $passed,
            'nextLevel'       => $nextLevel,
            'langId'          => $langId,
            'passScore'       => self::PASS_SCORE,
            'totalScore'      => self::TOTAL_SCORE,
            // Données temporelles pour l'affichage
            'timeReport'      => $timeReport,
            'elapsedTime'     => $elapsedTime,
            'rawScore'        => $rawScore,
            // Certificat si disponible
            'certificate'     => $certificate,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE 7 : Historique — avec TestPerformanceAnalyzer ✅
    // ═══════════════════════════════════════════════════════════

    #[Route('/my-results', name: 'mock_tests_my_results', methods: ['GET'])]
    public function myResults(TestResultRepository $resultRepo): Response
    {
        $user    = $this->getAuthenticatedUser();
        $results = $resultRepo->findByUser($user->getId());

        // ── MÉTIER AVANCÉ #1 : Analyse de performance multi-dimensionnelle ──
        $performanceReport = $this->performanceAnalyzer->analyze($user);

        return $this->render('internationaltests/mocktest_front/my_results.html.twig', [
            'user'              => $user,
            'results'           => $results,
            'passScore'         => self::PASS_SCORE,
            'totalScore'        => self::TOTAL_SCORE,
            'performanceReport' => $performanceReport,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SOUMISSIONS Writing / Speaking / Listening
    // ═══════════════════════════════════════════════════════════

    #[Route('/{id}/submit-writing', name: 'mock_tests_submit_writing', methods: ['POST'])]
    public function submitWriting(
        MockTest               $mockTest,
        Request                $request,
        EntityManagerInterface $em,
        CertificateService     $certificateService
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mock_test_submit_' . $mockTest->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        if (!$this->timeGuard->isSubmissionAcceptable($mockTest, $request)) {
            $this->addFlash('error', 'Submission refused: time limit exceeded.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        $session = $request->getSession();

        // Get topic from session
        $topicData = $session->get('writing_topic_' . $mockTest->getId());
        if (!$topicData) {
            $this->addFlash('error', 'Writing topic not found. Please start the test again.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // Get user's essay response
        $userResponse = trim($request->request->get('essay', ''));
        if (empty($userResponse)) {
            $this->addFlash('error', 'Please write your essay before submitting.');
            return $this->redirectToRoute('mock_tests_take_writing', ['id' => $mockTest->getId()]);
        }

        // Get Gemini correction
        $level = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';
        $topic = $topicData['topic'] ?? 'Writing topic';

        $correction = $this->geminiService->correctWriting($topic, $userResponse, $level, $languageName);

        // Create TestResult
        $result = new TestResult();
        $result->setUser($this->getAuthenticatedUser());
        $result->setMockTest($mockTest);
        $result->setDateTaken(new \DateTime());

        // Store topic and user response in aiCorrection for reference
        $correction['topic'] = $topic;
        $correction['userResponse'] = $userResponse;
        $correction['wordCount'] = str_word_count($userResponse);

        $result->setAiCorrection($correction);
        $result->setAiNote($correction['note']);
        $result->setOverallScore($correction['note']);

        $em->persist($result);
        $em->flush();

        // Check for certificate generation
        $certificateService->checkAndGenerateCertificate($this->getAuthenticatedUser(), $mockTest->getPlatformLanguage()->getId());

        // Store correction details in session so result page can display AI feedback
        $writingDetails = [
            [
                'questionText'    => $topic,
                'userAnswer'      => $userResponse,
                'correctAnswer'   => 'N/A',
                'isCorrect'       => $correction['passed'] ?? false,
                'sectionCategory' => 'Writing',
                'points'          => $correction['note'] ?? 0,
                'type'            => 'writing',
                'aiFeedback'      => $correction['feedback']    ?? '',
                'grammar'         => $correction['grammar']     ?? '',
                'coherence'       => $correction['coherence']   ?? '',
                'vocabulary'      => $correction['vocabulary']  ?? '',
                'suggestions'     => $correction['suggestions'] ?? [],
                'note'            => $correction['note']        ?? 0,
                'passed'          => $correction['passed']      ?? false,
                'wordCount'       => str_word_count($userResponse),
            ]
        ];

        // Clean up session
        $session->remove('writing_topic_' . $mockTest->getId());
        $session->remove('mock_test_started_at_' . $mockTest->getId());
        $session->set('mock_test_result_detail_' . $result->getId(), $writingDetails);

        return $this->redirectToRoute('mock_tests_result', [
            'id' => $mockTest->getId(),
            'resultId' => $result->getId()
        ]);
    }


    #[Route('/{id}/submit-speaking', name: 'mock_tests_submit_speaking', methods: ['POST'])]
    public function submitSpeaking(
        MockTest               $mockTest,
        Request                $request,
        EntityManagerInterface $em,
        CertificateService     $certificateService
    ): Response {
        // CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mock_test_submit_' . $mockTest->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // Vérification temporelle
        if (!$this->timeGuard->isSubmissionAcceptable($mockTest, $request)) {
            $this->addFlash('error', 'Submission refused: time limit exceeded.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        $session      = $request->getSession();
        $history      = $session->get('speaking_history_' . $mockTest->getId(), []);
        $level        = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        if (empty($history)) {
            $this->addFlash('error', 'Session expired. Please restart the test.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // Agréger les métriques de fluidité de tous les échanges
        $allFluencyMetrics = [];
        foreach ($history as $turn) {
            if ($turn['role'] === 'user' && !empty($turn['fluencyMetrics'])) {
                foreach ($turn['fluencyMetrics'] as $key => $value) {
                    $allFluencyMetrics[$key][] = $value;
                }
            }
        }
        $avgFluency = [];
        foreach ($allFluencyMetrics as $key => $values) {
            $avgFluency[$key] = round(array_sum($values) / count($values), 2);
        }

        // Évaluation Gemini de toute la conversation
        $evaluation = $this->geminiSpeakingService->evaluateConversation(
            $history, $avgFluency, $level, $languageName
        );

        $rawScore    = (float) $evaluation['note'];
        $timeReport  = $this->timeGuard->validateSubmission($mockTest, $request, $rawScore);
        $elapsedData = $this->timeGuard->getElapsedTime($mockTest, $request);
        $finalScore  = $timeReport['adjustedScore'];

        // Rapport de faiblesses
        $aiWeaknessReport = [
            ['section' => 'Grammar',       'score' => 0, 'status' => 'needs_improvement', 'correct' => 0, 'total' => 1],
            ['section' => 'Vocabulary',    'score' => 0, 'status' => 'needs_improvement', 'correct' => 0, 'total' => 1],
            ['section' => 'Fluency',       'score' => 0, 'status' => 'needs_improvement', 'correct' => 0, 'total' => 1],
            ['section' => 'Coherence',     'score' => 0, 'status' => 'needs_improvement', 'correct' => 0, 'total' => 1],
            ['section' => 'Pronunciation', 'score' => 0, 'status' => 'needs_improvement', 'correct' => 0, 'total' => 1],
        ];

        // Sauvegarder TestResult
        $testResult = new TestResult();
        $testResult->setMockTest($mockTest);
        $testResult->setUser($this->getAuthenticatedUser());
        $testResult->setOverallScore($finalScore);
        $testResult->setAiNote($rawScore);
        $testResult->setAiWeaknessReport($aiWeaknessReport);
        $testResult->setAiCorrection([
            'type'        => 'speaking',
            'evaluation'  => $evaluation,
            'history'     => $history,
            'avgFluency'  => $avgFluency,
            'timeReport'  => $timeReport,
            'elapsedTime' => $elapsedData,
            'rawScore'    => $rawScore,
            'subject'     => $session->get('speaking_subject_' . $mockTest->getId(), ''),
        ]);
        $testResult->setDateTaken(new \DateTime());

        $em->persist($testResult);
        $em->flush();

        // Certificat
        $langId = $mockTest->getPlatformLanguage()?->getId();
        if ($langId) {
            $certificateService->checkAndGenerateCertificate($this->getAuthenticatedUser(), $langId);
        }

        // Détails pour la page résultat
        $details = array_map(fn($turn) => [
            'questionText'    => $turn['text'],
            'userAnswer'      => '',
            'correctAnswer'   => 'N/A',
            'isCorrect'       => true,
            'sectionCategory' => 'Speaking',
            'points'          => 1,
            'type'            => $turn['role'],
        ], array_filter($history, fn($t) => $t['role'] === 'user'));

        // Nettoyage session
        $session->remove('speaking_history_' . $mockTest->getId());
        $session->remove('speaking_subject_' . $mockTest->getId());
        $session->remove('mock_test_started_at_' . $mockTest->getId());
        $session->set('mock_test_result_detail_' . $testResult->getId(), array_values($details));

        return $this->redirectToRoute('mock_tests_result', [
            'id'       => $mockTest->getId(),
            'resultId' => $testResult->getId(),
        ]);
    }

    #[Route('/{id}/submit-listening', name: 'mock_tests_submit_listening', methods: ['POST'])]
    public function submitListening(
        MockTest               $mockTest,
        Request                $request,
        EntityManagerInterface $em,
        CertificateService     $certificateService
    ): Response {
        // ── CSRF ──
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mock_test_submit_' . $mockTest->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── Vérification temporelle ──
        if (!$this->timeGuard->isSubmissionAcceptable($mockTest, $request)) {
            $this->addFlash('error', 'Submission refused: time limit exceeded.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── Récupération du contenu depuis la session ──
        $session          = $request->getSession();
        $listeningContent = $session->get('listening_content_' . $mockTest->getId());

        if (!$listeningContent || empty($listeningContent['questions'])) {
            $this->addFlash('error', 'Session expired. Please restart the test.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        // ── Récupération des réponses (indexées par position dans le tableau) ──
        $userAnswers = $request->request->all('answers') ?? [];

        // ── Correction complète via GeminiListeningService ──
        $level        = $mockTest->getLevel();
        $languageName = $mockTest->getPlatformLanguage()?->getName() ?? 'English';

        $scoringResult = $this->geminiListeningService->scoreSubmission(
            $listeningContent['questions'],
            $userAnswers,
            $level,
            $languageName
        );

        $rawScore = $scoringResult['score'];

        // ── Ajustement temporel (ExamTimeGuardService) ──
        $timeReport  = $this->timeGuard->validateSubmission($mockTest, $request, $rawScore);
        $elapsedData = $this->timeGuard->getElapsedTime($mockTest, $request);
        $finalScore  = $timeReport['adjustedScore'];

        // ── Rapport de faiblesses par type de question ──
        $qcmDetails  = array_filter($scoringResult['details'], fn($d) => $d['type'] === 'qcm');
        $openDetails = array_filter($scoringResult['details'], fn($d) => $d['type'] === 'open');

        $qcmCorrect  = count(array_filter($qcmDetails,  fn($d) => $d['isCorrect']));
        $openCorrect = count(array_filter($openDetails, fn($d) => $d['isCorrect']));

        $aiWeaknessReport = [
            [
                'section' => 'Multiple Choice (QCM)',
                'correct' => $qcmCorrect,
                'total'   => count($qcmDetails),
                'score'   => count($qcmDetails) > 0
                    ? round(($qcmCorrect / count($qcmDetails)) * 100)
                    : 0,
                'status'  => count($qcmDetails) > 0 && ($qcmCorrect / count($qcmDetails)) >= 0.5
                    ? 'good' : 'needs_improvement',
            ],
            [
                'section' => 'Open Questions',
                'correct' => $openCorrect,
                'total'   => count($openDetails),
                'score'   => count($openDetails) > 0
                    ? round(($openCorrect / count($openDetails)) * 100)
                    : 0,
                'status'  => count($openDetails) > 0 && ($openCorrect / count($openDetails)) >= 0.5
                    ? 'good' : 'needs_improvement',
            ],
        ];

        // ── Sauvegarde TestResult ──
        $testResult = new TestResult();
        $testResult->setMockTest($mockTest);
        $testResult->setUser($this->getAuthenticatedUser());
        $testResult->setOverallScore($finalScore);
        $testResult->setAiNote($rawScore);
        $testResult->setAiWeaknessReport($aiWeaknessReport);
        $testResult->setAiCorrection([
            'type'           => 'listening',
            'timeReport'     => $timeReport,
            'elapsedTime'    => $elapsedData,
            'rawScore'       => $rawScore,
            'openEvaluation' => $scoringResult['openEvaluation'],
            'audioType'      => $listeningContent['audioType'] ?? 'unknown',
        ]);
        $testResult->setDateTaken(new \DateTime());

        $em->persist($testResult);
        $em->flush();

        // ── Vérification certificat ──
        $langId = $mockTest->getPlatformLanguage()?->getId();
        if ($langId) {
            $certificateService->checkAndGenerateCertificate($this->getAuthenticatedUser(), $langId);
        }

        // ── Nettoyage session ──
        $session->remove('listening_content_' . $mockTest->getId());
        $session->remove('mock_test_started_at_' . $mockTest->getId());
        $session->set('mock_test_result_detail_' . $testResult->getId(), $scoringResult['details']);

        return $this->redirectToRoute('mock_tests_result', [
            'id'       => $mockTest->getId(),
            'resultId' => $testResult->getId(),
        ]);
    }
    // ═══════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════

    private function prepareTakeResponse(
        MockTest               $mockTest,
        TestQuestionRepository $questionRepo,
        Request                $request,
        string                 $template
    ): Response {
        if (!$mockTest->isActive()) {
            $this->addFlash('error', 'This test is currently unavailable.');
            return $this->redirectToRoute('mock_tests_index');
        }

        $questions = $questionRepo->findRandomQuestions($mockTest->getId(), self::QUESTIONS_PER_TEST);
        if (empty($questions)) {
            $this->addFlash('error', 'No questions available for this test yet.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        $session = $request->getSession();
        $session->set('mock_test_questions_' . $mockTest->getId(), array_map(fn($q) => $q->getId(), $questions));
        $session->set('mock_test_started_at_' . $mockTest->getId(), time());

        return $this->render('internationaltests/mocktest_front/' . $template, [
            'user'       => $this->getUser(),
            'mockTest'   => $mockTest,
            'questions'  => $questions,
            'totalScore' => self::TOTAL_SCORE,
        ]);
    }

    private function submitSubjectiveTest(
        MockTest               $mockTest,
        Request                $request,
        TestQuestionRepository $questionRepo,
        EntityManagerInterface $em,
        string                 $type
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('mock_test_submit_' . $mockTest->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        if (!$this->timeGuard->isSubmissionAcceptable($mockTest, $request)) {
            $this->addFlash('error', 'Submission refused: time limit exceeded.');
            return $this->redirectToRoute('mock_tests_start', ['id' => $mockTest->getId()]);
        }

        $session     = $request->getSession();
        $questionIds = $session->get('mock_test_questions_' . $mockTest->getId(), []);
        $answers     = $request->request->all('answers') ?? [];

        $totalQ     = count($questionIds);
        $answered   = 0;
        $details    = [];

        foreach ($questionIds as $qId) {
            $question = $questionRepo->find($qId);
            if (!$question) continue;
            $answer = trim($answers[$qId] ?? '');
            if (!empty($answer) && $answer !== 'N/A') $answered++;
            $details[] = [
                'questionId'      => $qId,
                'questionText'    => $question->getQuestionText(),
                'questionType'    => $question->getQuestionType(),
                'userAnswer'      => $answer,
                'correctAnswer'   => 'N/A',
                'isCorrect'       => false,
                'points'          => $question->getPoints(),
                'sectionCategory' => $question->getSectionCategory(),
            ];
        }

        $scoreOn20   = $totalQ > 0 ? round(($answered / $totalQ) * self::TOTAL_SCORE, 2) : 0;
        $timeReport  = $this->timeGuard->validateSubmission($mockTest, $request, $scoreOn20);
        $elapsedData = $this->timeGuard->getElapsedTime($mockTest, $request);
        $finalScore  = $timeReport['adjustedScore'];

        $testResult = new TestResult();
        $testResult->setMockTest($mockTest);
        $testResult->setUser($this->getAuthenticatedUser());
        $testResult->setOverallScore($finalScore);
        $testResult->setAiWeaknessReport([]);
        $testResult->setAiCorrection(['timeReport' => $timeReport, 'elapsedTime' => $elapsedData, 'rawScore' => $scoreOn20, 'type' => $type]);
        $testResult->setDateTaken(new \DateTime());

        $em->persist($testResult);
        $em->flush();

        $session->remove('mock_test_questions_' . $mockTest->getId());
        $session->remove('mock_test_started_at_' . $mockTest->getId());
        $session->set('mock_test_result_detail_' . $testResult->getId(), $details);

        return $this->redirectToRoute('mock_tests_result', ['id' => $mockTest->getId(), 'resultId' => $testResult->getId()]);
    }
}
