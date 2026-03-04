<?php

namespace App\Tests\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Service\ReclamationManager;
use PHPUnit\Framework\TestCase;

class ReclamationManagerTest extends TestCase
{
    // ================================================================
    // TEST 1 : Réclamation valide → doit retourner true
    // ================================================================
    public function testReclamationValide(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setSubject('Problème de connexion');
        $reclamation->setMessageBody('Je narrive pas à me connecter à mon compte.');
        $reclamation->setPriority(Reclamation::PRIORITY_MEDIUM);

        $manager = new ReclamationManager();

        $this->assertTrue($manager->validate($reclamation));
    }

    // ================================================================
    // TEST 2 : Sujet vide → doit lancer une exception
    // ================================================================
    public function testSujetObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le sujet de la réclamation est obligatoire.');

        $reclamation = new Reclamation();
        $reclamation->setSubject('');
        $reclamation->setMessageBody('Un message valide.');
        $reclamation->setPriority(Reclamation::PRIORITY_LOW);

        $manager = new ReclamationManager();
        $manager->validate($reclamation);
    }
}