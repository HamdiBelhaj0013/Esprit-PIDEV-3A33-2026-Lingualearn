@echo off
echo === Lingualearn - Setup ===
cd /d "%~dp0"

echo.
echo [1/5] Composer install...
call composer install --no-interaction
if errorlevel 1 goto :error

echo.
echo [2/5] NPM install...
call npm install
if errorlevel 1 goto :error

echo.
echo [3/5] Cache clear...
php bin/console cache:clear
if errorlevel 1 goto :error

echo.
echo [4/5] Database (create + migrate)...
php bin/console doctrine:database:create --if-not-exists 2>nul
php bin/console doctrine:migrations:migrate --no-interaction
if errorlevel 1 (
    echo Attention: MySQL doit etre demarre. Verifiez DATABASE_URL dans .env
    pause
)

echo.
echo [5/5] Build assets...
call npm run build
if errorlevel 1 goto :error

echo.
echo === Termine ===
echo Lancer le serveur: php -S localhost:8000 -t public
echo Puis ouvrir: http://localhost:8000
pause
exit /b 0

:error
echo Erreur lors du setup.
pause
exit /b 1
