#! /bin/bash

# Some code courtesy of the mediawiki-api-base contributors, released under GPL-2.0
# https://github.com/addwiki/mediawiki-api-base/blob/master/build/travis/install-mediawiki.sh

set -x

originalDirectory=$(pwd)

if [[ $TRAVIS_PHP_VERSION == *"hhvm"* ]]
then
    PHPINI=/etc/hhvm/php.ini
    echo "hhvm.enable_zend_compat = true" >> $PHPINI
fi

mkdir ./../web
cd ./../web

wget https://github.com/wikimedia/mediawiki/archive/$MW.tar.gz
tar -zxf $MW.tar.gz
mv mediawiki-$MW w
ln -s ./w ./wiki

cd w

composer self-update
composer install

mysql -e 'CREATE DATABASE enwiki_p;'
php maintenance/install.php --dbtype mysql --dbuser root --dbname enwiki_p --dbpath $(pwd) --pass CIPass Wikipedia CIUser
php maintenance/createAndPromote.php MusikAnimal 1234abcd --wiki enwiki_p
php maintenance/createAndPromote.php NiharikaKohli 1234abcd --wiki enwiki_p
php maintenance/createAndPromote.php Samwilson 1234abcd --wiki enwiki_p

# Installing multiple installations of MediaWiki takes a long time,
# so we'll use just one wiki (enwiki_p) in our tests, for now...

php maintenance/importDump.php --conf LocalSettings.php $originalDirectory/src/AppBundle/DataFixtures/MediaWiki/enwiki_p.xml --wiki enwiki_p

echo '
require_once "$IP/extensions/CentralAuth/CentralAuth.php";

$wgCentralAuthAutoNew = true;
$wgCentralAuthDatabase = "centralauth_p";
$wgCentralAuthAutoMigrate = true;
$wgCentralAuthCreateOnView = true;
$wgCentralAuthSilentLogin = true;
$wgCentralAuthDryRun = false;
$wgConf = new SiteConfiguration;
$wgLocalDatabases = [
    "enwiki_p",
];
$wgConf->wikis = $wgLocalDatabases;
$wgConf->suffixes = ["wiki_p"];
$wgConf->localVHosts = ["localhost"];
$wgConf->siteParamsCallback = "efGetSiteParams";
$wgConf->extractAllGlobals($wgDBname);

$wgConf->settings = [
    "wgServer" => [
        "default" => "http://localhost",
    ],
    "wgCanonicalServer" => [
        "default" => "http://localhost",
    ],

    "wgScriptPath" => [
        "enwiki_p" => "/enwiki",
    ],

    "wgArticlePath" => [
        "enwiki_p" => "/enwiki/\$1", //for short urls
    ],

    "wgLanguageCode" => [
        "default" => "\$lang",
    ],

    "wgLocalInterwiki" => [
        "default" => "\$lang",
    ],
];

function efGetSiteParams($conf, $wiki) {
    $site = null;
    $lang = null;
    foreach($conf->suffixes as $suffix) {
        if (substr($wiki, -strlen($suffix)) == $suffix) {
            $site = $suffix;
            $lang = substr($wiki, 0, -strlen($suffix));
            break;
        }
    }
    return [
        "suffix" => $site,
        "lang" => $lang,
        "params" => [
            "lang" => $lang,
            "site" => $site,
            "wiki" => $wiki,
        ],
        "tags" => [],
    ];
}' >> LocalSettings.php

cd extensions
git clone https://github.com/wikimedia/mediawiki-extensions-CentralAuth.git CentralAuth
cd CentralAuth
mysql -u root -e "CREATE DATABASE centralauth_p; USE centralauth_p; SOURCE central-auth.sql; GRANT all on centralauth_p.* to 'root'@'localhost';"
php maintenance/migratePass0.php
php maintenance/migratePass1.php

mysql -u root -e "SOURCE $originalDirectory/src/AppBundle/DataFixtures/MediaWiki/post-install.sql"

cd $originalDirectory
