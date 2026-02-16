# SCRIPT DEPLOY CIMÒ
Clear-Host
Write-Host "🚀 AVVIO DEPLOY CIMÒ V3.3.1" -ForegroundColor Magenta

$msg = Read-Host "📝 Cosa hai modificato?"
if (-not $msg) { $msg = "Update automatico CIMÒ" }

Write-Host "📡 Invio a GitHub..." -ForegroundColor Cyan
git add .
git commit -m "$msg"
git push origin master

Write-Host "⏳ Attesa build (15s)..." -ForegroundColor Yellow
Start-Sleep -Seconds 15

Write-Host "☸️ Riavvio Kubernetes..." -ForegroundColor Cyan
kubectl delete pod -l app=web-automatico

Write-Host "✅ DEPLOY COMPLETATO!" -ForegroundColor Green