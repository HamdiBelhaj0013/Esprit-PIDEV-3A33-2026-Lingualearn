<?php

namespace App\EventSubscriber;

use App\Module\PedagogicalContent\Entity\Lesson;
use App\Media\ThumbnailGenerator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Vich\UploaderBundle\Storage\StorageInterface;

final class LessonMediaSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly ThumbnailGenerator $thumbGenerator,
        private readonly string $projectDir,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    private function handle(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Lesson) {
            return;
        }

        // Si pas de vidéo, rien à faire
        if (!$entity->getVideoName()) {
            return;
        }

        // Si thumb existe déjà, ne pas écraser
        if ($entity->getThumbName()) {
            return;
        }

        // Chemin absolu vidéo (géré par Vich)
        $videoPath = $this->storage->resolvePath($entity, 'videoFile');
        if (!$videoPath || !is_file($videoPath)) {
            return;
        }

        // On génère un nom thumb
        $thumbFileName = 'thumb_' . pathinfo($entity->getVideoName(), PATHINFO_FILENAME) . '.jpg';
        $thumbAbsolutePath = $this->projectDir . '/public/uploads/lessons/thumbs/' . $thumbFileName;

        // Générer thumb
        $this->thumbGenerator->generateFromVideo($videoPath, $thumbAbsolutePath);

        // On stocke le nom en DB
        $entity->setThumbName($thumbFileName);
        $entity->setUpdatedAt(new \DateTimeImmutable());

        // ⚠️ postUpdate/postPersist: éviter boucle infinie
        // On ne fait pas flush ici. (On laisse l’admin re-save si besoin)
        // Si tu veux auto-flush, on peut le faire avec EntityManager (mais ça doit être géré proprement).
    }
}