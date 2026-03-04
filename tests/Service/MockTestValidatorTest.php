<?php

namespace App\Tests\Service;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Service\MockTestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour MockTestValidator
 * Valide les règles métier du module InternationalTests
 */
class MockTestValidatorTest extends TestCase
{
    private MockTestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new MockTestValidator();
    }

    // ══════════════════════════════════════════════
    // RÈGLE 1 — Durée du test
    // ══════════════════════════════════════════════

    public function testDurationValide(): void
    {
        $mockTest = new MockTest();
        $mockTest->setTitle('TOEFL Test');
        $mockTest->setDurationMinutes(30);

        $this->assertTrue($this->validator->validateDuration($mockTest));
    }

    public function testDurationZeroLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée du test doit être supérieure à 0 minute.');

        $mockTest = new MockTest();
        $mockTest->setDurationMinutes(0);

        $this->validator->validateDuration($mockTest);
    }

    public function testDurationNegativeLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $mockTest = new MockTest();
        $mockTest->setDurationMinutes(-10);

        $this->validator->validateDuration($mockTest);
    }

    // ══════════════════════════════════════════════
    // RÈGLE 2 — Titre du test
    // ══════════════════════════════════════════════

    public function testTitreValide(): void
    {
        $mockTest = new MockTest();
        $mockTest->setTitle('TOEFL Beginner Test');
        $mockTest->setDurationMinutes(30);

        $this->assertTrue($this->validator->validateTitle($mockTest));
    }

    public function testTitreVideLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du test est obligatoire.');

        $mockTest = new MockTest();
        $mockTest->setTitle('');

        $this->validator->validateTitle($mockTest);
    }

    // ══════════════════════════════════════════════
    // RÈGLE 3 — Niveau du test
    // ══════════════════════════════════════════════

    public function testNiveauBeginnerValide(): void
    {
        $this->assertTrue($this->validator->validateLevel('Beginner'));
    }

    public function testNiveauAdvancedValide(): void
    {
        $this->assertTrue($this->validator->validateLevel('Advanced'));
    }

    public function testNiveauInvalideLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Niveau invalide : Expert');

        $this->validator->validateLevel('Expert');
    }

    // ══════════════════════════════════════════════
    // RÈGLE 4 — Catégorie du test
    // ══════════════════════════════════════════════

    public function testCategorieSpeakingValide(): void
    {
        $this->assertTrue($this->validator->validateCategory('Speaking'));
    }

    public function testCategorieInvalideLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catégorie invalide : Video');

        $this->validator->validateCategory('Video');
    }

    // ══════════════════════════════════════════════
    // RÈGLE 5 — Score entre 0 et 20
    // ══════════════════════════════════════════════

    public function testScoreValide(): void
    {
        $this->assertTrue($this->validator->validateScore(15.5));
        $this->assertTrue($this->validator->validateScore(0));
        $this->assertTrue($this->validator->validateScore(20));
    }

    public function testScoreTropEleveLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le score doit être compris entre 0 et 20.');

        $this->validator->validateScore(25.0);
    }

    public function testScoreNegatifLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->validator->validateScore(-1.0);
    }

    // ══════════════════════════════════════════════
    // RÈGLE 6 — Réussite (score >= 10)
    // ══════════════════════════════════════════════

    public function testScoreReussi(): void
    {
        $this->assertTrue($this->validator->isPassed(10.0));
        $this->assertTrue($this->validator->isPassed(15.5));
        $this->assertTrue($this->validator->isPassed(20.0));
    }

    public function testScoreEchoue(): void
    {
        $this->assertFalse($this->validator->isPassed(9.9));
        $this->assertFalse($this->validator->isPassed(0.0));
        $this->assertFalse($this->validator->isPassed(5.0));
    }

    // ══════════════════════════════════════════════
    // RÈGLE 7 — Calcul pénalité (ExamTimeGuard)
    // ══════════════════════════════════════════════

    public function testPenalite20SecondesRetard(): void
    {
        // 20s de retard → palier 0-30s → 5%
        $this->assertEquals(5, $this->validator->computePenalty(20));
    }

    public function testPenalite60SecondesRetard(): void
    {
        // 60s de retard → palier 30-120s → 15%
        $this->assertEquals(15, $this->validator->computePenalty(60));
    }

    public function testPenalite200SecondesRetard(): void
    {
        // 200s de retard → palier 120-300s → 30%
        $this->assertEquals(30, $this->validator->computePenalty(200));
    }

    public function testPenalite400SecondesRetard(): void
    {
        // 400s de retard → palier +300s → 50%
        $this->assertEquals(50, $this->validator->computePenalty(400));
    }

    public function testPenaliteNegativeLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les secondes de dépassement ne peuvent pas être négatives.');

        $this->validator->computePenalty(-1);
    }

    // ══════════════════════════════════════════════
    // RÈGLE 8 — Application pénalité sur le score
    // ══════════════════════════════════════════════

    public function testApplicationPenalite5Pct(): void
    {
        // score=14, pénalité=5% → 14 * 0.95 = 13.3
        $result = $this->validator->applyPenalty(14.0, 5);
        $this->assertEquals(13.3, $result);
    }

    public function testApplicationPenalite15Pct(): void
    {
        // score=14, pénalité=15% → 14 * 0.85 = 11.9
        $result = $this->validator->applyPenalty(14.0, 15);
        $this->assertEquals(11.9, $result);
    }

    public function testApplicationPenalite50Pct(): void
    {
        // score=14, pénalité=50% → 14 * 0.50 = 7.0
        $result = $this->validator->applyPenalty(14.0, 50);
        $this->assertEquals(7.0, $result);
    }

    public function testPenaliteInvalideLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pourcentage de pénalité doit être entre 0 et 100.');

        $this->validator->applyPenalty(14.0, 150);
    }
}
