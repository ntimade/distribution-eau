# Distribution d'eau

Gestion multi-distributeurs : clients, compteurs, zones, relevés mensuels, facturation au m³, impayés et paiements.

## Installation

Prérequis : PHP 8.2+, Composer, MySQL/MariaDB.

1. `composer install`
2. Copier `.env.example` vers `.env`, renseigner `DB_*` et `APP_URL`, puis `php artisan key:generate`
3. Créer la base `distribution_eau`, puis `php artisan migrate`
4. `php artisan serve`

## À configurer avant la mise en ligne

La récupération du mot de passe envoie un e-mail : renseigner `MAIL_*` dans `.env`.
Avec `MAIL_MAILER=log` (développement), l'e-mail est écrit dans `storage/logs/laravel.log` et n'est pas envoyé.

Mettre `APP_ENV=production` et `APP_DEBUG=false` : avec `APP_DEBUG=true`, une panne affiche aux clients le détail technique de l'erreur au lieu de la page prévue.

La localisation de l'appareil ne fonctionne qu'en `https` (ou sur `localhost`) : installer un certificat sur le serveur. En `http`, les navigateurs la bloquent, et seul le placement à la main sur la carte reste possible.

## Tests

`php vendor/bin/phpunit`
