<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExercisesQuizzes/QuizControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/exercises/quizzes/quiz');

        self::assertResponseIsSuccessful();
    }
}
