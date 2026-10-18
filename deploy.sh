#!/usr/bin/env bash
set -e

echo "🚀 Memulai proses deployment Racunin ke Production..."

# 1. Aktifkan Maintenance Mode jika diperlukan
# php artisan down --render="errors::503" --secret="1630542a-246b-4b66-afa1-dd72a4c43515"

# 2. Pull Kode Terbaru dari Git
echo "📥 Mengambil kode terbaru..."
git pull origin main

# 3. Install/Update Dependensi PHP
echo "📦 Menginstall dependencies Composer..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Migrasi Database
echo "🗄️ Menjalankan migrasi database..."
php artisan migrate --force

# 5. Build Frontend Assets (Vite)
echo "⚡ Mengompilasi frontend assets..."
npm ci --no-audit
npm run build

# 6. Cache & Optimasi Laravel
echo "🧹 Memperbarui cache dan optimasi..."
php artisan optimize:clear
php artisan optimize
php artisan event:cache

# 7. Restart Queue Workers & Scheduler (pick up the new code)
echo "🔄 Merestart background queue workers & scheduler..."
php artisan queue:restart
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart affiliate-worker:* affiliate-scheduler
fi

# 8. Matikan Maintenance Mode
# php artisan up

echo "✅ Deployment Racunin Berhasil Selesai!"
