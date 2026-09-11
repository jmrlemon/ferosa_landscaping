@echo off
REM Ferosa queue worker - processes notifications and SMS jobs.
REM Must be running for customers to receive order/appointment notifications.
REM Auto-restarts if the worker exits (e.g. after a deploy or a crash).

cd /d "%~dp0"

:loop
D:\xampp\php\php.exe artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
echo Worker stopped, restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
