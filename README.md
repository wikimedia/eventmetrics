Event Metrics
=============

A Wikimedia Foundation tool that provides event organizers and grantees a simple, easy to use interface for reporting their shared metrics, removing the need for any manual counting.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Build Status](https://travis-ci.org/wikimedia/eventmetrics.svg?branch=master)](https://travis-ci.org/wikimedia/eventmetrics)
[![Test Coverage](https://api.codeclimate.com/v1/badges/8e85e93cd9f6848bebfc/test_coverage)](https://codeclimate.com/github/wikimedia/eventmetrics/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/8e85e93cd9f6848bebfc/maintainability)](https://codeclimate.com/github/wikimedia/eventmetrics/maintainability)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/eventmetrics/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/eventmetrics/?branch=master)

## Installation for development

### With Docker

Prerequisites: Docker, and Docker Compose.

After cloning the repository run `docker-compose up`.
Event Metrics will be available at [http://localhost:8000](http://localhost:8000).

### Local install

Prerequisites:

* PHP 7.2 and MySQL.
* A [Wikimedia developer account](https://wikitech.wikimedia.org/wiki/Help:Create_a_Wikimedia_developer_account) with which to access the Wikimedia database replicas.

After cloning the repository:

1. Create a local database called e.g. `eventmetrics`.
2. Run `composer install` (this will prompt for some configuration values):
   * Fill out your local database credentials according to your local configuration;
     those for `database.replica.user` and `database.replica.password` can be found in
     your `replica.my.cnf` file in the home directory of your account on Toolforge.
   * `app.logged_in_user` is used to mock the current user, instead of going through OAuth. Must be a valid Wikimedia username. In production this should be `null`.
3. Open a tunnel to the WMF databases: `ssh -L 4711:enwiki.web.db.svc.eqiad.wmflabs:3306 -L 4712:tools-db:3306 tools-dev.wmflabs.org -l your-username`
  (where `your-username` is your Wikimedia developer account username).
4. `./bin/console server:start` to start the server (shortcut: `s:s`).
5. You should be up and running at http://localhost:8000

To update: after pulling the latest code, run `composer install`.

## Usage

The web interface is hopefully straightforward to use. However developers can also do some functionality via the console. In the same directory as the application:

* `php bin/console app:process-event <eventId>` - will generate [`EventStat`](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/Model/EventStat.php)s for the Event with the ID `<eventId>`.
* `php bin/console app:spawn-jobs` - queries the [Job queue](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/Model/Job.php) and runs `app:process-event` for Events that are in the queue. There is a limit on the number of concurrent jobs to ensure the database quota on the replicas is not exceeded.

## PHP and framework

There is one internal [Symfony bundle](https://symfony.com/doc/current/bundles.html), called `AppBundle`. It contains a separate directory for the controllers, models, repositories, Twig helpers, and fixtures.

Models are [Doctrine ORM entities](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/working-with-objects.html) that directly correlate to tables in the `eventmetrics` database. Database interaction should generally be done with Doctrine's `EntityManager`.

Repositories are responsible for querying the replicas, MediaWiki API, file system, etc., wherever external data lives.
They do not do any post-processing.
Repositories should automatically be assigned to the models, and can be injected wherever they're required (via type-hinted parameters).

## Assets

Assets are managed with [Webpack Encore](https://github.com/symfony/webpack-encore).
Local CSS and JavaScript live in [app/Resources/assets](https://github.com/wikimedia/eventmetrics/tree/master/app/Resources/assets).
Fonts and vendor assets must be defined in [webpack.config.js](https://github.com/wikimedia/eventmetrics/blob/master/webpack.config.js),
and if needed, sourced in the `<head>` of [base.html.twig](https://github.com/wikimedia/eventmetrics/blob/master/app/Resources/views/base.html.twig).

On compilation, all assets are copied to the `public/assets/` directory (publicly accessible).
This happens by running `./node_modules/.bin/encore production` (or `dev` if you don't want the files to be minified and versioned).
You can also continually watch for file changes with `./node_modules/.bin/encore production --watch`.

## i18n

All messages live in the i18n/ directory.

For PHP, [Intuition](https://packagist.org/packages/krinkle/intuition) is used. Within the views, you can access a message using the `msg('message-key', ['arg1', 'arg2', ...])` function. Intuition is not available outside the views, but you probably don't need it in those cases anyway.

When working with model validations, you'll provide the message key and parameters that will in turn get passed into the view. For basic constraints, just put the key name. For instance `@UniqueEntity("title", message="error-program-title-dup")` for a duplicate program title. The name of the program is automatically passed in as the first parameter in the message. If you need to pass a variable, use the `payload` options, e.g. `@Assert\NotNull(message="error-invalid", payload={"0"="start-date"})`.

For [custom callbacks](https://symfony.com/doc/current/reference/constraints/Callback.html), use the validation builder and set the parameters accordingly. For instance, to validate that a program title is not reserved:

```php
if (in_array($this->title, ['edit', 'delete'])) {
    $context->buildViolation('error-program-title-reserved')
        ->setParameter(0, '<code>edit</code>, <code>delete</code>')
        ->atPath('title')
        ->addViolation();
}
```

In JavaScript, we use [jquery.i18n](https://github.com/wikimedia/jquery.i18n). The syntax is `$.i18n('message-key', 'arg1', 'arg2', ...)`.

## Tests

Use `composer test` to run the full test suite. The individual commands that it runs are as follows:

* `composer migrate-test` – Creates and migrates the test database.
* `composer lint` – tests for linting errors in PHP, Twig and YAML files, and uses [MinusX](https://www.mediawiki.org/wiki/MinusX) to ensure files have the correct permissions.
* `composer docs` – Validates PHP block-level documentation. If [phpDocumentor](https://www.phpdoc.org/) is not already installed, it will automatically be downloaded into the root of the repo, and will be ignored via .gitignore.
* `composer unit` – Runs unit and integration tests with [PHPUnit](https://phpunit.de/).

Most CodeSniffer and MinusX errors can be fixed automatically using `composer fix`.

The test database is automatically populated with the fixtures, which live in `src/DataFixtures/ORM`. This data, along with what is populated in [install-mediawiki.sh](https://github.com/wikimedia/eventmetrics/blob/master/build/ci/install-mediawiki.sh), are intended to mimic production data so that you can run the tests locally against the replicas and get the same results as the test MediaWiki installation that is used for the CI build. The [basic fixture set](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/DataFixtures/ORM/basic.yml) is loaded by default. The [extended set](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/DataFixtures/ORM/extended.yml) supplies a lot more test data, and is meant for testing beyond the workflow of creating events, etc., such as statistics generation.

Repository classes should not need tests. Add `@codeCoverageIgnore` to the bottom of the class summary so that coverage statistics are not affected.

### Functional/integration tests

Controller tests extend [`DatabaseAwareWebTestCase`](https://github.com/wikimedia/eventmetrics/blob/master/tests/AppBundle/Controller/DatabaseAwareWebTestCase.php), which loads fixtures and ensures full stack traces are shown when there is an HTTP error. Some class properties must be set for this to work:

* `$this->client` - the Symfony client.
* `$this->container` - the DI container.
* `$this->crawler` - the DOM crawler.
* `$this->response` - response of any requests you make.

See [`ProgramControllerTest`](https://github.com/wikimedia/eventmetrics/blob/master/tests/AppBundle/Controller/ProgramControllerTest.php) for an example.

## Deployment

For maintainer documentation, see https://wikitech.wikimedia.org/wiki/Tool:Event_Metrics

The application currently is running on WMF's VPS environment at https://eventmetrics.wmflabs.org

Deployment happens automatically after a new version tag is created.
