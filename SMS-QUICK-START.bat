@echo off
REM SMS Notification System - Quick Start Script
REM This helps you get started with the SMS notification system

echo ============================================
echo SMS NOTIFICATION SYSTEM - QUICK START
echo ============================================
echo.

:menu
echo.
echo What would you like to do?
echo.
echo 1. Test SMS configuration
echo 2. Run schedule reminder manually
echo 3. Set up automated task (Windows Task Scheduler)
echo 4. View setup guide
echo 5. Exit
echo.
set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" goto test_config
if "%choice%"=="2" goto run_reminder
if "%choice%"=="3" goto setup_task
if "%choice%"=="4" goto view_guide
if "%choice%"=="5" goto end
echo Invalid choice. Please try again.
goto menu

:test_config
echo.
echo ============================================
echo Testing SMS Configuration...
echo ============================================
echo.
php sms/test-sms.php
echo.
pause
goto menu

:run_reminder
echo.
echo ============================================
echo Running Schedule Reminder Manually...
echo ============================================
echo.
php sms/cron/schedule-reminder.php
echo.
pause
goto menu

:setup_task
echo.
echo ============================================
echo Setting up Windows Scheduled Task...
echo ============================================
echo.
echo This requires administrator privileges.
echo Please right-click this file and select "Run as administrator"
echo.
pause
cd sms\cron
call setup-windows-task.bat
cd ..\..
pause
goto menu

:view_guide
echo.
echo ============================================
echo Opening Setup Guide...
echo ============================================
echo.
start SMS-SETUP-GUIDE.md
echo.
echo Setup guide opened in your default text editor.
echo.
pause
goto menu

:end
echo.
echo ============================================
echo Thank you for using the SMS system!
echo ============================================
echo.
echo For help, see:
echo   - SMS-SETUP-GUIDE.md (detailed setup)
echo   - SMS-QUICK-REFERENCE.txt (quick reference)
echo   - sms/README.md (technical docs)
echo.
pause
