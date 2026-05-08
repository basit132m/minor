@echo off
echo ============================================
echo   XMR Miner - Build Script
echo ============================================
echo.

REM Install dependencies
echo [1/3] Installing Python dependencies...
pip install -r requirements.txt
if %errorlevel% neq 0 (
    echo ERROR: pip install failed.
    pause
    exit /b 1
)

echo.
echo [2/3] Building XMR Miner .exe with PyInstaller...
pyinstaller ^
    --onefile ^
    --windowed ^
    --name "XMR_Miner" ^
    --icon=icon.ico ^
    --add-data "config.json;." ^
    miner.py

if %errorlevel% neq 0 (
    REM Retry without icon if icon.ico is missing
    echo Retrying without custom icon...
    pyinstaller ^
        --onefile ^
        --windowed ^
        --name "XMR_Miner" ^
        --add-data "config.json;." ^
        miner.py
)

if %errorlevel% neq 0 (
    echo ERROR: PyInstaller build failed.
    pause
    exit /b 1
)

echo.
echo [3/3] Done!
echo.
echo Your executable is at: dist\XMR_Miner.exe
echo.
echo NOTE: On first launch, XMR_Miner.exe will automatically
echo       download XMRig into the same folder.
echo.
pause
