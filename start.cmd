@echo off
REM AFK Builder - wrapper para start.ps1 (clique-duplo no Explorer)
REM Repassa argumentos para o PowerShell. Uso:
REM   start.cmd
REM   start.cmd -Migrate
REM   start.cmd -SkipChecks

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0start.ps1" %*
pause
