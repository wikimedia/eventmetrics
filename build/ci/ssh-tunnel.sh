#!/usr/bin/expect

spawn ssh -4 -fN \
  -L 4711:s1.web.db.svc.wikimedia.cloud:3306 \
  -L 4712:s2.web.db.svc.wikimedia.cloud:3306 \
  -L 4713:s3.web.db.svc.wikimedia.cloud:3306 \
  -L 4714:s4.web.db.svc.wikimedia.cloud:3306 \
  -L 4715:s5.web.db.svc.wikimedia.cloud:3306 \
  -L 4716:s6.web.db.svc.wikimedia.cloud:3306 \
  -L 4717:s7.web.db.svc.wikimedia.cloud:3306 \
  -L 4718:s8.web.db.svc.wikimedia.cloud:3306 \
  communitytech@tools-dev.wmflabs.org
expect "Enter passphrase for key '/home/travis/.ssh/id_rsa':"
send "\r"
