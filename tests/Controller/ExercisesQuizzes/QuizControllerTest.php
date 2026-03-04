<?php

namespace App\Tests\Controller\ExercisesQuizzes;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class QuizControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/exercises/quizzes/quiz');

        self::assertResponseIsSuccessful();
    }
}
