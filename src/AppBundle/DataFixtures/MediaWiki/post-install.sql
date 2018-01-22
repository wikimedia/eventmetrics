-- To be run after MediaWiki and CentralAuth have been installed.
-- Here we update the CI's MediaWiki database to match production, so that you
-- can run the tests locally off of the replicas and they will still pass.
UPDATE centralauth_p.globaluser SET gu_id = 10584730 WHERE gu_name = 'MusikAnimal';
UPDATE centralauth_p.globaluser SET gu_registration = '20110704020217' WHERE gu_name = 'MusikAnimal';
UPDATE centralauth_p.globaluser SET gu_id = 30393229 WHERE gu_name = 'NiharikaKohli';
UPDATE centralauth_p.globaluser SET gu_registration = '20140117135845' WHERE gu_name = 'NiharikaKohli';
UPDATE centralauth_p.globaluser SET gu_id = 6398 WHERE gu_name = 'Samwilson';
UPDATE centralauth_p.globaluser SET gu_registration = '20080530001752' WHERE gu_name = 'Samwilson';
UPDATE centralauth_p.localuser SET lu_wiki = 'enwiki' WHERE lu_wiki = 'enwiki_p';
UPDATE centralauth_p.localuser SET lu_wiki = 'dewiki' WHERE lu_wiki = 'dewiki_p';
UPDATE centralauth_p.localuser SET lu_wiki = 'frwiki' WHERE lu_wiki = 'frwiki_p';

CREATE DATABASE meta_p;
USE meta_p;
CREATE TABLE wiki (
    dbname VARCHAR(32),
    lang VARCHAR(12),
    url TEXT
);
INSERT INTO wiki VALUES('enwiki', 'en', 'https://en.wikipedia.org');

-- This is used only for some integration tests.
-- There is not actually a dewiki installed on the CI build.
INSERT INTO wiki VALUES('dewiki', 'de', 'https://de.wikipedia.org');

GRANT all on meta_p.* to 'root'@'localhost';
