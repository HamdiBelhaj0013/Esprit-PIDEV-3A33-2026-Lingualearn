# Lancer le projet Lingualearn

## Prérequis

- **PHP** >= 8.2 (extensions : ctype, iconv, json, pdo, mbstring, openssl, intl)
- **Composer**
- **Node.js** et **npm** (pour les assets)
- **MySQL** 8.x (ou MariaDB) — ou adapter `DATABASE_URL` dans `.env`

## 1. Variables d'environnement

- Le fichier `.env` contient déjà `APP_SECRET` et `DATABASE_URL`.
- Pour une config locale (mot de passe DB, etc.), copiez `.env` vers `.env.local` et modifiez :

```bash
# Exemple .env.local
DATABASE_URL="mysql://user:password@127.0.0.1:3306/1lingualearn_db?serverVersion=8.0.32&charset=utf8mb4"
```

## 2. Installer les dépendances

```bash
composer install
npm install
```

## 3. Base de données

Créer la base si elle n’existe pas (MySQL doit être démarré) :

```bash
php bin/console doctrine:database:create
```

Puis appliquer les migrations :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

## 4. Cache et assets

```bash
php bin/console cache:clear
npm run build
```

(En dev, vous pouvez lancer `npm run watch` dans un terminal séparé au lieu de `npm run build`.)

## 5. Lancer l’application

Serveur PHP intégré :

```bash
symfony server:start
```

ou :

```bash
php -S localhost:8000 -t public
```

Puis ouvrir : **http://localhost:8000**

---

## Commandes utiles

| Commande | Description |
|----------|-------------|
| `php bin/console cache:clear` | Vider le cache |
| `php bin/console doctrine:migrations:migrate` | Appliquer les migrations |
| `php bin/console doctrine:schema:validate` | Vérifier le schéma Doctrine |
| `npm run dev` | Compiler les assets une fois |
| `npm run watch` | Recompiler les assets à chaque modification |

## Backoffice Exercices / Quiz

- Liste des **exercices** : http://localhost:8000/admin/exercice
- Liste des **quiz** : http://localhost:8000/admin/quizzes
