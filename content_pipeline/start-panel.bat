@echo off
setlocal
rem Content feed pipeline - management panel launcher
rem Runs from the parent folder so paths inside config.yaml resolve.
cd /d "%~dp0.."

set "PY=python"
where python >nul 2>nul || set "PY=py"
%PY% --version >nul 2>nul
if errorlevel 1 (
  echo Python was not found on this system.
  echo Install it from python.org and tick "Add python.exe to PATH".
  echo.
  pause
  exit /b 1
)

%PY% -c "import typer, yaml" >nul 2>nul
if errorlevel 1 (
  echo Installing required packages, this runs only once...
  %PY% -m pip install --quiet --disable-pip-version-check typer pyyaml
  if errorlevel 1 (
    echo Package installation failed. See the error above.
    echo.
    pause
    exit /b 1
  )
)

if not exist "config.yaml" (
  echo Creating config.yaml from the example...
  copy /y "content_pipeline\config.example.yaml" "config.yaml" >nul
)

echo Starting the panel, your browser will open automatically.
echo Press Ctrl+C in this window to stop it.
echo.
%PY% -m content_pipeline.run panel -c config.yaml

echo.
pause
