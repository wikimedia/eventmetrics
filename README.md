Grant Metrics
=============

A Wikimedia Foundation tool that provides grantees a simple, easy to use interface for reporting their shared metrics, removing the need for any manual counting.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Build Status](https://travis-ci.org/wikimedia/grantmetrics.svg?branch=master)](https://travis-ci.org/wikimedia/grantmetrics)
[![Code Coverage](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)

Installation
============

After cloning the repository, run:

* `composer install`. Use `grantmetrics` as the `database_name`. Fill out your credentials accordingly.
* `php bin/console doctrine:database:create` to create the database.
* `php bin/console doctrine:migrate` to run the migrations.
* `php bin/console server:start` to start the server.
* You should be up and running at http://localhost:8000
