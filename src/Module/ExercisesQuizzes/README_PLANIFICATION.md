# Module ExercisesQuizzes — Planification des quiz (type Google Calendar)

## Vue d’ensemble

Ce module inclut une **planification des quiz** pour l’étudiant : date/heure + note, annulation, et passage automatique en « terminé » quand le quiz est soumis.

- **Worked / not worked** : basé sur `UserLessonStatus` (tentative enregistrée = quiz travaillé).
- **Sécurité** : l’étudiant ne peut planifier que pour lui-même (vérifié dans le service et les contrôleurs).
- **Perf** : chargement en batch des planifications (`getScheduleMap`) pour la liste des quiz.

---

## Fichiers de la planification (référence 100 %)

### Entité & persistance
| Fichier | Rôle |
|---------|------|
| `Entity/QuizSchedule.php` | Entité : student, quiz, scheduledAt, status (SCHEDULED/DONE/CANCELLED), note, createdAt, updatedAt |
| `Repository/QuizScheduleRepository.php` | `getScheduleMapForUserAndQuizIds`, `findOneScheduledByUserAndQuiz`, `findLatestByUserAndQuiz` |

### Service métier
| Fichier | Rôle |
|---------|------|
| `Service/QuizPlanningService.php` | `schedule()`, `reschedule()`, `cancel()`, `getScheduleMap()`, `markDoneForUserAndQuiz()` — validation et sécurité (étudiant = propriétaire) |

### Formulaire
| Fichier | Rôle |
|---------|------|
| `Form/QuizScheduleType.php` | Champs : scheduledAt (date/heure), note (optionnel) — contrainte « date dans le futur » |

### Contrôleurs
| Fichier | Rôle |
|---------|------|
| `Controller/UserPracticeController.php` | Liste des quiz : injecte `QuizPlanningService`, construit `scheduleMap` en batch, ajoute `schedule` et `canSchedule` par item (worked = `UserLessonStatus` existant) |
| `Controller/UserQuizScheduleController.php` | `GET/POST /learn/practice/quiz/{id}/schedule` (planifier/modifier), `POST /learn/practice/quiz/schedule/{scheduleId}/cancel` — interdit si quiz déjà travaillé |
| `Controller/UserQuizController.php` | Après soumission du quiz : appelle `markDoneForUserAndQuiz()` pour passer une planification SCHEDULED en DONE |

### Templates (module uniquement)
| Fichier | Rôle |
|---------|------|
| `templates/exercises_quizzes/learn/practice.html.twig` | Liste : badge « Non travaillé », si planifié affiche la date, si travaillé badge « Terminé » ; bouton Planifier (si `canSchedule`) et formulaire d’annulation |
| `templates/exercises_quizzes/learn/schedule_form.html.twig` | Formulaire planification : date/heure + note, lien retour vers `learn_practice` |

### Migration
| Fichier | Rôle |
|---------|------|
| `migrations/Version20260220180000.php` | Création de la table `quiz_schedule` (student_id, quiz_id, scheduled_at, status, note, created_at, updated_at) |

---

## Routes (planification)

- **Liste des quiz** : `GET /learn/practice` → `learn_practice`
- **Planifier / modifier** : `GET|POST /learn/practice/quiz/{id}/schedule` → `learn_practice_quiz_schedule`
- **Annuler** : `POST /learn/practice/quiz/schedule/{scheduleId}/cancel` → `learn_practice_quiz_schedule_cancel`

---

## Comportement

1. **Liste** : un seul appel à `getScheduleMap($user, $quizIds)` charge toutes les planifications SCHEDULED pour les quiz affichés.
2. **Worked** : `hasAttempted = (UserLessonStatus pour user+lesson existe)` ; `canSchedule = !hasAttempted`.
3. **Planifier** : possible uniquement si le quiz est dans la liste practice (leçon + exercices activés) et pas encore travaillé.
4. **Quiz terminé** : dans `UserQuizController::submit`, après `flush`, `markDoneForUserAndQuiz($user, $quiz)` met à jour une éventuelle planification SCHEDULED en DONE (sans toucher à d’autres modules).
