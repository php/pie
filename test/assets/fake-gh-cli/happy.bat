@echo off
set "found=0"

rem Look for --repo or --help params;
echo %* | findstr /C:"--repo=php/pie" >nul && set "found=1"
echo %* | findstr /C:"--help" >nul && set "found=1"

if "%found%"=="0" (
    echo Error: --repo=php/pie or --help parameters missing
    exit /b 1
)

echo Pretending to be gh cli - happy path
exit /b 0
