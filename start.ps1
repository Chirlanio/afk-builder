# AFK Builder — script de inicialização para desenvolvimento
#
# Valida pré-requisitos e dispara os 4 processos do `composer dev`:
#   - php artisan serve (backend Laravel)
#   - php artisan queue:listen --tries=1 (worker da fila — necessário para /admin/sync)
#   - php artisan pail (logs em tempo real)
#   - npm run dev (Vite dev server com HMR)
#
# Uso:
#   .\start.ps1              → verifica + roda
#   .\start.ps1 -SkipChecks  → pula validação (mais rápido em re-runs)
#   .\start.ps1 -Migrate     → roda migrations pendentes antes
#

param(
    [switch]$SkipChecks,
    [switch]$Migrate
)

$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
Set-Location $projectRoot

function Write-Step($msg) {
    Write-Host "==> $msg" -ForegroundColor Cyan
}

function Write-Ok($msg) {
    Write-Host "    OK: $msg" -ForegroundColor Green
}

function Write-Warn($msg) {
    Write-Host "    AVISO: $msg" -ForegroundColor Yellow
}

function Write-Fail($msg) {
    Write-Host "    ERRO: $msg" -ForegroundColor Red
}

function Test-Command($name) {
    $null = Get-Command $name -ErrorAction SilentlyContinue
    return $?
}

if (-not $SkipChecks) {
    Write-Step "Validando pré-requisitos"

    $missing = @()
    foreach ($cmd in @('php', 'composer', 'node', 'npm')) {
        if (Test-Command $cmd) {
            Write-Ok "$cmd disponível"
        } else {
            Write-Fail "$cmd não encontrado no PATH"
            $missing += $cmd
        }
    }
    if ($missing.Count -gt 0) {
        throw "Instale ou adicione ao PATH: $($missing -join ', ')"
    }

    if (-not (Test-Path '.env')) {
        Write-Fail '.env não existe — copie .env.example e rode `php artisan key:generate`'
        throw 'Setup incompleto'
    }
    Write-Ok '.env existe'

    if (-not (Test-Path 'vendor')) {
        Write-Fail 'vendor/ não existe — rode `composer install`'
        throw 'Dependências PHP faltando'
    }
    Write-Ok 'vendor/ presente'

    if (-not (Test-Path 'node_modules')) {
        Write-Fail 'node_modules/ não existe — rode `npm install`'
        throw 'Dependências JS faltando'
    }
    Write-Ok 'node_modules/ presente'

    Write-Step "Verificando conexão com banco e migrations"
    $statusOutput = & php artisan migrate:status 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fail 'falha ao consultar migrations — MySQL está rodando? Confira o .env.'
        Write-Host $statusOutput
        throw 'BD inacessível'
    }
    Write-Ok 'banco acessível'

    $pending = $statusOutput | Select-String -Pattern '\bPending\b'
    if ($pending) {
        Write-Warn "há migrations pendentes ($($pending.Count))"
        if ($Migrate) {
            Write-Step "Aplicando migrations pendentes"
            & php artisan migrate --force
            if ($LASTEXITCODE -ne 0) { throw 'migrate falhou' }
        } else {
            Write-Warn 'rode com -Migrate para aplicá-las antes de subir'
        }
    } else {
        Write-Ok 'migrations em dia'
    }
}

Write-Step "Subindo serviços (server + queue:listen + pail + vite)"
Write-Host "    Ctrl+C interrompe todos os processos." -ForegroundColor DarkGray
Write-Host "    Acesse http://127.0.0.1:8000 quando o Vite anunciar." -ForegroundColor DarkGray
Write-Host ''

& composer dev
