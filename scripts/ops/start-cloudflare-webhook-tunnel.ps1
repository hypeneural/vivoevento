[CmdletBinding()]
param(
    [string] $LocalTargetUrl,
    [string] $CloudflaredPath,
    [string] $ZApiInstanceKey,
    [string] $WhatsAppProvider = 'zapi',
    [string] $StateFilePath,
    [int] $ConnectTimeoutSeconds = 45,
    [int] $ReconnectDelaySeconds = 5,
    [switch] $NoReconnect,
    [switch] $DryRun
)

Set-StrictMode -Version 3.0
$ErrorActionPreference = 'Stop'

function Write-Info {
    param([string] $Message)

    Write-Host "[info] $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string] $Message)

    Write-Host "[ok] $Message" -ForegroundColor Green
}

function Write-WarnLine {
    param([string] $Message)

    Write-Host "[warn] $Message" -ForegroundColor Yellow
}

function Resolve-ProjectRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}

function Get-DotEnvValue {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,
        [Parameter(Mandatory = $true)]
        [string] $Key
    )

    if (-not (Test-Path $Path)) {
        return $null
    }

    foreach ($line in Get-Content -Path $Path) {
        if ($line -match "^\s*$([regex]::Escape($Key))=(.*)$") {
            $value = $Matches[1].Trim()

            if (
                ($value.StartsWith('"') -and $value.EndsWith('"')) -or
                ($value.StartsWith("'") -and $value.EndsWith("'"))
            ) {
                $value = $value.Substring(1, $value.Length - 2)
            }

            return $value
        }
    }

    return $null
}

function Resolve-ApiEnvPath {
    param([string] $ProjectRoot)

    $apiEnvPath = Join-Path $ProjectRoot 'apps/api/.env'

    if (Test-Path $apiEnvPath) {
        return $apiEnvPath
    }

    return (Join-Path $ProjectRoot 'apps/api/.env.example')
}

function Resolve-LocalTargetUrl {
    param(
        [string] $ProjectRoot,
        [string] $ExplicitUrl
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitUrl)) {
        return $ExplicitUrl
    }

    $apiEnvPath = Resolve-ApiEnvPath -ProjectRoot $ProjectRoot
    $appUrl = Get-DotEnvValue -Path $apiEnvPath -Key 'APP_URL'

    if (-not [string]::IsNullOrWhiteSpace($appUrl)) {
        return $appUrl
    }

    return 'http://localhost:8000'
}

function Get-UriInfo {
    param([string] $Url)

    $uri = [Uri] $Url
    $port = if ($uri.IsDefaultPort) {
        if ($uri.Scheme -eq 'https') { 443 } else { 80 }
    } else {
        $uri.Port
    }

    return [PSCustomObject] @{
        Url = $uri
        BaseUrl = $uri.GetLeftPart([System.UriPartial]::Authority)
        Host = $uri.DnsSafeHost
        Port = $port
        Scheme = $uri.Scheme
    }
}

function Test-Url {
    param(
        [string] $Url,
        [int] $TimeoutSeconds = 5
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -TimeoutSec $TimeoutSeconds

        return [PSCustomObject] @{
            Success = $true
            StatusCode = [int] $response.StatusCode
            Error = $null
        }
    } catch {
        return [PSCustomObject] @{
            Success = $false
            StatusCode = $null
            Error = $_.Exception.Message
        }
    }
}

function Get-HealthProbe {
    param([string] $BaseUrl)

    foreach ($path in @('/up', '/health/live', '/')) {
        $result = Test-Url -Url ($BaseUrl.TrimEnd('/') + $path)

        if ($result.Success) {
            return [PSCustomObject] @{
                Success = $true
                Path = $path
                StatusCode = $result.StatusCode
                Error = $null
            }
        }
    }

    return [PSCustomObject] @{
        Success = $false
        Path = '/up'
        StatusCode = $null
        Error = 'Nao foi possivel validar a API local em /up, /health/live ou /.'
    }
}

