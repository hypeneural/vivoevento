[CmdletBinding()]
param(
    [string] $LocalTargetUrl,
    [string] $SshTarget = 'free.pinggy.io',
    [int] $SshPort = 443,
    [int] $DebuggerPort = 4300,
    [int] $ConnectTimeoutSeconds = 25,
    [int] $ReconnectDelaySeconds = 5,
    [string] $ZApiInstanceKey,
    [string] $WhatsAppProvider = 'zapi',
    [string] $StateFilePath,
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

function Get-LocalForwardHost {
    param([string] $HostName)

    if ($HostName -in @('localhost', '127.0.0.1', '::1')) {
        return '127.0.0.1'
    }

    return $HostName
}

function Get-FreeTcpPort {
    param([int] $PreferredPort)

    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    $ports = @{}

    foreach ($listener in $listeners) {
        $ports[$listener.Port] = $true
    }

    $port = $PreferredPort

    while ($ports.ContainsKey($port)) {
        $port++
    }

    return $port
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

function Get-PinggyUrls {
    param(
        [int] $LocalDebuggerPort,
        [int] $TimeoutSeconds
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-RestMethod -Uri "http://127.0.0.1:$LocalDebuggerPort/urls" -TimeoutSec 4

            if ($response.urls -and $response.urls.Count -gt 0) {
                return @($response.urls)
            }
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    return @()
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
        [bool] $PagarmeBasicAuthConfigured
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
        local_target_url = $LocalTargetUrlValue
        public_base_url = $PublicBaseUrl
        public_health_url = $PublicBaseUrl + $HealthPath
        state_file = $StatePath
        api_env_path = $ApiEnvPath
        urls = [ordered] @{
            zapi_inbound = $zapiBase + '/inbound'
            zapi_status = $zapiBase + '/status'
            zapi_delivery = $zapiBase + '/delivery'
            pagarme = $pagarmeUrl
        }
        pagarme = [ordered] @{
            basic_auth_configured = $PagarmeBasicAuthConfigured
        }
        validation_examples = [ordered] @{
            health = "Invoke-WebRequest -Uri '$($PublicBaseUrl + $HealthPath)'"
            zapi = "Invoke-WebRequest -Method POST -Uri '$($zapiBase + '/inbound')' -ContentType 'application/json' -Body '{""messageId"":""dev-test"",""body"":""pinggy test""}'"
            pagarme = "Invoke-WebRequest -Method POST -Uri '$pagarmeUrl' -ContentType 'application/json' -Body '{""id"":""evt_test"",""type"":""order.paid"",""created_at"":""$(Get-Date -Format o)"",""data"":{""id"":""or_test"",""metadata"":{""billing_order_uuid"":""00000000-0000-0000-0000-000000000000""}}}'"
        }
    }
}

function Save-StatePayload {
    param(
        [hashtable] $State,
        [string] $Path
    )

    $directory = Split-Path -Path $Path -Parent

    if (-not [string]::IsNullOrWhiteSpace($directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $State | ConvertTo-Json -Depth 8 | Set-Content -Path $Path -Encoding UTF8
}

function Show-Summary {
    param(
        [hashtable] $State,
        [bool] $PagarmeBasicAuthConfigured,
        [bool] $LocalHealthOk,
        [bool] $PublicHealthOk,
        [string] $PublicHealthError
    )

    Write-Host ''
    Write-Ok "Tunnel ativo em $($State.public_base_url)"
    Write-Host "Health publico: $($State.public_health_url)"
    Write-Host "Z-API inbound: $($State.urls.zapi_inbound)"
    Write-Host "Z-API status: $($State.urls.zapi_status)"
    Write-Host "Z-API delivery: $($State.urls.zapi_delivery)"
    Write-Host "Pagar.me: $($State.urls.pagarme)"
    Write-Host "Arquivo de estado: $($State.state_file)"

    if ($LocalHealthOk) {
        Write-Ok 'A API local respondeu antes de abrir o tunnel.'
    } else {
        Write-WarnLine 'A API local nao respondeu antes do tunnel. A URL publica pode abrir, mas os webhooks vao falhar ate a API subir.'
    }

    if ($PublicHealthOk) {
        Write-Ok 'A URL publica respondeu no health check.'
    } elseif (-not [string]::IsNullOrWhiteSpace($PublicHealthError)) {
        Write-WarnLine "Nao foi possivel validar a URL publica agora: $PublicHealthError"
    }

    if ($PagarmeBasicAuthConfigured) {
        Write-Ok 'Pagar.me Basic Auth esta configurado no apps/api/.env.'
    } else {
        Write-WarnLine 'PAGARME_WEBHOOK_BASIC_AUTH_USER/PASSWORD estao vazios. Pelo codigo atual, o endpoint do Pagar.me aceita webhook sem auth quando ambos estao vazios.'
    }

    if ($State.urls.zapi_inbound -like '*{EXTERNAL_INSTANCE_ID}*') {
        Write-WarnLine 'Passe -ZApiInstanceKey para gerar a URL final da sua instancia Z-API sem placeholder.'
    }

    Write-Host 'Worker Z-API: cd apps/api && php artisan queue:work redis --queue=whatsapp-inbound --tries=3'
    Write-Host 'Worker Billing: cd apps/api && php artisan queue:work redis --queue=billing --tries=3'
    Write-Host ''
}

$projectRoot = Resolve-ProjectRoot
$apiEnvPath = Resolve-ApiEnvPath -ProjectRoot $projectRoot
$resolvedLocalTargetUrl = Resolve-LocalTargetUrl -ProjectRoot $projectRoot -ExplicitUrl $LocalTargetUrl
$localTarget = Get-UriInfo -Url $resolvedLocalTargetUrl
$localForwardHost = Get-LocalForwardHost -HostName $localTarget.Host
$localHealth = Get-HealthProbe -BaseUrl $localTarget.BaseUrl
$localDebuggerPort = Get-FreeTcpPort -PreferredPort $DebuggerPort

if (-not $StateFilePath) {
    $StateFilePath = Join-Path $env:TEMP 'eventovivo-pinggy-webhook.json'
}

$pagarmeWebhookUser = Get-DotEnvValue -Path $apiEnvPath -Key 'PAGARME_WEBHOOK_BASIC_AUTH_USER'
$pagarmeWebhookPassword = Get-DotEnvValue -Path $apiEnvPath -Key 'PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD'
$pagarmeBasicAuthConfigured = -not (
    [string]::IsNullOrWhiteSpace($pagarmeWebhookUser) -or
    [string]::IsNullOrWhiteSpace($pagarmeWebhookPassword)
)

if (-not (Get-Command ssh -ErrorAction SilentlyContinue)) {
    throw 'Nao encontrei o comando ssh neste Windows. Instale o OpenSSH Client e rode novamente.'
}

if ($localDebuggerPort -ne $DebuggerPort) {
    Write-WarnLine "A porta local $DebuggerPort para o Web Debugger ja estava ocupada. Vou usar $localDebuggerPort."
}

Write-Info "Target local: $($localTarget.BaseUrl)"
Write-Info "Env da API: $apiEnvPath"

if ($localHealth.Success) {
    Write-Ok "API local respondeu em $($localTarget.BaseUrl)$($localHealth.Path) com status $($localHealth.StatusCode)."
} else {
    Write-WarnLine $localHealth.Error
}

$sshArguments = @(
    '-T',
    '-p', $SshPort.ToString(),
    '-L', "$localDebuggerPort`:localhost:4300",
    '-o', 'ServerAliveInterval=30',
    '-o', 'ServerAliveCountMax=3',
    '-o', 'TCPKeepAlive=yes',
    '-o', 'ExitOnForwardFailure=yes',
    '-o', 'StrictHostKeyChecking=no',
    "-R0:$localForwardHost`:$($localTarget.Port)",
    $SshTarget
)

if ($DryRun) {
    $placeholderState = Build-StatePayload `
        -PublicBaseUrl 'https://SEU-TUNNEL.pinggy.link' `
        -HealthPath $localHealth.Path `
        -LocalTargetUrlValue $resolvedLocalTargetUrl `
        -ApiEnvPath $apiEnvPath `
        -StatePath $StateFilePath `
        -ZApiInstanceKeyValue $ZApiInstanceKey `
        -WhatsAppProviderValue $WhatsAppProvider `
        -PagarmeBasicAuthConfigured $pagarmeBasicAuthConfigured

    Write-Host ''
    Write-Info 'Dry run ativo. Nada foi conectado.'
    Write-Host "Comando SSH: ssh $($sshArguments -join ' ')"
    Write-Host "Arquivo de estado: $StateFilePath"
    Write-Host "Z-API inbound: $($placeholderState.urls.zapi_inbound)"
    Write-Host "Pagar.me: $($placeholderState.urls.pagarme)"

    return
}

do {
    $stdoutPath = Join-Path $env:TEMP 'eventovivo-pinggy-stdout.log'
    $stderrPath = Join-Path $env:TEMP 'eventovivo-pinggy-stderr.log'

    Remove-Item $stdoutPath, $stderrPath -ErrorAction SilentlyContinue

    Write-Info "Abrindo tunnel em $SshTarget para ${localForwardHost}:$($localTarget.Port)..."

    $process = Start-Process `
        -FilePath 'ssh' `
        -ArgumentList $sshArguments `
        -PassThru `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -WindowStyle Hidden

    try {
        $urls = Get-PinggyUrls -LocalDebuggerPort $localDebuggerPort -TimeoutSeconds $ConnectTimeoutSeconds

        if ($urls.Count -eq 0) {
            throw 'Nao consegui ler a URL publica no Web Debugger do Pinggy. Verifique o acesso a free.pinggy.io.'
        }

        $publicBaseUrl = ($urls | Where-Object { $_ -like 'https://*' } | Select-Object -First 1)

        if ([string]::IsNullOrWhiteSpace($publicBaseUrl)) {
            $publicBaseUrl = $urls[0]
        }

        $state = Build-StatePayload `
            -PublicBaseUrl $publicBaseUrl `
            -HealthPath $localHealth.Path `
            -LocalTargetUrlValue $resolvedLocalTargetUrl `
            -ApiEnvPath $apiEnvPath `
            -StatePath $StateFilePath `
            -ZApiInstanceKeyValue $ZApiInstanceKey `
            -WhatsAppProviderValue $WhatsAppProvider `
            -PagarmeBasicAuthConfigured $pagarmeBasicAuthConfigured

        Save-StatePayload -State $state -Path $StateFilePath

        $publicHealth = Test-Url -Url $state.public_health_url

        Show-Summary `
            -State $state `
            -PagarmeBasicAuthConfigured $pagarmeBasicAuthConfigured `
            -LocalHealthOk $localHealth.Success `
            -PublicHealthOk $publicHealth.Success `
            -PublicHealthError $publicHealth.Error

        Wait-Process -Id $process.Id
        $exitCode = $process.ExitCode

        if ($exitCode -ne 0) {
            Write-WarnLine "O processo ssh encerrou com codigo $exitCode."

            if (Test-Path $stderrPath) {
                $stderrTail = Get-Content -Path $stderrPath -ErrorAction SilentlyContinue | Select-Object -Last 5

                foreach ($line in $stderrTail) {
                    if (-not [string]::IsNullOrWhiteSpace($line)) {
                        Write-WarnLine $line
                    }
                }
            }
        }
    } finally {
        if ($process -and -not $process.HasExited) {
            Stop-Process -Id $process.Id -Force
        }
    }

    if ($NoReconnect) {
        break
    }

    Write-WarnLine "Tunnel caiu ou foi encerrado. Tentando reconectar em $ReconnectDelaySeconds segundos..."
    Start-Sleep -Seconds $ReconnectDelaySeconds
} while ($true)
