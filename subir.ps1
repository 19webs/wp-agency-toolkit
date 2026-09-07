# Set console output encoding to UTF-8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# ==============================================================================
# CONFIGURACIÓN DE PUBLIT.IO API Y REPOSITORIOS DE PLUGINS
# ==============================================================================
$apiKey    = "HwNBmrHZuzjXNVxmvNNZ"
$apiSecret = "ZaGrALDeuGwqWxDZVvLsAG2nPDSghPzm"

$plugins = @{
    "1" = @{
        Name     = "WP Agency Toolkit"
        Folder   = "C:\Users\Usuario\Desktop\Wp Agency toolkit"
        PhpFile  = "wp-agency-toolkit.php"
        ZipName  = "wp-agency-toolkit.zip"
        PublicId = "wp-agency-toolkit"
        ConstName = "WPAT_VERSION"
    }
    "2" = @{
        Name     = "PrestaWoo"
        Folder   = "C:\Users\Usuario\Desktop\PrestaWoo"
        PhpFile  = "prestashowoo-migration.php"
        ZipName  = "prestawoo.zip"
        PublicId = "prestawoo"
        ConstName = "PRESTAWOO_VERSION"
    }
    "3" = @{
        Name     = "WP Docu Signer Pro"
        Folder   = "C:\Users\Usuario\Desktop\wp docu signer pro"
        PhpFile  = "wp-document-signer.php"
        ZipName  = "wp-docu-signer-pro.zip"
        PublicId = "wp-docu-signer-pro"
        ConstName = "WP_DOC_SIGNER_VERSION"
    }
    "4" = @{
        Name     = "WP Autocontent"
        Folder   = "C:\Users\Usuario\Desktop\Wp Autocontent\wp-autocontent"
        PhpFile  = "wp-autocontent.php"
        ZipName  = "wp-autocontent.zip"
        PublicId = "wp-autocontent"
        ConstName = "WP_AUTOCONTENT_VERSION"
    }
}

# ==============================================================================
# FUNCIONES AUXILIARES
# ==============================================================================

function Get-PublitioAuthQuery {
    param([string]$key, [string]$secret)
    $timestamp = [int]([DateTimeOffset]::Now.ToUnixTimeSeconds())
    $nonce = Get-Random -Minimum 10000000 -Maximum 99999999
    $stringToSign = "$timestamp$nonce$secret"
    $sha1 = [System.Security.Cryptography.SHA1]::Create()
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($stringToSign)
    $sig = [System.BitConverter]::ToString($sha1.ComputeHash($bytes)).Replace("-","").ToLower()
    return "api_key=$key&api_timestamp=$timestamp&api_nonce=$nonce&api_signature=$sig"
}

function Get-PluginCurrentVersion {
    param([string]$filePath)
    if (Test-Path $filePath) {
        $content = Get-Content $filePath -Encoding UTF8 -Raw
        if ($content -match 'Version:\s+([0-9.]+)') {
            return $Matches[1]
        }
    }
    return "1.0.0"
}

function Get-SuggestedNextVersion {
    param([string]$version)
    $parts = $version.Split('.')
    if ($parts.Count -ge 3) {
        $parts[2] = [int]$parts[2] + 1
        return $parts -join '.'
    }
    return "$version.1"
}

function Upload-ToPublitio {
    param(
        [string]$zipPath,
        [string]$zipName,
        [string]$publicId
    )
    
    Write-Host "[INFO] Conectando con Publit.io API..." -ForegroundColor Yellow
    $authQuery = Get-PublitioAuthQuery -key $apiKey -secret $apiSecret
    
    # 1. Buscar si ya existe el archivo
    $listUrl = "https://api.publit.io/v1/files/list?$authQuery"
    $existingFileId = $null
    $existingFileUrl = $null
    
    try {
        $listRes = Invoke-RestMethod -Uri $listUrl -Method Get
        if ($listRes.files) {
            foreach ($f in $listRes.files) {
                if ($f.title -eq $zipName -or $f.public_id -eq $publicId -or $f.public_id -eq $zipName) {
                    $existingFileId = $f.id
                    $existingFileUrl = $f.url_download
                    break
                }
            }
        }
    } catch {
        Write-Host "[WARN] No se pudo obtener la lista de Publit.io. Se intentará crear de cero." -ForegroundColor Yellow
    }

    $authQueryNew = Get-PublitioAuthQuery -key $apiKey -secret $apiSecret
    
    if ($existingFileId) {
        Write-Host "[INFO] Reemplazando archivo existente en Publit.io (ID: $existingFileId)..." -ForegroundColor Yellow
        $uploadUrl = "https://api.publit.io/v1/files/$existingFileId/replace?$authQueryNew"
        $curlOutput = & curl.exe -s -X POST "$uploadUrl" -F "file=@$zipPath"
    } else {
        Write-Host "[INFO] Subiendo nuevo archivo a Publit.io..." -ForegroundColor Yellow
        $uploadUrl = "https://api.publit.io/v1/files/create?$authQueryNew"
        $curlOutput = & curl.exe -s -X POST "$uploadUrl" -F "file=@$zipPath" -F "public_id=$publicId" -F "title=$zipName"
    }

    try {
        $json = $curlOutput | ConvertFrom-Json
        if ($json.success -or $json.id) {
            $dlUrl = if ($json.url_download) { $json.url_download } else { "https://media.publit.io/file/$publicId.zip" }
            Write-Host "[OK] Subido correctamente a Publit.io!" -ForegroundColor Green
            Write-Host "[URL DESCARGA PÚBLICA]: " -NoNewline
            Write-Host "$dlUrl" -ForegroundColor Cyan
            return $dlUrl
        } else {
            Write-Host "[ERROR] Publit.io error: $($json.message)" -ForegroundColor Red
        }
    } catch {
        Write-Host "[INFO] Respuesta de Publit.io recibida." -ForegroundColor Green
        Write-Host "[URL DESCARGA PÚBLICA]: https://media.publit.io/file/$publicId.zip" -ForegroundColor Cyan
    }
}

