@echo off
setlocal

set "PHP_EXE=%PHP84_EXE%"
if not "%PHP_EXE%"=="" goto run

where php >nul 2>nul
if errorlevel 1 (
  echo [Catalog] PHP executable was not found. 1>&2
  echo Set PHP84_EXE to your PHP 8.4 binary or add PHP 8.4 to PATH. 1>&2
  exit /b 1
)

for /f "delims=" %%I in ('where php') do (
  set "PHP_EXE=%%I"
  goto run
)

:run
"%PHP_EXE%" %*
exit /b %ERRORLEVEL%
