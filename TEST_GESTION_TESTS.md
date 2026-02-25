# 🧪 Guide de Test - Gestion des Tests InternationalTests

## 📋 Checklist Rapide

### ✅ Préparation
- [ ] Cache Symfony vidé: `php bin/console cache:clear`
- [ ] Base de données nettoyée (optionnel mais recommandé)
- [ ] Navigateur avec console ouverte (F12)

---

## 🔍 Test 1: Suppression de Questions depuis MockTest/Show

### Étapes:
1. Aller sur `/admin/mock-tests`
2. Cliquer sur "View" (👁️) pour un test qui a des questions
3. Dans la table des questions, cliquer sur le bouton 🗑️ (trash) rouge

### Résultat attendu:
- ✅ Une modale professionnelle s'ouvre
- ✅ Le titre est "Delete Question"
- ✅ Le texte de la question est affiché
- ✅ Deux boutons: "Cancel" et "Yes, Delete"

### Test de Cancel:
4. Cliquer sur "Cancel"
- ✅ La modale se ferme
- ✅ La question n'est PAS supprimée

### Test de Delete:
5. Cliquer à nouveau sur 🗑️
6. Cliquer sur "Yes, Delete"
- ✅ La page se recharge
- ✅ Un message flash vert apparaît: "Question supprimée avec succès !"
- ✅ La question n'apparaît plus dans la liste

---

## ⏱️ Test 2: Timer Auto-Submit (QCM)

### Préparation:
1. Créer un test QCM avec **durée de 1 minute** (pour tester rapidement)
2. Ajouter au moins 3 questions au test
3. Activer le test

### Étapes:
1. Aller sur `/mock-tests` (front)
2. Sélectionner une langue
3. Sélectionner un niveau
4. Cliquer sur "Start Test" pour le test de 1 minute
5. Cliquer sur "Start Exam"
6. **Ouvrir la console du navigateur (F12 → Console)**
7. Attendre que le timer arrive à 00:00

### Résultat attendu dans la console:
```
⏰ Timer expired - Auto-submitting test...
📝 Submitting test...
✅ Form found, submitting...
```

### Résultat attendu dans l'interface:
- ✅ Le formulaire est soumis automatiquement
- ✅ Redirection vers la page de résultats
- ✅ Les résultats s'affichent correctement

---

## 📝 Test 3: Timer Auto-Submit (Writing)

### Préparation:
1. Créer un test avec **testCategory = "Writing"**
2. Durée: 1 minute
3. Ajouter des questions de type "writing"

### Étapes:
1. Passer le test en front
2. Ouvrir la console (F12)
3. Attendre que le timer expire

### Résultat attendu dans la console:
```
⏰ Writing test timer expired - Auto-submitting...
✅ Form found, submitting...
```

---

## 🎤 Test 4: Timer Auto-Submit (Speaking)

### Préparation:
1. Créer un test avec **testCategory = "Speaking"**
2. Durée: 1 minute
3. Ajouter des questions de type "speaking"

### Étapes:
1. Passer le test en front
2. Ouvrir la console (F12)
3. Attendre que le timer expire

### Résultat attendu dans la console:
```
⏰ Speaking test timer expired - Auto-submitting...
✅ Form found, submitting...
```

---

## 🎧 Test 5: Timer Auto-Submit (Listening)

### Préparation:
1. Créer un test avec **testCategory = "Listening"**
2. Durée: 1 minute
3. Ajouter des questions de type "listening"

### Étapes:
1. Passer le test en front
2. Ouvrir la console (F12)
3. Attendre que le timer expire

### Résultat attendu dans la console:
```
⏰ Listening test timer expired - Auto-submitting...
✅ Form found, submitting...
```

---

## 📊 Test 6: Affichage des Résultats

### Préparation:
**IMPORTANT**: Nettoyer d'abord les anciennes données corrompues:
```sql
-- Se connecter à PostgreSQL
psql -U postgres -d lingualearn

-- Supprimer tous les résultats de test
DELETE FROM test_result;
```

### Étapes:
1. Créer un nouveau test QCM avec 10 questions
2. Répartir les questions sur 3 sections (ex: Grammar, Vocabulary, Reading)
3. Passer le test en front
4. Répondre correctement à 7 questions sur 10
5. Soumettre le test

### Résultat attendu:
- ✅ Score affiché: environ 14.0 / 20 (70%)
- ✅ Section Analysis affiche le bon format:
  - "Grammar 2/3 (67%)" ← CORRECT
  - PAS "Grammar 3/2 (150%)" ← INCORRECT
- ✅ Pas de texte garbled (comme "fghjklmlkjhghjn")
- ✅ Pourcentages cohérents (entre 0% et 100%)

---

## 🗑️ Test 7: Suppression de MockTest

### Depuis Index:
1. Aller sur `/admin/mock-tests`
2. Cliquer sur 🗑️ pour un test
3. Vérifier la modale
4. Confirmer

### Depuis Show:
1. Aller sur `/admin/mock-tests`
2. Cliquer sur "View" pour un test
3. Cliquer sur "Delete Test" (bouton rouge en bas)
4. Vérifier la modale
5. Confirmer

### Résultat attendu:
- ✅ Modale professionnelle s'ouvre
- ✅ Message de confirmation clair
- ✅ Test supprimé après confirmation
- ✅ Message flash de succès

---

## ✅ Checklist Finale

Après tous les tests, vérifier:
- [ ] Tous les boutons de suppression fonctionnent
- [ ] Tous les timers auto-submit fonctionnent (QCM, Writing, Speaking, Listening)
- [ ] Les résultats s'affichent correctement (pas de données corrompues)
- [ ] Aucun message d'erreur dans la console
- [ ] Les flash messages s'affichent professionnellement
- [ ] Aucun `alert()` ou `confirm()` JS natif n'apparaît

---

## 🐛 En Cas de Problème

### Timer ne soumet pas automatiquement:
1. Ouvrir la console (F12)
2. Vérifier les logs
3. Si "❌ Form not found!" apparaît → problème avec l'ID du formulaire
4. Vérifier que le formulaire a bien l'ID correct (`examForm`, `writingForm`, etc.)

### Section Analysis affiche des données incorrectes:
1. Supprimer tous les résultats: `DELETE FROM test_result;`
2. Vider le cache: `php bin/console cache:clear`
3. Refaire un test propre

### Bouton delete ne fonctionne pas:
1. Vérifier la console pour les erreurs JavaScript
2. Vérifier que le CSRF token est généré
3. Vérifier que la route existe

---

## 📞 Support

Si un problème persiste:
1. Copier les logs de la console
2. Copier les erreurs Symfony (dans `var/log/dev.log`)
3. Faire une capture d'écran du problème
4. Décrire les étapes pour reproduire


