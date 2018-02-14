git pull
composer install
php bin/console cache:clear --env=prod --no-warmup
php bin/console assetic:dump --env=prod
