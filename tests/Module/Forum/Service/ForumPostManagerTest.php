<?php

namespace App\Tests\Module\Forum\Service;

use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Entity\ForumReply;
use App\Module\Forum\Service\ForumPostManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ForumPostManager.
 *
 * Règles métier testées :
 *  POST  — titre 5–255 chars, contenu 20+ chars, langId 1–5, post inactif non publiable
 *  REPLY — contenu 10–5000 chars, post associé requis, post actif requis, inactive ≠ best answer
 *  REACT — type parmi like/dislike/useful/funny
 *  VIEW  — incrementView() ne crée pas de valeur négative
 *
 * @covers \App\Module\Forum\Service\ForumPostManager
 */
class ForumPostManagerTest extends TestCase
{
    private ForumPostManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ForumPostManager();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeValidPost(): ForumPost
    {
        $post = new ForumPost();
        $post->setTitle('Comment utiliser le subjonctif en français ?');
        $post->setContent('Je voudrais comprendre les cas d\'utilisation du subjonctif en détail.');
        $post->setPlatformLanguageId(1);
        $post->setIsActive(true);
        return $post;
    }

    private function makeValidReply(ForumPost $post): ForumReply
    {
        $reply = new ForumReply();
        $reply->setContent('Le subjonctif s\'utilise après certaines conjonctions comme "bien que".');
        $reply->setPost($post);
        $reply->setIsActive(true);
        return $reply;
    }

    // =========================================================================
    // validatePost — CAS VALIDES
    // =========================================================================

    public function testValidPostPassesValidation(): void
    {
        $post   = $this->makeValidPost();
        $result = $this->manager->validatePost($post);

        $this->assertTrue($result);
    }

    public function testPostWithMinimalTitleAndContentIsValid(): void
    {
        $post = new ForumPost();
        $post->setTitle('Hello'); // exactement 5 chars
        $post->setContent('This content has twenty chars!'); // 30 chars
        $post->setPlatformLanguageId(2);

        $this->assertTrue($this->manager->validatePost($post));
    }

    // =========================================================================
    // validatePost — TITRE INVALIDE
    // =========================================================================

    public function testPostTitleTooShortThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('5 caractères');

        $post = $this->makeValidPost();
        $post->setTitle('Hi'); // 2 chars < 5

