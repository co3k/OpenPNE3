@echo off

cd "../../"

"%ProgramFiles(x86)%\php\v5.3\php.exe" bin/azure/serviceConfigurationCacheClear.php
IF ERRORLEVEL 1 (
    "%RoleRoot%\bin\azure\Install.cmd"
)
