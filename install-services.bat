@echo off
REM ---------------------------------------------------------------------------
REM Ferosa background services installer.
REM
REM Registers the two processes that run outside every web request:
REM
REM   Queue worker - sends every mail and notification in the system. Stopped,
REM                  customers get no booking confirmation and no order update,
REM                  and nothing errors anywhere to tell you.
REM   Scheduler    - 08:00 visit reminders and the 02:00 nightly db:backup.
REM                  MySQL here has no binary logging, so those dumps are the
REM                  only route back from data loss.
REM
REM RIGHT-CLICK THIS FILE AND CHOOSE "Run as administrator".
REM Registering a scheduled task needs elevation; a normal terminal cannot.
REM
REM To undo:  schtasks /delete /tn "Ferosa Queue Worker" /f
REM           schtasks /delete /tn "Ferosa Scheduler" /f
REM ---------------------------------------------------------------------------

setlocal
set "APPDIR=%~dp0"
if "%APPDIR:~-1%"=="\" set "APPDIR=%APPDIR:~0,-1%"
set "PHP=D:\xampp\php\php.exe"

echo.
echo  Ferosa background services
echo  ==========================
echo.

net session >nul 2>&1
if errorlevel 1 (
    echo  [X] Not running as administrator.
    echo.
    echo      Close this window, right-click install-services.bat,
    echo      and choose "Run as administrator".
    echo.
    pause
    exit /b 1
)

if not exist "%PHP%" (
    echo  [X] PHP not found at %PHP%
    echo      Edit the PHP line near the top of this file to match your XAMPP path.
    echo.
    pause
    exit /b 1
)

if not exist "%APPDIR%\artisan" (
    echo  [X] artisan not found in %APPDIR%
    echo      This file must stay inside the ferosa-laravel folder.
    echo.
    pause
    exit /b 1
)

echo  Registering the queue worker...
schtasks /create /tn "Ferosa Queue Worker" /sc onstart /ru SYSTEM /rl HIGHEST /f ^
  /tr "\"%APPDIR%\start-worker.bat\"" >nul
if errorlevel 1 (echo  [X] Could not register the queue worker. & pause & exit /b 1)
echo  [OK] Ferosa Queue Worker

echo  Registering the scheduler...
schtasks /create /tn "Ferosa Scheduler" /sc onstart /ru SYSTEM /rl HIGHEST /f ^
  /tr "cmd /c cd /d \"%APPDIR%\" ^&^& \"%PHP%\" artisan schedule:work" >nul
if errorlevel 1 (echo  [X] Could not register the scheduler. & pause & exit /b 1)
echo  [OK] Ferosa Scheduler

echo.
echo  Starting both now, so you do not have to reboot...
schtasks /run /tn "Ferosa Queue Worker" >nul
schtasks /run /tn "Ferosa Scheduler" >nul

echo.
echo  Current state
echo  -------------
schtasks /query /tn "Ferosa Queue Worker" /fo list | findstr /I "TaskName Status"
schtasks /query /tn "Ferosa Scheduler" /fo list | findstr /I "TaskName Status"

echo.
echo  Done. Both tasks start again on every boot.
echo.
echo  THE REAL CHECK IS TOMORROW MORNING:
echo    a file dated 02:00 in storage\app\backups
echo.
echo  Until one exists, the scheduler is not doing its job. Nothing here
echo  will warn you - that is exactly why this file exists.
echo.
pause
endlocal