function Resolve-CloudflaredPath {
    param(
        [string] $ProjectRoot,
        [string] $ExplicitPath
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        if (-not (Test-Path $ExplicitPath)) {
            throw "cloudflared nao encontrado em: $ExplicitPath"
        }

        return (Resolve-Path $ExplicitPath).Path
    }

    $candidates = @(
        (Join-Path $ProjectRoot 'scripts/ops/bin/cloudflared.exe'),
        (Join-Path $PSScriptRoot 'bin/cloudflared.exe')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    $command = Get-Command cloudflared -ErrorAction SilentlyContinue

    if ($command) {
        return $command.Path
    }

    throw 'cloudflared nao encontrado. Baixe o binario oficial em scripts/ops/bin/cloudflared.exe ou instale com winget.'
}

function Assert-QuickTunnelSupported {
    $blockedFiles = @()

    foreach ($directory in @(
        (Join-Path $HOME '.cloudflared'),
        (Join-Path $HOME '.cloudflare-warp')
    )) {
        foreach ($fileName in @('config.yml', 'config.yaml')) {
            $candidate = Join-Path $directory $fileName

            if (Test-Path $candidate) {
                $blockedFiles += $candidate
            }
        }
    }

    if ($blockedFiles.Count -gt 0) {
        $paths = $blockedFiles -join ', '
        throw "Quick Tunnel nao suporta config.yml/config.yaml nessas pastas. Renomeie temporariamente: $paths"
    }
}

function Get-QuickTunnelUrl {
    param(
        [string[]] $LogPaths,
        [int] $TimeoutSeconds
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        foreach ($path in $LogPaths) {
            if (-not (Test-Path $path)) {
                continue
            }

            $matchInfo = Select-String -Path $path -Pattern 'https://[-a-z0-9]+\.trycloudflare\.com' -AllMatches -ErrorAction SilentlyContinue | Select-Object -Last 1

            if (-not $matchInfo) {
                continue
            }

            $values = @($matchInfo.Matches | ForEach-Object { $_.Value })

            if ($values.Count -gt 0) {
                return $values[-1]
            }
        }

        Start-Sleep -Seconds 1
    }

    return $null
}

function Build-StatePayload {
    param(
        [string] $PublicBaseUrl,
        [string] $HealthPath,
        [string] $LocalTargetUrlValue,
        [string] $ApiEnvPath,
        [string] $StatePath,
        [string] $ZApiInstanceKeyValue,
        [string] $WhatsAppProviderValue,
        [bool] $PagarmeBasicAuthConfigured,
        [string] $CloudflaredExePath,
        [string] $CloudflaredVersion,
        [int] $ChildProcessId,
        [string] $StdOutLogPath,
        [string] $StdErrLogPath
    )

    $zapiInstanceSegment = if ([string]::IsNullOrWhiteSpace($ZApiInstanceKeyValue)) {
        '{EXTERNAL_INSTANCE_ID}'
    } else {
        $ZApiInstanceKeyValue
    }

    $zapiBase = "$PublicBaseUrl/api/v1/webhooks/whatsapp/$WhatsAppProviderValue/$zapiInstanceSegment"
    $pagarmeUrl = "$PublicBaseUrl/api/v1/webhooks/billing/pagarme"

    return [ordered] @{
        generated_at = (Get-Date).ToString('o')
        transport = 'cloudflare-quick-tunnel'
        local_target_url = $LocalTargetUrlValue
        public_base_url = $PublicBaseUrl
        public_health_url = $PublicBaseUrl + $HealthPath
        state_file = $StatePath
        api_env_path = $ApiEnvPath
        process = [ordered] @{
            child_process_id = $ChildProcessId
            stdout_log_path = $StdOutLogPath
            stderr_log_path = $StdErrLogPath
        }
        cloudflared = [ordered] @{
            executable_path = $CloudflaredExePath
            version = $CloudflaredVersion
        }
        urls = [ordered] @{
            zapi_inbound = $zapiBase + '/inbound'
            zapi_status = $zapiBase + '/status'
            zapi_delivery = $zapiBase + '/delivery'
            pagarme = $pagarmeUrl
        }
        pagarme = [ordered] @{
            basic_auth_configured = $PagarmeBasicAuthConfigured
        }
        notes = @(
            'Quick Tunnel e para desenvolvimento e testes.',
            'A URL publica muda quando o cloudflared reinicia.',
            'Nao foi observada tela de cautela/interstitial nas validacoes locais deste projeto.'
        )
    }
}

function Write-StateFile {
    param(
        [string] $Path,
        [hashtable] $Payload
    )

    $directory = Split-Path -Parent $Path

    if (-not [string]::IsNullOrWhiteSpace($directory) -and -not (Test-Path $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $json = $Payload | ConvertTo-Json -Depth 8
    [System.IO.File]::WriteAllText($Path, $json, [System.Text.UTF8Encoding]::new($false))
}

function Show-Urls {
    param([hashtable] $StatePayload)

    Write-Host ''
    Write-Host 'Webhook URLs' -ForegroundColor White
    Write-Host "  Base:     $($StatePayload.public_base_url)"
    Write-Host "  Health:   $($StatePayload.public_health_url)"
    Write-Host "  Z-API in: $($StatePayload.urls.zapi_inbound)"
    Write-Host "  Z-API st: $($StatePayload.urls.zapi_status)"
    Write-Host "  Z-API dl: $($StatePayload.urls.zapi_delivery)"
    Write-Host "  Pagar.me: $($StatePayload.urls.pagarme)"
    Write-Host "  State:    $($StatePayload.state_file)"
    Write-Host ''
}

$projectRoot = Resolve-ProjectRoot
$apiEnvPath = Resolve-ApiEnvPath -ProjectRoot $projectRoot
$localTargetUrlValue = Resolve-LocalTargetUrl -ProjectRoot $projectRoot -ExplicitUrl $LocalTargetUrl
$uriInfo = Get-UriInfo -Url $localTargetUrlValue
$originBaseUrl = $uriInfo.BaseUrl
$healthProbe = Get-HealthProbe -BaseUrl $originBaseUrl
$cloudflaredExePath = Resolve-CloudflaredPath -ProjectRoot $projectRoot -ExplicitPath $CloudflaredPath
$cloudflaredVersion = (& $cloudflaredExePath --version | Select-Object -First 1)
$pagarmeBasicAuthConfigured = -not [string]::IsNullOrWhiteSpace((Get-DotEnvValue -Path $apiEnvPath -Key 'PAGARME_WEBHOOK_BASIC_AUTH_USER')) -and
    -not [string]::IsNullOrWhiteSpace((Get-DotEnvValue -Path $apiEnvPath -Key 'PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD'))

Assert-QuickTunnelSupported

if ([string]::IsNullOrWhiteSpace($StateFilePath)) {
    $StateFilePath = Join-Path $env:TEMP 'eventovivo-cloudflare-webhook.json'
}

$stdOutLogPath = Join-Path $env:TEMP 'eventovivo-cloudflared-quick-out.txt'
$stdErrLogPath = Join-Path $env:TEMP 'eventovivo-cloudflared-quick-err.txt'

Write-Info "Origem local: $originBaseUrl"
Write-Info "cloudflared: $cloudflaredExePath"
Write-Info $cloudflaredVersion

if ($healthProbe.Success) {
    Write-Ok "API local respondeu $($healthProbe.StatusCode) em $($healthProbe.Path)"
} else {
    Write-WarnLine $healthProbe.Error
}

if ($DryRun) {
    $dryRunState = Build-StatePayload `
        -PublicBaseUrl 'https://RANDOM.trycloudflare.com' `
        -HealthPath $healthProbe.Path `
        -LocalTargetUrlValue $originBaseUrl `
        -ApiEnvPath $apiEnvPath `
        -StatePath $StateFilePath `
        -ZApiInstanceKeyValue $ZApiInstanceKey `
        -WhatsAppProviderValue $WhatsAppProvider `
        -PagarmeBasicAuthConfigured $pagarmeBasicAuthConfigured `
        -CloudflaredExePath $cloudflaredExePath `
        -CloudflaredVersion $cloudflaredVersion `
        -ChildProcessId 0 `
        -StdOutLogPath $stdOutLogPath `
        -StdErrLogPath $stdErrLogPath

    Write-Info "Comando: `"$cloudflaredExePath`" tunnel --url $originBaseUrl --no-autoupdate"
    Show-Urls -StatePayload $dryRunState
    return
}

$attempt = 0
$childProcess = $null

try {
    do {
        $attempt++

        foreach ($path in @($stdOutLogPath, $stdErrLogPath)) {
            if (Test-Path $path) {
                Remove-Item -LiteralPath $path -Force
            }
        }

        Write-Info "Abrindo Cloudflare Quick Tunnel (tentativa $attempt)..."

        $childProcess = Start-Process `
            -FilePath $cloudflaredExePath `
            -ArgumentList @('tunnel', '--url', $originBaseUrl, '--no-autoupdate') `
            -RedirectStandardOutput $stdOutLogPath `
            -RedirectStandardError $stdErrLogPath `
            -PassThru `
            -WindowStyle Hidden

        $publicBaseUrl = Get-QuickTunnelUrl -LogPaths @($stdErrLogPath, $stdOutLogPath) -TimeoutSeconds $ConnectTimeoutSeconds

        if ([string]::IsNullOrWhiteSpace($publicBaseUrl)) {
            Write-WarnLine 'Nao foi possivel descobrir a URL publica do Cloudflare.'

            if (-not $childProcess.HasExited) {
                Stop-Process -Id $childProcess.Id -Force
            }

            if ($NoReconnect) {
                throw 'Falha ao obter a URL publica do Quick Tunnel.'
            }

            Write-Info "Nova tentativa em $ReconnectDelaySeconds segundos..."
            Start-Sleep -Seconds $ReconnectDelaySeconds
            continue
        }

        $statePayload = Build-StatePayload `
            -PublicBaseUrl $publicBaseUrl `
            -HealthPath $healthProbe.Path `
            -LocalTargetUrlValue $originBaseUrl `
            -ApiEnvPath $apiEnvPath `
            -StatePath $StateFilePath `
            -ZApiInstanceKeyValue $ZApiInstanceKey `
            -WhatsAppProviderValue $WhatsAppProvider `
            -PagarmeBasicAuthConfigured $pagarmeBasicAuthConfigured `
            -CloudflaredExePath $cloudflaredExePath `
            -CloudflaredVersion $cloudflaredVersion `
            -ChildProcessId $childProcess.Id `
            -StdOutLogPath $stdOutLogPath `
            -StdErrLogPath $stdErrLogPath

        Write-StateFile -Path $StateFilePath -Payload $statePayload

        Write-Ok "Quick Tunnel ativo em $publicBaseUrl"

        $publicProbe = Test-Url -Url ($publicBaseUrl.TrimEnd('/') + $healthProbe.Path) -TimeoutSeconds 20

        if ($publicProbe.Success) {
            Write-Ok "Health publico respondeu $($publicProbe.StatusCode) em $($healthProbe.Path)"
        } else {
            Write-WarnLine "Nao foi possivel validar o health publico: $($publicProbe.Error)"
        }

        Show-Urls -StatePayload $statePayload

        $childProcess.WaitForExit()
        Write-WarnLine "cloudflared finalizou com codigo $($childProcess.ExitCode)"
        $childProcess = $null

        if ($NoReconnect) {
            break
        }

        Write-Info "Reconectando em $ReconnectDelaySeconds segundos..."
        Start-Sleep -Seconds $ReconnectDelaySeconds
    } while (-not $NoReconnect)
} finally {
    if ($childProcess -and -not $childProcess.HasExited) {
        Stop-Process -Id $childProcess.Id -Force
    }
}
