@echo off
echo Starting MySQL from XAMPP...
cd /d C:\xampp
call mysql_start.bat
if %errorlevel% neq 0 (
    echo.
    echo MySQL failed to start. Trying alternative method...
    cd mysql\bin
    mysqld.exe --install
    net start mysql
)
echo.
echo Checking MySQL status...
timeout /t 2 /nobreak >nul
netstat -ano | findstr :3307
if %errorlevel% equ 0 (
    echo.
    echo ✅ MySQL is now running on port 3307!
) else (
    echo.
    echo ❌ MySQL is still not running. Please start it from XAMPP Control Panel.
)
pause

