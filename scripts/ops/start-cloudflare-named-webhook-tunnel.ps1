[CmdletBinding()]
param(
    [string] $TunnelName = 'eventovivo-local-webhooks',
    [string] $TunnelHostname = 'webhooks-local.eventovivo.com.br',
    [string] $LocalTargetUrl,
    [string] $CloudflaredPath,
    [string] $ZApiInstanceId,
    [string] $ZApiToken,
    [string] $ZApiClientToken,
    [string] $StateFilePath,
    [int] $ConnectTimeoutSeconds = 45,
    [int] $ReconnectDelaySeconds = 5,
    [switch] $NoReconnect,
    [switch] $UpdateZApiWebhook,
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
            if ((($value.StartsWith('"')) -and $value.EndsWith('"')) -or (($value.StartsWith("'")) -and $value.EndsWith("'"))) {
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
    if (Test-Path $apiEnvPath) { return $apiEnvPath }
    return (Join-Path $ProjectRoot 'apps/api/.env.example')
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

    foreach ($candidate in @(
        (Join-Path $ProjectRoot 'scripts/ops/bin/cloudflared.exe'),
        (Join-Path $PSScriptRoot 'bin/cloudflared.exe')
    )) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    $command = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($command) { return $command.Path }

    throw 'cloudflared nao encontrado. Baixe o binario oficial em scripts/ops/bin/cloudflared.exe ou instale com winget.'
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
    if (-not [string]::IsNullOrWhiteSpace($appUrl)) { return $appUrl }
    return 'http://localhost:8000'
}

function Get-UriInfo {
    param([string] $Url)
    $uri = [Uri] $Url
    return [PSCustomObject] @{ Url = $uri; BaseUrl = $uri.GetLeftPart([System.UriPartial]::Authority) }
}

function Resolve-StateFilePath {
    param(
        [string] $ExplicitPath,
        [string] $TunnelNameValue
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        return $ExplicitPath
    }

    return (Join-Path (Join-Path $HOME '.cloudflared') "$TunnelNameValue.state.json")
}

function Test-Url {
    param(
        [string] $Url,
        [int] $TimeoutSeconds = 5
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -TimeoutSec $TimeoutSeconds
        return [PSCustomObject] @{ Success = $true; StatusCode = [int] $response.StatusCode; Error = $null }
    } catch {
        return [PSCustomObject] @{ Success = $false; StatusCode = $null; Error = $_.Exception.Message }
    }
}

function Get-HealthProbe {
    param([string] $BaseUrl)

    foreach ($path in @('/up', '/health/live', '/')) {
        $result = Test-Url -Url ($BaseUrl.TrimEnd('/') + $path)
        if ($result.Success) {
            return [PSCustomObject] @{ Success = $true; Path = $path; StatusCode = $result.StatusCode }
        }
    }

    return [PSCustomObject] @{ Success = $false; Path = '/up'; StatusCode = $null }
}

function Read-StatePayload {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        throw "Arquivo de estado nao encontrado em $Path. Rode primeiro setup-cloudflare-named-webhook-tunnel.ps1."
    }

    return (Get-Content $Path -Raw | ConvertFrom-Json)
}

