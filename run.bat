@echo off
set "PHP=C:\Users\SIVA\tools\php\php.exe"
set "PHP_CLI_SERVER_WORKERS=4"
if not exist "%~dp0logs" mkdir "%~dp0logs"
echo Serving http://localhost:8080
"%PHP%" -S localhost:8080 -t "%~dp0public" "%~dp0public\index.php" 2>&1 | powershell -NoProfile -Command "$input | Tee-Object -FilePath '%~dp0logs\output.log'"
pause
