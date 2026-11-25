# ==============================================================================
# NEXT MEDICALIAN – One-Click Deploy Script
# Powered by EdGenie
# ==============================================================================
param(
    [string]$Message = "Auto-deploy: update NEXT MEDICALIAN production build",
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  NEXT MEDICALIAN - Central Exam Management System        " -ForegroundColor Green
Write-Host "  One-Click Push and Production Deployment Pipeline       " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Compile Production Frontend Assets (Vite)
if (-not $SkipBuild) {
    Write-Host "[1/4] Compiling frontend production assets (Vite)..." -ForegroundColor Yellow
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Frontend build failed! Deployment aborted." -ForegroundColor Red
        exit 1
    }
    Write-Host "Frontend build completed successfully." -ForegroundColor Green
} else {
    Write-Host "[1/4] Skipping frontend build as requested." -ForegroundColor DarkGray
}

# Step 2: Stage and Commit Changes to Git
Write-Host "[2/4] Staging and committing changes to Git repository..." -ForegroundColor Yellow
git add .
$status = git status --porcelain
if ($status) {
    git commit -m "$Message"
    Write-Host "Committed changes with message: '$Message'" -ForegroundColor Green
} else {
    Write-Host "No unstaged changes detected. Continuing to push..." -ForegroundColor DarkGray
}

# Step 3: Push to GitHub Remote
Write-Host "[3/4] Pushing commits to GitHub (origin/main)..." -ForegroundColor Yellow
git push origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "Failed to push to GitHub remote! Check internet/credentials." -ForegroundColor Red
    exit 1
}
Write-Host "Pushed successfully to https://github.com/arponnovonex-collab/nextmedicalian.git" -ForegroundColor Green

# Step 4: Verify Live Site
Write-Host "[4/4] Verifying live deployment on Hostinger..." -ForegroundColor Yellow
Write-Host "Domain: https://next.thuhashop.com" -ForegroundColor Cyan

try {
    $response = & curl.exe -sI https://next.thuhashop.com/
    if ($response -match "HTTP/\S+\s+200") {
        Write-Host ""
        Write-Host "==========================================================" -ForegroundColor Green
        Write-Host "  DEPLOYMENT SUCCESSFUL! Live site is UP and RESPONDING:  " -ForegroundColor Green
        Write-Host "  URL: https://next.thuhashop.com                         " -ForegroundColor White
        Write-Host "==========================================================" -ForegroundColor Green
    } else {
        Write-Host "Live site responded. Verify at: https://next.thuhashop.com" -ForegroundColor Yellow
    }
} catch {
    Write-Host "Site pushed! Open in browser: https://next.thuhashop.com" -ForegroundColor Cyan
}

Write-Host ""
