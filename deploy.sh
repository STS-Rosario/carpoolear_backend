#!/bin/bash

git fetch
git reset --hard origin/laravel-11

composer dump-autoload
php artisan migrate --force
php artisan optimize 
php artisan config:cache
php artisan pulse:restart

t