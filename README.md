Grant Metrics
=============

A Wikimedia Foundation tool that provides grantees a simple, easy to use interface for reporting their shared metrics, removing the need for any manual counting.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Build Status](https://travis-ci.org/wikimedia/grantmetrics.svg?branch=master)](https://travis-ci.org/wikimedia/grantmetrics)
[![Code Coverage](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)

## Installation

After cloning the repository, run:

* `composer install`. Use `grantmetrics` as the `database_name`. Fill out your credentials accordingly.
* `php bin/console doctrine:database:create` to create the database.
* `php bin/console doctrine:migrations:migrate` to run the migrations.
* `php bin/console server:start` to start the server.
* You should be up and running at http://localhost:8000

## PHP and framework

There is one internal [Symfony bundle](https://symfony.com/doc/current/bundles.html), called `AppBundle`. It contains a separate directory for the controllers, models, respositories, Twig helpers, and fixtures.

Models are [Doctrine ORM entities](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/working-with-objects.html) that directly correlate to tables in the grantmetrics database. These `grantmetrics` tables in general should not be queried directly with SQL.

Repositories are responsible for querying the replicas, MediaWiki API, file system, etc., wherever external data lives. They do not do any post-processing. Repositories should automatically be assigned to the models, but you may need to set the DI container too. The process would look something like:

```php
$program = new Program([...args]);
$programRepo = new ProgramRepository();
$programRepo->setContainer($container);
$this->setRepository($programRepo);
```

## Assets

Local CSS and JavaScript live in app/Resources/assets. Fonts and vendor assets must be defined in config.yml, and if needed, sourced in the `<head>` of base.html.twig.

Ultimately all compiled assets are copied to the web/ directory (publicly accessible). This should happen automatically, but if not try dumping the assets with `php bin/console assetic:dump`. If you find you have to keep doing this regularly, you can continually watch for changes with `php bin/console assetic:watch`.

## Tests

First make sure your test schema is up-to-date:

* `php bin/console doctrine:database:create --env=test` (first time only).
* `php bin/console doctrine:migrations:migrate --env=test` to run the migrations.

To run the tests, use `./vendor/bin/simple-phpunit tests/`

The test database is automatically populated with the fixtures, which live in `src/DataFixtures/ORM/fixtures.yml`

### Functional tests

Controller tests extend DatabaseAwareWebTestCase, which loads fixtures and ensures full stack traces are shown when there is an HTTP error. Some class properties must be set for this to work:

* `$this->client` - the Symfony client.
* `$this->container` - the DI container.
* `$this->crawler` - the DOM crawler.
* `$this->response` - response of any requests you make.

See `ProgramControllerTest` for an example.
