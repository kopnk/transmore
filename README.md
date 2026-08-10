# TransMore

Aplikasi operasional transportasi offline-first berbasis Nuxt 4, Pinia, Dexie, Tailwind CSS, Chart.js, Day.js, Zod, Vue Sonner, dan ExcelJS.

```bash
npm install
npm run dev
```

`npm run dev` menjalankan Nuxt dan backend PHP secara bersamaan. Gunakan `npm run dev:frontend` atau `npm run dev:backend` hanya jika ingin menjalankannya di terminal terpisah.

User, master data, permission, audit, dan sesi disimpan di backend PHP/MySQL. Kredensial awal harus diganti saat setup. IndexedDB hanya menyimpan transaksi pengiriman, outbox, dan grant offline bertanda tangan agar operasional tetap berjalan ketika koneksi terputus.

Saat pertama kali login setelah pembaruan, data PKS, kendaraan, dan kebun dari IndexedDB versi lama diimpor secara idempotent ke MySQL lalu dibersihkan dari IndexedDB setelah server mengonfirmasi keberhasilan. API tidak disimpan di localStorage maupun cache service worker.

## Deploy Hostinger satu domain

1. Jalankan `npx nuxi generate`.
2. Upload seluruh isi `.output/public/` ke `public_html/`.
3. Upload folder `backend/` ke folder domain, sejajar dengan `public_html/`, tanpa membawa `.env` lokal dan key dalam `backend/storage/`.
4. Buat `backend/.env` dari `.env.production.example` dan isi kredensial MySQL Hostinger.
5. Jalankan `php backend/migrate.php` melalui SSH, atau import seluruh migration secara berurutan melalui phpMyAdmin.

Frontend selalu memakai `/api`: saat development diproxy ke `127.0.0.1:8000`, sedangkan production dilayani oleh PHP pada domain Hostinger yang sama.
