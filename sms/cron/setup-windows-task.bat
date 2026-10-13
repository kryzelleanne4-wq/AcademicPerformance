@echo off
REM Windows Task Scheduler Setup Script for SMS Schedule Reminders
REM This creates a scheduled task that runs daily at 6:00 PM

echo ============================================
echo SMS Schedule Reminder - Windows Setup
echo ============================================
echo.

REM Get PHP path
set PHP_PATH=
where php >nul 2>nul
if %errorlevel% equ 0 (
    for /f "delims=" %%i in ('where php') do set PHP_PATH=%%i
    echo Found PHP at: !PHP_PATH!
) else (
    echo PHP not found in PATH. Please enter the full path to php.exe
    echo Example: C:\xampp\php\php.exe
    set /p PHP_PATH="Enter PHP path: "
)

REM Get current directory
set SCRIPT_PATH=%~dp0schedule-reminder.php

echo.
echo Configuration:
echo   PHP: %PHP_PATH%
echo   Script: %SCRIPT_PATH%
echo   Schedule: Daily at 6:00 PM
echo.

REM Create the scheduled task
schtasks /create /tn "SMS Schedule Reminder" /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /sc daily /st 18:00 /f

if %errorlevel% equ 0 (
    echo.
    echo ============================================
    echo SUCCESS! Scheduled task created.
    echo ============================================
    echo.
    echo The task will run daily at 6:00 PM
    echo.
    echo To manage this task:
    echo   - Open Task Scheduler (search in Start menu^)
    echo   - Find "SMS Schedule Reminder" in the task list
    echo.
    echo To test the task now:
    echo   schtasks /run /tn "SMS Schedule Reminder"
    echo.
    echo Or run manually:
    echo   php "%SCRIPT_PATH%"
    echo.
) else (
    echo.
    echo ============================================
    echo ERROR: Failed to create scheduled task
    echo ============================================
    echo.
    echo This script requires administrator privileges.
    echo Please right-click and select "Run as administrator"
    echo.
)

pause
