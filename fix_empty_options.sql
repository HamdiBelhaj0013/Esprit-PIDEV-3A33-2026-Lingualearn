-- ═══════════════════════════════════════════════════════════════
-- Script SQL pour corriger les questions avec options vides
-- ═══════════════════════════════════════════════════════════════
-- Date: 2026-02-24
-- Module: InternationalTests
-- Problème: Les questions QCM ont options = [] (vide)
-- Solution: Ajouter des options de test pour chaque question
-- ═══════════════════════════════════════════════════════════════

-- IMPORTANT: Ce script ajoute des options GÉNÉRIQUES pour tester.
-- Vous devez ensuite éditer chaque question via l'interface admin
-- pour mettre les vraies options professionnelles.

-- ═══════════════════════════════════════════════════════════════
-- ÉTAPE 1: Vérifier les questions avec options vides
-- ═══════════════════════════════════════════════════════════════

SELECT id, question_text, options, question_type, section_category
FROM test_question
WHERE options = '[]' OR options IS NULL OR options = '{}';

-- ═══════════════════════════════════════════════════════════════
-- ÉTAPE 2: Mettre à jour les questions avec des options de test
-- ═══════════════════════════════════════════════════════════════

-- Pour les questions de type QCM (qcm, qcm_single, qcm_multiple)
UPDATE test_question
SET options = '{"A": "Option A", "B": "Option B", "C": "Option C", "D": "Option D"}'::json
WHERE (options = '[]' OR options IS NULL OR options = '{}')
  AND question_type IN ('qcm', 'qcm_single', 'qcm_multiple');

-- Pour les questions de type Reading Comprehension
UPDATE test_question
SET options = '{"A": "True", "B": "False", "C": "Not mentioned", "D": "Cannot determine"}'::json
WHERE (options = '[]' OR options IS NULL OR options = '{}')
  AND question_type = 'reading';

-- ═══════════════════════════════════════════════════════════════
-- ÉTAPE 3: Vérifier que les options ont été ajoutées
-- ═══════════════════════════════════════════════════════════════

SELECT id, question_text, options, question_type
FROM test_question
WHERE question_type IN ('qcm', 'qcm_single', 'qcm_multiple', 'reading')
ORDER BY id;

-- ═══════════════════════════════════════════════════════════════
-- NOTES IMPORTANTES
-- ═══════════════════════════════════════════════════════════════

-- 1. Ces options sont GÉNÉRIQUES et pour TESTER seulement
-- 2. Vous DEVEZ éditer chaque question via /admin/test-questions
-- 3. Remplacer les options génériques par les vraies options
-- 4. Vérifier que correct_answer correspond à une des clés (A, B, C, D)

-- Exemple de bonnes options pour une question de grammaire:
-- {
--   "A": "I am going to the store",
--   "B": "I goes to the store",
--   "C": "I going to the store",
--   "D": "I gone to the store"
-- }

-- Exemple de bonnes options pour une question de vocabulaire:
-- {
--   "A": "Happy",
--   "B": "Sad",
--   "C": "Angry",
--   "D": "Excited"
-- }

-- ═══════════════════════════════════════════════════════════════
-- COMMANDES POUR EXÉCUTER CE SCRIPT
-- ═══════════════════════════════════════════════════════════════

-- Option 1: Via psql
-- psql -U postgres -d lingualearn -f fix_empty_options.sql

-- Option 2: Via Symfony console
-- php bin/console doctrine:query:sql "UPDATE test_question SET options = '{\"A\": \"Option A\", \"B\": \"Option B\", \"C\": \"Option C\", \"D\": \"Option D\"}'::json WHERE (options = '[]' OR options IS NULL OR options = '{}') AND question_type IN ('qcm', 'qcm_single', 'qcm_multiple');"

-- ═══════════════════════════════════════════════════════════════
-- FIN DU SCRIPT
-- ═══════════════════════════════════════════════════════════════