function Publish-SinglePlugin {
    param([hashtable]$pConfig)
    
    $name     = $pConfig.Name
    $folder   = $pConfig.Folder
    $phpFile  = Join-Path $folder $pConfig.PhpFile
    $zipName  = $pConfig.ZipName
    $publicId = $pConfig.PublicId
    $zipPath  = Join-Path $folder $zipName

    Write-Host ""
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "  Procesando Plugin: $name" -ForegroundColor Cyan
    Write-Host "==========================================================" -ForegroundColor Cyan

    if (-not (Test-Path $folder)) {
        Write-Host "[ERROR] La carpeta $folder no existe." -ForegroundColor Red
        return
    }

    # Ir a la carpeta del plugin
    Push-Location $folder

    # 1. Versiones
    $currentVer = Get-PluginCurrentVersion -filePath $phpFile
    $suggestedVer = Get-SuggestedNextVersion -version $currentVer

    Write-Host "[INFO] Versión actual: " -NoNewline
    Write-Host "$currentVer" -ForegroundColor Yellow
    Write-Host "[INFO] Sugerida:       " -NoNewline
    Write-Host "$suggestedVer" -ForegroundColor Green
    
    $inputVer = Read-Host "Nueva versión para $name [ENTER = $suggestedVer]"
    if ([string]::IsNullOrWhiteSpace($inputVer)) {
        $version = $suggestedVer
    } else {
        $version = $inputVer.Trim()
    }

    # 2. Modificar versión en PHP (UTF-8 sin BOM)
    Write-Host "[INFO] Actualizando versión a v$version en $($pConfig.PhpFile)..." -ForegroundColor Yellow
    $raw = Get-Content $phpFile -Encoding UTF8 -Raw
    $raw = $raw -replace 'Version:\s+[0-9.]+', "Version:     $version"
    if ($pConfig.ConstName) {
        $const = $pConfig.ConstName
        $raw = $raw -replace "define\(\s*'$const'\s*,\s*'[0-9.]+'\s*\)", "define( '$const', '$version' )"
    }
    [System.IO.File]::WriteAllText((Get-Item $phpFile).FullName, $raw, (New-Object System.Text.UTF8Encoding $false))
    Write-Host "[OK] Versión v$version guardada." -ForegroundColor Green

    # 3. Crear ZIP temporal sin archivos excluidos
    Write-Host "[INFO] Generando paquete comprimido $zipName..." -ForegroundColor Yellow
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    $excludeList = @(".git", ".vscode", "subir.bat", "subir.ps1", $zipName)
    $filesToZip = Get-ChildItem -Path $folder | Where-Object { $excludeList -notcontains $_.Name }
    Compress-Archive -Path $filesToZip.FullName -DestinationPath $zipPath -Force
    Write-Host "[OK] Paquete $zipName creado." -ForegroundColor Green

    # 4. Git Push & Tags
    Write-Host "[INFO] Actualizando repositorio GitHub..." -ForegroundColor Yellow
    if (-not (Test-Path ".git")) {
        git init
        git branch -M main
    }
    git add .
    git commit -m "Versión v$version"
    git push origin main

    git tag -d "v$version" 2>$null
    git push origin ":refs/tags/v$version" 2>$null
    git tag "v$version"
    git push origin "v$version"
    Write-Host "[OK] Código y etiqueta v$version subidos a GitHub." -ForegroundColor Green

    # 5. Subida a Publit.io API
    Upload-ToPublitio -zipPath $zipPath -zipName $zipName -publicId $publicId

    # Limpiar ZIP local
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    Pop-Location
    Write-Host "[OK] ¡Publicación de $name completada!" -ForegroundColor Green
}

# ==============================================================================
# MENÚ DE EJECUCIÓN PRINCIPAL
# ==============================================================================

# Detectar si se está ejecutando desde dentro de una carpeta de plugin específica
$currentDir = (Get-Location).Path
$matchedKey = $null

foreach ($key in $plugins.Keys) {
    if ($plugins[$key].Folder.ToLower() -eq $currentDir.ToLower()) {
        $matchedKey = $key
        break
    }
}

if ($matchedKey) {
    # Ejecución directa para el plugin actual
    Publish-SinglePlugin -pConfig $plugins[$matchedKey]
} else {
    # Menú maestro interactivo
    Clear-Host
    Write-Host ""
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "  PUBLICADOR GLOBAL DE PLUGINS (GitHub + Publit.io API)" -ForegroundColor Cyan
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host ""
    
    foreach ($k in ($plugins.Keys | Sort-Object)) {
        $p = $plugins[$k]
        $ver = Get-PluginCurrentVersion -filePath (Join-Path $p.Folder $p.PhpFile)
        Write-Host "  [$k] $($p.Name) (v$ver)"
    }
    Write-Host "  [A] Publicar TODOS los Plugins a la vez" -ForegroundColor Yellow
    Write-Host ""
    
    $choice = Read-Host "Selecciona una opción [1-4 o A]"
    
    if ($choice -eq "A" -or $choice -eq "a") {
        foreach ($k in ($plugins.Keys | Sort-Object)) {
            Publish-SinglePlugin -pConfig $plugins[$k]
        }
    } elseif ($plugins.ContainsKey($choice)) {
        Publish-SinglePlugin -pConfig $plugins[$choice]
    } else {
        Write-Host "[ERROR] Opción no válida." -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Proceso finalizado con éxito." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

Read-Host "Presiona ENTER para salir"
