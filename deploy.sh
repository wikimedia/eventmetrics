#!/bin/bash
if [ ! -z "$1" ]; then
  git fetch origin
  git checkout -t origin/$1
  git checkout $1
  git pull origin $1
else
  git checkout master
  git pull origin master
fi
composer install
php bin/console cache:clear --env=prod --no-warmup
php bin/console assetic:dump --env=prod
