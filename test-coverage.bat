@echo off
REM Run PHPUnit tests with coverage
set XDEBUG_MODE=coverage
vendor\bin\phpunit --coverage-text %*
set XDEBUG_MODE=

