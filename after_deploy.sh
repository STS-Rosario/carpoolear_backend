#!/bin/bash

#ssh movilizame@104.131.15.228 -p 2200 'cd  /home/movilizame/sites/carpoolear_dev && ./after_deploy.sh'

composer dump-autoload
php artisan optimize 
php artisan api:cache
php artisan config:cache