param(
    [switch]$Production
)

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

Write-Host "=== [1/6] Checking Prerequisites (git, docker, docker compose) ===" -ForegroundColor Cyan
foreach ($cmd in @("git", "docker")) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        throw "Missing required prerequisite: $cmd"
    }
}
docker compose version | Out-Null

Write-Host "=== [2/6] Generating Missing Fail-Closed Secrets ===" -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path ".\secrets", ".\infra\secrets" | Out-Null
$secretNames = @("db_password", "redis_password", "radius_secret", "rabbitmq_password", "bkash_secret", "nagad_secret")

function New-RandomSecret {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
    -join ((1..32) | ForEach-Object { $chars[(Get-Random -Maximum $chars.Length)] })
}

foreach ($name in $secretNames) {
    $s1 = ".\secrets\$name.txt"
    $s2 = ".\infra\secrets\$name.txt"
    if (-not (Test-Path $s1) -or ((Get-Item $s1).Length -eq 0)) {
        (New-RandomSecret) | Set-Content -Path $s1 -NoNewline -Encoding ASCII
    }
    Copy-Item -Path $s1 -Destination $s2 -Force
}

Write-Host "=== [3/6] Configuring backend/.env ===" -ForegroundColor Cyan
if (-not (Test-Path ".\backend\.env")) {
    Copy-Item ".\backend\.env.example" ".\backend\.env"
}

$dbPass   = (Get-Content ".\secrets\db_password.txt" -Raw).Trim()
$radSec   = (Get-Content ".\secrets\radius_secret.txt" -Raw).Trim()
$mfsKey   = (Get-Content ".\secrets\bkash_secret.txt" -Raw).Trim()
$mockFlag = if ($Production) { "false" } else { "true" }

$envLines = Get-Content ".\backend\.env"
$envLines = $envLines -replace "^DB_PASSWORD=.*", "DB_PASSWORD=$dbPass"
$envLines = $envLines -replace "^RADIUS_SECRET=.*", "RADIUS_SECRET=$radSec"
$envLines = $envLines -replace "^MIKROTIK_MOCK=.*", "MIKROTIK_MOCK=$mockFlag"
if ($envLines -match "^MFS_WEBHOOK_HMAC_KEY=") {
    $envLines = $envLines -replace "^MFS_WEBHOOK_HMAC_KEY=.*", "MFS_WEBHOOK_HMAC_KEY=$mfsKey"
} else {
    $envLines += "MFS_WEBHOOK_HMAC_KEY=$mfsKey"
}
$envLines | Set-Content ".\backend\.env" -Encoding UTF8

Write-Host "=== [4/6] Building Frontend Vite Assets ===" -ForegroundColor Cyan
if (Get-Command npm -ErrorAction SilentlyContinue) {
    Push-Location ".\backend"
    npm ci
    npm run build
    Pop-Location
} else {
    docker run --rm -v "${RepoRoot}\backend:/app" -w /app node:20-alpine sh -c "npm ci && npm run build"
}

Write-Host "=== [5/6] Starting Docker Stack & Running Migrations ===" -ForegroundColor Cyan
$composeFile = if (Test-Path ".\infra\docker-compose.yml") { ".\infra\docker-compose.yml" } else { ".\docker-compose.yml" }
docker compose -f $composeFile up -d --build

$appContainer = (docker ps --format "{{.Names}}" | Where-Object { $_ -match "laravel" } | Select-Object -First 1)
if (-not $appContainer) { throw "Laravel container did not start." }
$workdir = (docker inspect --format '{{.Config.WorkingDir}}' $appContainer)

Start-Sleep -Seconds 8
docker exec -w $workdir $appContainer php artisan key:generate --force --no-interaction
docker exec -w $workdir $appContainer php artisan migrate --force
docker exec -w $workdir $appContainer php artisan db:seed --force
docker exec -w $workdir $appContainer php artisan radius:maintain-partitions
docker exec -w $workdir $appContainer php artisan optimize:clear

Write-Host "=== [6/6] Verifying /api/v1/health & Live GUIs ===" -ForegroundColor Cyan
try {
    $resp = Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/v1/health" -UseBasicParsing -TimeoutSec 10
    Write-Host "Health Check HTTP Status: $($resp.StatusCode)" -ForegroundColor Green
} catch {
    Write-Warning "Health endpoint not yet responding on :8000 ($($_.Exception.Message))"
}

Write-Host @"

====================================================================
 LIVE SYSTEM GUIs READY
====================================================================
 Self-Care Portal        : http://127.0.0.1:8000/self-care
 Subscriber Dashboard    : http://127.0.0.1:8000/portal/dashboard
 Filament Admin Panel    : http://127.0.0.1:8000/admin
 NOC Command Center      : http://127.0.0.1:8000/noc/dashboard
 NOC Carrier Operations  : http://127.0.0.1:8000/noc/carrier
 Hotspot Captive Portal  : http://127.0.0.1:8000/hotspot/login
 Gemini Audit Hub        : http://127.0.0.1:8000/gemini/audit
 Grafana / Prometheus    : http://127.0.0.1:3000  |  http://127.0.0.1:9090
 RabbitMQ Management     : http://127.0.0.1:15672
====================================================================
"@ -ForegroundColor Green
