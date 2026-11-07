@echo off
cd C:\xampp\mysql\bin
mysqld --defaults-file=my.ini --user=root > NUL 2>&1
timeout /t 5 > NUL