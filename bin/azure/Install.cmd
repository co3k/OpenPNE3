@echo off

icacls %RoleRoot%\approot /grant "Everyone":F /T
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /-"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='RoleDeploymentID']" /commit:apphost
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /+"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='RoleDeploymentID',value='%RoleDeploymentID%']" /commit:apphost
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /-"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='PATH']" /commit:apphost
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /+"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='PATH',value='%PATH%;%RoleRoot%\base\x86']" /commit:apphost
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /-"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='AZURE_ROLE_ROOT']" /commit:apphost
%WINDIR%\system32\inetsrv\appcmd.exe set config -section:system.webServer/fastCgi /+"[fullPath='%RoleRoot%\bin\azure\php\php-cgi.exe'].environmentVariables.[name='AZURE_ROLE_ROOT',value='%RoleRoot%']" /commit:apphost

"%RoleRoot%\bin\azure\php\php.exe" -m >> log.txt

ECHO "Starting OpenPNE Installation" >> log.txt

copy "databases.yml" "../../config"
copy "OpenPNE.yml" "../../config"

cd "../../"

"%RoleRoot%\bin\azure\php\php.exe" symfony opPlugin:sync >>bin/azure/log.txt 2>>bin/azure/err.txt

"%RoleRoot%\bin\azure\php\php.exe" bin/azure/checkDBExists.php
IF ERRORLEVEL 1 (
    ECHO "Starting Database Initialization" >> bin/azure/log.txt
    "%RoleRoot%\bin\azure\php\php.exe" symfony doctrine:build --all --and-load --no-confirmation >>bin/azure/log.txt 2>>bin/azure/err.txt
) ELSE (
    ECHO "Rebuilding Model Classes" >> bin/azure/log.txt
    "%RoleRoot%\bin\azure\php\php.exe" symfony doctrine:build --all-classes --no-confirmation >>bin/azure/log.txt 2>>bin/azure/err.txt
)

"%RoleRoot%\bin\azure\php\php.exe" bin/azure/quickFixForeignKey.php

"%RoleRoot%\bin\azure\php\php.exe" symfony cc >>bin/azure/log.txt 2>>bin/azure/err.txt

ECHO "Completed OpenPNE Installation" >> bin/azure/log.txt
