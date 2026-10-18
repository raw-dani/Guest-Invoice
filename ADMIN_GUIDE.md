# Panduan Administrator — Modul WHMCS Guest Invoice & Payment

Dokumen ini ditujukan bagi staf penagihan (billing staff) dan administrator WHMCS untuk mengelola alur kerja tautan tagihan guest harian.

---

## 1. Alur Kerja Harian

### A. Membuat Tautan Guest untuk Satu Invoice
Ada dua cara mudah membuat tautan:

#### Cara 1: Langsung dari Halaman Invoice WHMCS (Paling Cepat)
1. Buka menu **Billing** > **Invoices**.
2. Klik nomor invoice yang ingin Anda kelola.
3. Di bagian bawah ringkasan invoice, temukan panel **Guest Invoice & Payment Access**:
   - Jika belum ada tautan aktif: Klik tombol **Buat Tautan Guest Invoice**.
   - Jika tautan sudah ada: Tautan aktif beserta tanggal kedaluwarsa akan ditampilkan. Anda dapat mengklik **Kirim Ulang Link ke Klien** atau **Cabut Tautan**.
4. Klik **Buka di Dashboard Modul** untuk menyalin tautan langsung.

#### Cara 2: Dari Dashboard Modul
1. Buka menu **Addons** > **Guest Invoice & Payment**.
2. Pilih tab **Buat Tautan (Single / Bulk)**.
3. Pada kartu sebelah kiri (*Buat Tautan Single Invoice*):
   - Masukkan ID Invoice WHMCS (contoh: `104`).
   - Pilih masa berlaku (misal: 30 Hari).
   - Centang opsi *Langsung kirimkan tautan melalui email resmi ke pelanggan* jika diinginkan.
   - Klik **Generate Tautan Guest Invoice**.
4. Kotak hijau akan muncul berisi URL lengkap. Klik tombol **Salin Tautan** untuk menyalinnya ke papan klip (*clipboard*), lalu tempel ke chat WhatsApp/Telegram pelanggan.

---

### B. Pembuatan Tautan Massal (Bulk Generator)
Cocok digunakan saat awal bulan atau setelah penagihan massal (*batch invoicing*):
1. Buka **Addons** > **Guest Invoice & Payment** > tab **Buat Tautan**.
2. Pada kartu sebelah kanan (*Buat Tautan Massal*):
   - Masukkan daftar ID tagihan yang ingin dibuatkan link. Anda dapat memisahkan dengan tanda koma atau baris baru:
     ```text
     101, 102, 103, 104, 105
     ```
   - Tentukan masa berlaku tautan.
   - Klik **Proses Bulk Generation**.
3. Sistem akan memproses setiap invoice secara independen. Tabel hasil akan menampilkan status:
   - **Sukses:** Menampilkan kolom input tautan dan tombol **Salin**.
   - **Gagal:** Menampilkan alasan kegagalan spesifik (misal: invoice tidak ditemukan) tanpa membatalkan invoice lainnya.

---

### C. Mencabut Tautan (Revoke Access)
Jika terjadi sengketa, salah kirim, atau pelanggan meminta pembatalan akses:
1. Buka tab **Kelola Tautan Aktif**.
2. Cari berdasarkan nomor ID tagihan pada kolom pencarian.
3. Klik tombol merah **Cabut (Revoke)**.
4. Tautan tersebut akan langsung nonaktif detik itu juga. Jika ada pihak yang mencoba membuka tautan tersebut kembali, sistem akan menampilkan pesan aman: *Tautan Tagihan Tidak Valid atau Kedaluwarsa*.

---

### D. Menginspeksi Log Keamanan
1. Buka tab **Audit Log Keamanan**.
2. Anda dapat melihat riwayat setiap kejadian:
   - `token_generated`: Pembuatan tautan oleh admin/sistem.
   - `invoice_viewed`: Pembukaan tagihan oleh pelanggan.
   - `payment_initiated`: Pelanggan mengklik tombol checkout gateway.
   - `payment_success`: Verifikasi pembayaran berhasil dari gateway.
   - `rate_limited`: IP pengunjung yang melebihi batas percobaan akses.
   - `access_denied`: Upaya akses token palsu atau kedaluwarsa.
3. Alamat IP disamarkan dengan *Salted SHA-256 Hash* untuk menjamin kepatuhan regulasi privasi data.

---

## 2. Praktik Terbaik Keamanan bagi Tim Billing
- Jangan pernah membagikan tautan guest di forum atau grup publik.
- Jika pelanggan melapor tidak menerima email atau tautan kedaluwarsa, cukup buatkan token baru melalui dashboard modul (token lama otomatis dicabut).
- Selalu periksa status akhir di modul atau di data transaksi tagihan WHMCS sebelum melakukan aktivasi layanan manual.
