# SCRIPT DEPLOY CIMÒ
Clear-Host
Write-Host "🚀 AVVIO DEPLOY CIMÒ V3.4.0 PRO" -ForegroundColor Magenta

# Recupero messaggio di commit
$msg = Read-Host "📝 Cosa hai modificato?"
if (-not $msg) { $msg = "Update automatico CIMÒ - Fix Layout" }

Write-Host "📡 Invio a GitHub..." -ForegroundColor Cyan
git add .
# Usiamo il messaggio inserito dall'utente
git commit -m "$msg"

# Forziamo il push su master assicurandoci che sia sincronizzato
git push origin master

Write-Host "⏳ Attesa build e propagazione (15s)..." -ForegroundColor Yellow
Start-Sleep -Seconds 15

Write-Host "☸️ Riavvio Pods Kubernetes..." -ForegroundColor Cyan
# Il comando delete pod forzerà il ReplicaSet a crearne di nuovi con l'ultima immagine
kubectl delete pod -l app=web-automatico

Write-Host "✅ DEPLOY COMPLETATO CON SUCCESSO!" -ForegroundColor Green
Write-Host "🌐 Controlla l'interfaccia di CIMÒ tra pochi istanti." -ForegroundColor Gray