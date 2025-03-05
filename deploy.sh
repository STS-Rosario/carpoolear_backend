#!/bin/bash

git fetch
git reset --hard origin/laravel-11

composer dump-autoload
php artisan optimize 
php artisan config:cache

t