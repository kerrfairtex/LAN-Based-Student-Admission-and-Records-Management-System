@echo off
REM TRAC JHS SARMS — local dev bootstrap, Windows entry point.
REM
REM Forwards to the cross-platform PHP core in tools\dev-up.php so
REM there is exactly one source of truth across Unix and Windows.
REM
REM Usage:
REM   tools\dev-up.cmd
REM
REM Requires PHP and PostgreSQL client tools (initdb, pg_ctl, psql,
REM pg_isready) on PATH. See tools\dev-up.php for the full check.

setlocal
cd /d "%~dp0\.."
php tools\dev-up.php
endlocal
