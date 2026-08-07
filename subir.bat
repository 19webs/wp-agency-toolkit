@echo off
echo.
echo ==============================================
echo   Asistente de Subida y Actualizacion a GitHub
echo ==============================================
echo.

:: 1. Verificar si la carpeta local de Git existe. Si no, inicializarla.
if not exist .git (
    echo [INFO] El repositorio local no estaba inicializado. Configurando Git...
    git init
    git branch -M main
    git remote add origin https://github.com/19webs/wp-agency-toolkit.git
    echo [OK] Repositorio Git local conectado a GitHub.
    echo.
)

:: 2. Preguntar al usuario si desea incrementar la version y crear un tag
set /p version="Introduce la nueva version (ej: 3.2.0) o pulsa ENTER para subir cambios simples: "

if "%version%"=="" (
    echo.
    echo [INFO] Subiendo cambios normales sin incremento de version...
    set commit_message=Auto-actualizacion
    goto git_push
)

echo.
echo [INFO] Actualizando version a v%version% en wp-agency-toolkit.php...
:: Modificar la cabecera "Version:"
powershell -Command "(GC wp-agency-toolkit.php) -replace 'Version:\s+[0-9.]+', 'Version:     %version%' | Out-File -encoding utf8 wp-agency-toolkit.php"
:: Modificar la constante WPAT_VERSION
powershell -Command "(GC wp-agency-toolkit.php) -replace 'define\(\s*''WPAT_VERSION''\s*,\s*''[0-9.]+''\s*\)', 'define( ''WPAT_VERSION'', ''%version%'' )' | Out-File -encoding utf8 wp-agency-toolkit.php"
echo [OK] Archivo wp-agency-toolkit.php modificado.

set commit_message=Version v%version%

:git_push
:: 3. Añadir y hacer commit
git add .
git commit -m "%commit_message%"

:: 4. Subir archivos al repositorio
echo.
echo [INFO] Subiendo archivos a GitHub...
git push origin main

:: 5. Si se especifico version, crear y subir el tag correspondiente
if not "%version%"=="" (
    echo.
    echo [INFO] Creando y subiendo etiqueta de version v%version%...
    :: Eliminar tag local y remoto previo si existiera por error para evitar colisiones
    git tag -d v%version% >nul 2>&1
    git push origin :refs/tags/v%version% >nul 2>&1
    
    :: Crear y subir nueva etiqueta
    git tag v%version%
    git push origin v%version%
    echo [OK] Etiqueta v%version% subida correctamente a GitHub.
)

echo.
echo ==============================================
echo   Proceso completado con exito.
echo ==============================================
echo.
pause