        $this->manager->validatePost($post);
    }

    public function testPostTitleEmptyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $post = $this->makeValidPost();
        $post->setTitle('');

        $this->manager->validatePost($post);
    }

    public function testPostTitleTooLongThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('255 caractères');

        $post = $this->makeValidPost();
        $post->setTitle(str_repeat('A', 256)); // 256 chars > 255

        $this->manager->validatePost($post);
    }

    public function testPostTitleExactly255CharsIsValid(): void
    {
        $post = $this->makeValidPost();
        $post->setTitle(str_repeat('A', 255));

        $this->assertTrue($this->manager->validatePost($post));
    }

    // =========================================================================
    // validatePost — CONTENU INVALIDE
    // =========================================================================

    public function testPostContentTooShortThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('20 caractères');

        $post = $this->makeValidPost();
        $post->setContent('Court.'); // < 20 chars

        $this->manager->validatePost($post);
    }

    public function testPostContentEmptyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $post = $this->makeValidPost();
        $post->setContent('');

        $this->manager->validatePost($post);
    }

    public function testPostContentExactly20CharsIsValid(): void
    {
        $post = $this->makeValidPost();
        $post->setContent('12345678901234567890'); // exactement 20

        $this->assertTrue($this->manager->validatePost($post));
    }

    // =========================================================================
    // validatePost — LANGUE INVALIDE
    // =========================================================================

    public function testPostLanguageIdZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('langue');

        $post = $this->makeValidPost();
        $post->setPlatformLanguageId(0);

        $this->manager->validatePost($post);
    }

    public function testPostLanguageIdAbove5ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $post = $this->makeValidPost();
        $post->setPlatformLanguageId(99);

        $this->manager->validatePost($post);
    }

    public function testPostLanguageIdNegativeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $post = $this->makeValidPost();
        $post->setPlatformLanguageId(-1);

        $this->manager->validatePost($post);
    }

    public function testAllValidLanguageIds(): void
    {
        foreach ([1, 2, 3, 4, 5] as $langId) {
            $post = $this->makeValidPost();
            $post->setPlatformLanguageId($langId);
            $this->assertTrue($this->manager->validatePost($post), "Language ID $langId should be valid");
        }
    }

    // =========================================================================
    // assertPostIsPublishable
    // =========================================================================

    public function testActivePostIsPublishable(): void
    {
        $post = $this->makeValidPost();
        $post->setIsActive(true);

        $this->assertTrue($this->manager->assertPostIsPublishable($post));
    }

    public function testInactivePostIsNotPublishable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('inactif');

        $post = $this->makeValidPost();
        $post->setIsActive(false);

        $this->manager->assertPostIsPublishable($post);
    }

    // =========================================================================
    // incrementView
    // =========================================================================

    public function testIncrementViewIncrementsCounter(): void
    {
        $post = $this->makeValidPost();

        $this->manager->incrementView($post);

        $this->assertSame(1, $post->getViewCount());
    }

    public function testIncrementViewMultipleTimes(): void
    {
        $post = $this->makeValidPost();

        $this->manager->incrementView($post);
        $this->manager->incrementView($post);
        $this->manager->incrementView($post);

        $this->assertSame(3, $post->getViewCount());
    }

    // =========================================================================
    // validateReply — CAS VALIDES
    // =========================================================================

    public function testValidReplyPassesValidation(): void
    {
        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);

        $this->assertTrue($this->manager->validateReply($reply));
    }

    // =========================================================================
    // validateReply — CONTENU INVALIDE
    // =========================================================================

    public function testReplyContentTooShortThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('10 caractères');

        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setContent('Court');  // < 10 chars

        $this->manager->validateReply($reply);
    }

    public function testReplyContentEmptyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setContent('');

        $this->manager->validateReply($reply);
    }

    public function testReplyContentTooLongThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('5000 caractères');

        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setContent(str_repeat('A', 5001));

        $this->manager->validateReply($reply);
    }

    public function testReplyContentExactly10CharsIsValid(): void
    {
        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setContent('1234567890'); // exactement 10

        $this->assertTrue($this->manager->validateReply($reply));
    }

    // =========================================================================
    // validateReply — POST INVALIDE
    // =========================================================================

    public function testReplyWithNoPostThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('associée à un post');

        $reply = new ForumReply();
        $reply->setContent('Voici ma réponse détaillée au sujet.');
        $reply->setPost(null);

        $this->manager->validateReply($reply);
    }

    public function testReplyOnInactivePostThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inactif');

        $post = $this->makeValidPost();
        $post->setIsActive(false);

        $reply = $this->makeValidReply($post);

        $this->manager->validateReply($reply);
    }

    // =========================================================================
    // markAsBestAnswer
    // =========================================================================

    public function testMarkActiveReplyAsBestAnswer(): void
    {
        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setIsActive(true);

        $this->manager->markAsBestAnswer($reply);

        $this->assertTrue($reply->isBestAnswer());
    }

    public function testMarkInactiveReplyAsBestAnswerThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('inactive');

        $post  = $this->makeValidPost();
        $reply = $this->makeValidReply($post);
        $reply->setIsActive(false);

        $this->manager->markAsBestAnswer($reply);
    }

    // =========================================================================
    // validateReactionType
    // =========================================================================

    public function testValidReactionTypes(): void
    {
        foreach (['like', 'dislike', 'useful', 'funny'] as $type) {
            $this->assertTrue(
                $this->manager->validateReactionType($type),
                "Type '$type' should be valid"
            );
        }
    }

    public function testInvalidReactionTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $this->manager->validateReactionType('heart');
    }

    public function testEmptyReactionTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->validateReactionType('');
    }

    public function testUppercaseReactionTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Les types sont case-sensitive
        $this->manager->validateReactionType('LIKE');
    }
}