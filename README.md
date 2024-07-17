Event Metrics
=============

A Wikimedia Foundation tool that provides event organizers and grantees a simple, easy to use interface for reporting their shared metrics, removing the need for any manual counting.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![CI](https://github.com/wikimedia/eventmetrics/actions/workflows/ci.yaml/badge.svg)](https://github.com/wikimedia/eventmetrics/actions/workflows/ci.yaml)

## Installation for development

Prerequisites:

* PHP 8.1+ and MySQL 5.7+.
* [Composer](https://getcomposer.org/)
* A [Wikimedia developer account](https://wikitech.wikimedia.org/wiki/Help:Create_a_Wikimedia_developer_account) with which to access the Toolforge replicas.
* [Symfony CLI](https://symfony.com/download) for running the server.

After cloning the repository:

1. Copy [.env.dist](.env.dist) to `.env` and fill in the necessary values:
   * Values for `DATABASE_REPLICA_USER` and `DATABASE_REPLICA_PASSWORD` can be found in
     your `replica.my.cnf` file in the home directory of your account on Toolforge.
   * `APP_LOGGED_IN_USER` is used to mock the current user, instead of going through OAuth.
     Must be a valid Wikimedia username.
2. Run `composer install`.
3. Open a tunnel to the WMF databases: `symfony console toolforge:ssh`.
4. `symfony server start` to start the server.
5. You should be up and running at http://localhost:8000

To update: after pulling the latest code, run `composer install`.

## Usage

The web interface is hopefully straightforward to use. However, developers can also do some
functionality via the console. In the same directory as the application:

* `symfony console app:process-event <eventId>` - will generate [`EventStat`](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/Model/EventStat.php)s for the Event with the ID `<eventId>`.
* `symfony console app:spawn-jobs` - queries the [Job queue](https://github.com/wikimedia/eventmetrics/blob/master/src/AppBundle/Model/Job.php) and runs `app:process-event`
  for Events that are in the queue. There is a limit on the number of concurrent jobs to
  ensure the database quota on the replicas is not exceeded.

## PHP and framework

Event Metrics uses the [Symfony](https://symfony.com/) framework.

Models are [Doctrine ORM entities](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/working-with-objects.html) that directly correlate to tables in the `eventmetrics` database. Database interaction should generally be done with Doctrine's `EntityManager`.

Repositories are responsible for querying the replicas, MediaWiki API, file system, etc., wherever external data lives.
They do not do any post-processing.
Repositories should automatically be assigned to the models, and can be injected wherever they're required (via type-hinted parameters).

## Assets

Assets are managed by [Webpack Encore](https://symfony.com/doc/current/frontend.html).
The entry point is [assets/js/application.js](assets/js/application.js), and the output is in `public/build/`.
Compiled assets must be committed to the repository.

* `npm run build` - compiles assets for production.
* `npm run watch` - compiles assets for development and watches for changes.
* `npm run dev` - compiles assets for development without watching.

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

For maintainer documentation, see https://wikitech.wikimedia.org/wiki/Nova_Resource:Eventmetrics

The application currently is running on WMF's VPS environment at https://eventmetrics.wmcloud.org

Deployment happens automatically after a new version tag is created.