function Build-StatePayload {
    param(
        [pscustomobject] $BaseState,
        [string] $LocalTargetUrlValue,
        [string] $HealthPath,
        [string] $CloudflaredExePath,
        [string] $CloudflaredVersion,
        [int] $ChildProcessId,
        [string] $StdOutLogPath,
        [string] $StdErrLogPath
    )

    return [ordered] @{
        generated_at = (Get-Date).ToString('o')
        transport = $BaseState.transport
        zone = $BaseState.zone
        tunnel_name = $BaseState.tunnel_name
        tunnel_id = $BaseState.tunnel_id
        hostname = $BaseState.hostname
        local_target_url = $LocalTargetUrlValue
        public_base_url = $BaseState.public_base_url
        public_health_url = $BaseState.public_base_url + $HealthPath
        api_env_path = $BaseState.api_env_path
        state_file = $BaseState.state_file
        cloudflared = [ordered] @{
            executable_path = $CloudflaredExePath
            version = $CloudflaredVersion
        }
        process = [ordered] @{
            child_process_id = $ChildProcessId
            stdout_log_path = $StdOutLogPath
            stderr_log_path = $StdErrLogPath
        }
        urls = [ordered] @{
            zapi_inbound = $BaseState.urls.zapi_inbound
            zapi_status = $BaseState.urls.zapi_status
            zapi_delivery = $BaseState.urls.zapi_delivery
            pagarme = $BaseState.urls.pagarme
        }
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

function Update-ZApiWebhook {
    param(
        [string] $WebhookUrl,
        [string] $InstanceId,
        [string] $Token,
        [string] $ClientToken
    )

    if ([string]::IsNullOrWhiteSpace($InstanceId) -or [string]::IsNullOrWhiteSpace($Token) -or [string]::IsNullOrWhiteSpace($ClientToken)) {
        throw 'Credenciais da Z-API ausentes para atualizar o webhook.'
    }

    $headers = @{ 'Client-Token' = $ClientToken; 'Content-Type' = 'application/json' }
    $payloadWebhook = @{ value = $WebhookUrl } | ConvertTo-Json -Compress
    $payloadNotify = @{ notifySentByMe = $true } | ConvertTo-Json -Compress

    $webhookResponse = Invoke-RestMethod -Method Put -Uri "https://api.z-api.io/instances/$InstanceId/token/$Token/update-webhook-received-delivery" -Headers $headers -Body $payloadWebhook
    $notifyResponse = Invoke-RestMethod -Method Put -Uri "https://api.z-api.io/instances/$InstanceId/token/$Token/update-notify-sent-by-me" -Headers $headers -Body $payloadNotify

    return [PSCustomObject] @{ Webhook = $webhookResponse; NotifySentByMe = $notifyResponse }
}

function Show-Urls {
    param([hashtable] $StatePayload)

    Write-Host ''
    Write-Host 'Named Tunnel URLs' -ForegroundColor White
    Write-Host "  Hostname: $($StatePayload.hostname)"
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
$cloudflaredExePath = Resolve-CloudflaredPath -ProjectRoot $projectRoot -ExplicitPath $CloudflaredPath
$cloudflaredVersion = (& $cloudflaredExePath --version | Select-Object -First 1)
$localTargetUrlValue = Resolve-LocalTargetUrl -ProjectRoot $projectRoot -ExplicitUrl $LocalTargetUrl
$originBaseUrl = (Get-UriInfo -Url $localTargetUrlValue).BaseUrl
$healthProbe = Get-HealthProbe -BaseUrl $originBaseUrl
$statePath = Resolve-StateFilePath -ExplicitPath $StateFilePath -TunnelNameValue $TunnelName
$baseState = Read-StatePayload -Path $statePath

if ([string]::IsNullOrWhiteSpace($ZApiInstanceId)) { $ZApiInstanceId = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_INSTANCE_ID' }
if ([string]::IsNullOrWhiteSpace($ZApiToken)) { $ZApiToken = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_TOKEN' }
if ([string]::IsNullOrWhiteSpace($ZApiClientToken)) { $ZApiClientToken = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_CLIENT_TOKEN' }

$stdOutLogPath = Join-Path $env:TEMP 'eventovivo-cloudflared-named-out.txt'
$stdErrLogPath = Join-Path $env:TEMP 'eventovivo-cloudflared-named-err.txt'

Write-Info "cloudflared: $cloudflaredExePath"
Write-Info $cloudflaredVersion
Write-Info "Tunnel name: $($baseState.tunnel_name)"
Write-Info "Hostname fixo: $($baseState.hostname)"
Write-Info "Origem local: $originBaseUrl"

if ($healthProbe.Success) {
    Write-Ok "API local respondeu $($healthProbe.StatusCode) em $($healthProbe.Path)"
} else {
    Write-WarnLine 'A API local nao respondeu em /up, /health/live ou /.'
}

$previewState = Build-StatePayload -BaseState $baseState -LocalTargetUrlValue $originBaseUrl -HealthPath $healthProbe.Path -CloudflaredExePath $cloudflaredExePath -CloudflaredVersion $cloudflaredVersion -ChildProcessId 0 -StdOutLogPath $stdOutLogPath -StdErrLogPath $stdErrLogPath

if ($DryRun) {
    Show-Urls -StatePayload $previewState
    Write-Info "Comando: `"$cloudflaredExePath`" tunnel --no-autoupdate run --url $originBaseUrl $($baseState.tunnel_name)"
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

        Write-Info "Abrindo named tunnel (tentativa $attempt)..."

        $childProcess = Start-Process -FilePath $cloudflaredExePath -ArgumentList @('tunnel', '--logfile', $stdErrLogPath, '--no-autoupdate', 'run', '--url', $originBaseUrl, $baseState.tunnel_name) -PassThru -WindowStyle Hidden

        $statePayload = Build-StatePayload -BaseState $baseState -LocalTargetUrlValue $originBaseUrl -HealthPath $healthProbe.Path -CloudflaredExePath $cloudflaredExePath -CloudflaredVersion $cloudflaredVersion -ChildProcessId $childProcess.Id -StdOutLogPath $stdOutLogPath -StdErrLogPath $stdErrLogPath
        Write-StateFile -Path $statePath -Payload $statePayload

        $deadline = (Get-Date).AddSeconds($ConnectTimeoutSeconds)
        $publicProbe = $null

        while ((Get-Date) -lt $deadline) {
            $publicProbe = Test-Url -Url ($statePayload.public_health_url) -TimeoutSeconds 10
            if ($publicProbe.Success) { break }
            Start-Sleep -Seconds 2
        }

        if ($publicProbe -and $publicProbe.Success) {
            Write-Ok "Health publico respondeu $($publicProbe.StatusCode) em $($healthProbe.Path)"
        } else {
            Write-WarnLine "Nao foi possivel validar o health publico em $($statePayload.public_health_url)."
        }

        Show-Urls -StatePayload $statePayload

        if ($UpdateZApiWebhook) {
            $updateResult = Update-ZApiWebhook -WebhookUrl $statePayload.urls.zapi_inbound -InstanceId $ZApiInstanceId -Token $ZApiToken -ClientToken $ZApiClientToken
            Write-Ok "Z-API webhook atualizado: $($updateResult.Webhook | ConvertTo-Json -Compress)"
            Write-Ok "Z-API notifySentByMe: $($updateResult.NotifySentByMe | ConvertTo-Json -Compress)"
        }

        $childProcess.WaitForExit()
        Write-WarnLine "cloudflared finalizou com codigo $($childProcess.ExitCode)"
        $childProcess = $null

        if ($NoReconnect) { break }

        Write-Info "Reconectando em $ReconnectDelaySeconds segundos..."
        Start-Sleep -Seconds $ReconnectDelaySeconds
    } while (-not $NoReconnect)
} finally {
    if ($childProcess -and -not $childProcess.HasExited) {
        Stop-Process -Id $childProcess.Id -Force
    }
}
