[CmdletBinding()]
param(
    [string] $TunnelName = 'eventovivo-local-webhooks',
    [string] $TunnelHostname = 'webhooks-local.eventovivo.com.br',
    [string] $CloudflareZone = 'eventovivo.com.br',
    [string] $LocalTargetUrl,
    [string] $CloudflaredPath,
    [string] $ZApiInstanceKey,
    [string] $ZApiInstanceId,
    [string] $ZApiToken,
    [string] $ZApiClientToken,
    [string] $WhatsAppProvider = 'zapi',
    [string] $StateFilePath,
    [switch] $UpdateZApiWebhook,
    [switch] $LaunchLogin,
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

    $cloudflaredHome = Join-Path $HOME '.cloudflared'
    if (-not (Test-Path $cloudflaredHome)) {
        New-Item -ItemType Directory -Path $cloudflaredHome -Force | Out-Null
    }

    return (Join-Path $cloudflaredHome "$TunnelNameValue.state.json")
}

function Resolve-OriginCertPath {
    return (Join-Path $HOME '.cloudflared\cert.pem')
}

function Assert-OriginCert {
    param(
        [string] $OriginCertPath,
        [string] $CloudflaredExePath,
        [string] $CloudflareZoneValue,
        [bool] $LaunchLoginFlow
    )

    if (Test-Path $OriginCertPath) {
        return
    }

    if ($LaunchLoginFlow) {
        Write-WarnLine "Certificado Cloudflare ausente. Abrindo login para $CloudflareZoneValue..."
        Start-Process -FilePath $CloudflaredExePath -ArgumentList @('tunnel', 'login', $CloudflareZoneValue) | Out-Null
    }

    throw "Certificado Cloudflare ausente em $OriginCertPath. Conclua 'cloudflared tunnel login $CloudflareZoneValue' no navegador e rode o setup novamente."
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

function Get-TunnelRecord {
    param(
        [string] $CloudflaredExePath,
        [string] $TunnelNameValue
    )

    $json = & $CloudflaredExePath tunnel list --output json --name $TunnelNameValue 2>$null
    if ([string]::IsNullOrWhiteSpace(($json | Out-String))) { return $null }

    $records = @($json | ConvertFrom-Json)
    if ($records.Count -eq 0) { return $null }
    if ($records.Count -gt 1) { throw "Mais de um tunnel com o nome '$TunnelNameValue' foi encontrado." }
    return $records[0]
}

function Ensure-TunnelExists {
    param(
        [string] $CloudflaredExePath,
        [string] $TunnelNameValue
    )

    $record = Get-TunnelRecord -CloudflaredExePath $CloudflaredExePath -TunnelNameValue $TunnelNameValue
    if ($record) { return $record }

    Write-Info "Criando named tunnel '$TunnelNameValue'..."
    & $CloudflaredExePath tunnel create --output json $TunnelNameValue | Out-Host

    $record = Get-TunnelRecord -CloudflaredExePath $CloudflaredExePath -TunnelNameValue $TunnelNameValue
    if (-not $record) {
        throw "O tunnel '$TunnelNameValue' nao apareceu na listagem apos a criacao."
    }

    return $record
}

function Ensure-DnsRoute {
    param(
        [string] $CloudflaredExePath,
        [string] $TunnelNameValue,
        [string] $TunnelHostnameValue
    )

    Write-Info "Configurando DNS '$TunnelHostnameValue' para '$TunnelNameValue'..."
    & $CloudflaredExePath tunnel route dns --overwrite-dns $TunnelNameValue $TunnelHostnameValue | Out-Host
}

function Build-StatePayload {
    param(
        [string] $TunnelNameValue,
        [string] $TunnelIdValue,
        [string] $TunnelHostnameValue,
        [string] $LocalTargetUrlValue,
        [string] $HealthPath,
        [string] $StatePath,
        [string] $ApiEnvPath,
        [string] $CloudflaredExePath,
        [string] $CloudflaredVersion,
        [string] $CloudflareZoneValue,
        [string] $ZApiInstanceKeyValue,
        [string] $WhatsAppProviderValue
    )

    $zapiInstanceSegment = if ([string]::IsNullOrWhiteSpace($ZApiInstanceKeyValue)) { '{EXTERNAL_INSTANCE_ID}' } else { $ZApiInstanceKeyValue }
    $publicBaseUrl = "https://$TunnelHostnameValue"
    $zapiBase = "$publicBaseUrl/api/v1/webhooks/whatsapp/$WhatsAppProviderValue/$zapiInstanceSegment"

    return [ordered] @{
        generated_at = (Get-Date).ToString('o')
        transport = 'cloudflare-named-tunnel'
        zone = $CloudflareZoneValue
        tunnel_name = $TunnelNameValue
        tunnel_id = $TunnelIdValue
        hostname = $TunnelHostnameValue
        local_target_url = $LocalTargetUrlValue
        public_base_url = $publicBaseUrl
        public_health_url = $publicBaseUrl + $HealthPath
        api_env_path = $ApiEnvPath
        state_file = $StatePath
        cloudflared = [ordered] @{
            executable_path = $CloudflaredExePath
            version = $CloudflaredVersion
        }
        urls = [ordered] @{
            zapi_inbound = $zapiBase + '/inbound'
            zapi_status = $zapiBase + '/status'
            zapi_delivery = $zapiBase + '/delivery'
            pagarme = "$publicBaseUrl/api/v1/webhooks/billing/pagarme"
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
$originCertPath = Resolve-OriginCertPath
$statePath = Resolve-StateFilePath -ExplicitPath $StateFilePath -TunnelNameValue $TunnelName

if ([string]::IsNullOrWhiteSpace($ZApiInstanceKey)) { $ZApiInstanceKey = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_INSTANCE_ID' }
if ([string]::IsNullOrWhiteSpace($ZApiInstanceId)) { $ZApiInstanceId = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_INSTANCE_ID' }
if ([string]::IsNullOrWhiteSpace($ZApiToken)) { $ZApiToken = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_TOKEN' }
if ([string]::IsNullOrWhiteSpace($ZApiClientToken)) { $ZApiClientToken = Get-DotEnvValue -Path $apiEnvPath -Key 'WHATSAPP_AUTH_ZAPI_CLIENT_TOKEN' }

Write-Info "cloudflared: $cloudflaredExePath"
Write-Info $cloudflaredVersion
Write-Info "Tunnel name: $TunnelName"
Write-Info "Hostname fixo: $TunnelHostname"
Write-Info "Origem local: $originBaseUrl"

if ($healthProbe.Success) {
    Write-Ok "API local respondeu $($healthProbe.StatusCode) em $($healthProbe.Path)"
} else {
    Write-WarnLine 'A API local nao respondeu em /up, /health/live ou /.'
}

$statePayload = Build-StatePayload `
    -TunnelNameValue $TunnelName `
    -TunnelIdValue '{TUNNEL_ID}' `
    -TunnelHostnameValue $TunnelHostname `
    -LocalTargetUrlValue $originBaseUrl `
    -HealthPath $healthProbe.Path `
    -StatePath $statePath `
    -ApiEnvPath $apiEnvPath `
    -CloudflaredExePath $cloudflaredExePath `
    -CloudflaredVersion $cloudflaredVersion `
    -CloudflareZoneValue $CloudflareZone `
    -ZApiInstanceKeyValue $ZApiInstanceKey `
    -WhatsAppProviderValue $WhatsAppProvider

if ($DryRun) {
    Show-Urls -StatePayload $statePayload
    Write-Info "Login necessario: `"$cloudflaredExePath`" tunnel login $CloudflareZone"
    Write-Info "Provisionamento: `"$cloudflaredExePath`" tunnel create $TunnelName"
    Write-Info "DNS: `"$cloudflaredExePath`" tunnel route dns --overwrite-dns $TunnelName $TunnelHostname"
    return
}

Assert-OriginCert -OriginCertPath $originCertPath -CloudflaredExePath $cloudflaredExePath -CloudflareZoneValue $CloudflareZone -LaunchLoginFlow $LaunchLogin.IsPresent

$tunnelRecord = Ensure-TunnelExists -CloudflaredExePath $cloudflaredExePath -TunnelNameValue $TunnelName
Ensure-DnsRoute -CloudflaredExePath $cloudflaredExePath -TunnelNameValue $TunnelName -TunnelHostnameValue $TunnelHostname

$statePayload = Build-StatePayload `
    -TunnelNameValue $TunnelName `
    -TunnelIdValue $tunnelRecord.id `
    -TunnelHostnameValue $TunnelHostname `
    -LocalTargetUrlValue $originBaseUrl `
    -HealthPath $healthProbe.Path `
    -StatePath $statePath `
    -ApiEnvPath $apiEnvPath `
    -CloudflaredExePath $cloudflaredExePath `
    -CloudflaredVersion $cloudflaredVersion `
    -CloudflareZoneValue $CloudflareZone `
    -ZApiInstanceKeyValue $ZApiInstanceKey `
    -WhatsAppProviderValue $WhatsAppProvider

Write-StateFile -Path $statePath -Payload $statePayload
Show-Urls -StatePayload $statePayload

if ($UpdateZApiWebhook) {
    $updateResult = Update-ZApiWebhook -WebhookUrl $statePayload.urls.zapi_inbound -InstanceId $ZApiInstanceId -Token $ZApiToken -ClientToken $ZApiClientToken
    Write-Ok "Z-API webhook atualizado: $($updateResult.Webhook | ConvertTo-Json -Compress)"
    Write-Ok "Z-API notifySentByMe: $($updateResult.NotifySentByMe | ConvertTo-Json -Compress)"
}

Write-Ok 'Named tunnel provisionado. Rode o script de start para manter a conexao ativa.'
