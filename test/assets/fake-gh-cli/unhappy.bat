@echo off
if "%*" == "" goto main
echo %* | findstr /C:"--help" >nul
if %errorlevel% == 0 exit /b 0

:main
echo "Pretending to be gh cli - unhappy path"
exit /b 1
