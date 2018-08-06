#!/usr/bin/expect

spawn ssh -4 -fN -L 4711:enwiki.web.db.svc.eqiad.wmflabs:3306 communitytech@tools-dev.wmflabs.org
expect "Enter passphrase for key '/home/travis/.ssh/id_rsa':"
send "\r"
