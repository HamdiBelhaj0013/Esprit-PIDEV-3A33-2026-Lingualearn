# Améliorations QCM et Reading - Module InternationalTests

## ✅ Modifications Complétées

### 1. **Entité TestQuestion Améliorée**

**Fichier:** `src/Module/InternationalTests/Entity/TestQuestion.php`

**Nouveaux champs ajoutés:**
- `questionType` (string, 50 caractères) - Type de question avec 3 valeurs possibles:
  - `qcm_single` - QCM à choix unique (une seule réponse correcte)
  - `qcm_multiple` - QCM à choix multiples (plusieurs réponses correctes)
  - `reading` - Question de compréhension écrite avec texte de lecture

- `readingPassage` (text, nullable) - Texte de lecture pour les questions de type Reading

**Nouvelle méthode:**
```php
public function isAnswerCorrect(?string $userAnswer): bool
```
Cette méthode gère intelligemment la validation pour tous les types de questions:
- **QCM single**: Comparaison exacte de la réponse
- **QCM multiple**: Compare les réponses séparées par `|` (pipe)
- **Reading**: Comparaison exacte de la réponse complète

---

### 2. **Formulaire TestQuestionType Amélioré**

**Fichier:** `src/Module/InternationalTests/Form/TestQuestionType.php`

**Nouveaux champs:**
- **Question Type** (dropdown) - Sélection du type de question
- **Reading Passage** (textarea) - Zone pour entrer le texte de lecture (optionnel)

**Améliorations:**
- Placeholders et help text en français
- Classes Bootstrap pour un meilleur style
- Instructions claires pour chaque champ

**Format des options (JSON):**
```json
["Un comparatif de supériorité", "Un superlatif", "Un comparatif d'égalité", "Un comparatif d'infériorité"]
```

**Format de la réponse correcte:**
- **QCM single**: `"Un comparatif de supériorité"`
- **QCM multiple**: `"Option1|Option2|Option3"` (séparées par pipe)
- **Reading**: `"La réponse complète en texte"`

---

### 3. **Template take.html.twig Amélioré**

**Fichier:** `templates/internationaltests/mocktest_front/take.html.twig`

**Nouvelles fonctionnalités:**

1. **Affichage du Reading Passage** (si présent):
   - Zone stylée avec icône 📖
   - Texte formaté avec espacement approprié
   - Affiché avant la question

2. **Support des Checkboxes pour QCM Multiple**:
   - Détection automatique du type de question
   - Radio buttons pour choix unique
   - Checkboxes pour choix multiples
   - Instructions visuelles pour les questions à choix multiples

3. **Gestion de tous les formats JSON**:
   - Tableau simple: `["option1", "option2"]`
   - Objet: `{"A": "option1", "B": "option2"}`
   - Tableau d'objets: `[{"text": "option1"}]`

---

### 4. **Contrôleur MockTestFrontController Amélioré**

**Fichier:** `src/Module/InternationalTests/Controller/Front/MockTestFrontController.php`

**Méthode `submit()` mise à jour:**
- Détection automatique des réponses multiples (array)
- Conversion des arrays en string avec séparateur `|`
- Utilisation de la méthode `isAnswerCorrect()` de l'entité
- Ajout des champs `questionType` et `readingPassage` dans les résultats détaillés

---

### 5. **Migration de Base de Données**

**Commandes exécutées:**
```bash
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:schema:update --force
```

**Modifications appliquées:**
- Ajout de `question_type` (VARCHAR 50, default 'qcm_single')
- Ajout de `reading_passage` (LONGTEXT, nullable)
- Modification de `correct_answer` en LONGTEXT (pour supporter les réponses longues)

---

## 📋 Comment Utiliser le Nouveau Système

### Créer une Question QCM à Choix Unique

1. Allez sur `/internationaltests/testquestion/new`
2. Sélectionnez **"QCM - Choix unique"** dans Question Type
3. Entrez la question dans **Question Text**
4. Dans **Options de réponse (JSON)**, entrez:
   ```json
   ["Un comparatif de supériorité", "Un superlatif", "Un comparatif d'égalité", "Un comparatif d'infériorité"]
   ```
5. Dans **Correct Answer**, entrez exactement l'une des options:
   ```
   Un comparatif de supériorité
   ```

### Créer une Question QCM à Choix Multiples

1. Sélectionnez **"QCM - Choix multiples"**
2. Entrez les options comme pour un QCM simple
3. Dans **Correct Answer**, séparez les bonnes réponses par `|`:
   ```
   Option correcte 1|Option correcte 2
   ```

### Créer une Question de Reading

1. Sélectionnez **"Reading - Compréhension écrite"**
2. Dans **Reading Passage**, entrez le texte à lire:
   ```
   Marie se lève tôt chaque matin pour aller à l'école. 
   Elle prend son petit-déjeuner à 7h30 et part à 8h00.
   ```
3. Dans **Question Text**, entrez la question:
   ```
   À quelle heure Marie prend-elle son petit-déjeuner ?
   ```
4. Dans **Options**, entrez les choix de réponse:
   ```json
   ["À 7h30", "À 8h00", "À 7h00", "À 8h30"]
   ```
5. Dans **Correct Answer**:
   ```
   À 7h30
   ```

---

## ⚠️ Important - Règles à Respecter

1. **Correspondance Exacte**: La réponse dans "Correct Answer" doit correspondre EXACTEMENT à l'une des options (même casse, même ponctuation)

2. **Format JSON Valide**: Les options doivent être un tableau JSON valide

3. **Séparateur pour Multiples**: Pour QCM multiple, utilisez le caractère `|` (pipe) sans espaces autour

4. **Texte Complet**: Les options doivent contenir le texte complet de la réponse, pas juste des lettres (A, B, C, D)

---

## 🎯 Prochaines Étapes

1. ✅ **Éditer vos questions existantes** pour ajouter de vraies options professionnelles
2. ✅ **Tester le flux complet** en passant un test avec différents types de questions
3. ✅ **Vérifier les résultats** pour s'assurer que le scoring fonctionne correctement

---

**Date:** 2026-02-20  
**Module:** InternationalTests  
**Status:** ✅ Système QCM professionnel implémenté avec succès

