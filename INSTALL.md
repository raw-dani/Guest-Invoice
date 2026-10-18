# Panduan Instalasi — Modul WHMCS Guest Invoice & Payment

Dokumen ini menjelaskan langkah-langkah instalasi, aktivasi, dan verifikasi modul **Guest Invoice & Payment** (`guestinvoice`) pada lingkungan WHMCS produksi atau staging.

---

## 1. Persyaratan Sistem

Pastikan server hosting/server billing Anda memenuhi spesifikasi berikut:
- **WHMCS:** Versi 8.0 hingga 8.12+ (direkomendasikan WHMCS 8.12.x).
- **PHP:** Versi 8.1 atau 8.2.
- **Ekstensi PHP:**
  - `pdo_mysql` / `mysqli`
  - `curl` (untuk komunikasi API gateway)
  - `openssl` & `random_bytes` (bawaan PHP 8.1+)
  - `json` & `mbstring`
- **Database:** MySQL 5.7+ atau MariaDB 10.3+.
- **Protokol:** Wajib menggunakan HTTPS pada URL WHMCS (`SystemURL`).

---

## 2. Langkah Instalasi

### Langkah 1: Unggah Berkas Modul
Ekstrak paket ZIP atau salin direktori `guestinvoice` ke dalam folder addon WHMCS:
```text
WHMCS_ROOT/modules/addons/guestinvoice/
```
Pastikan struktur di dalam server menjadi:
```text
WHMCS_ROOT/
└── modules/
    └── addons/
        └── guestinvoice/
            ├── guestinvoice.php
            ├── hooks.php
            ├── lib/
            ├── controllers/
            ├── templates/
            ├── assets/
            ├── lang/
            └── migrations/
```

### Langkah 2: Perizinan Berkas (File Permissions)
Atur kepemilikan dan permission berkas sesuai standar web server (Apache/Nginx/LiteSpeed):
- Direktori: `0755`
- Berkas PHP/TPL/CSS/JS: `0644`

Contoh via SSH/terminal Linux:
```bash
chown -R www-data:www-data /path/to/whmcs/modules/addons/guestinvoice
find /path/to/whmcs/modules/addons/guestinvoice -type d -exec chmod 755 {} \;
find /path/to/whmcs/modules/addons/guestinvoice -type f -exec chmod 644 {} \;
```

### Langkah 3: Aktivasi di Admin Area WHMCS
1. Masuk ke **WHMCS Admin Area** sebagai Administrator Utama (Full Administrator).
2. Arahkan menu ke **System Settings (Ikon Kunci / Pengaturan)** > **Addon Modules**.
3. Cari modul bernama **Guest Invoice & Payment**.
4. Klik tombol hijau **Activate**.
   - Saat aktivasi, modul secara otomatis membuat 4 tabel khusus:
     - `mod_guestinvoice_tokens`
     - `mod_guestinvoice_logs`
     - `mod_guestinvoice_settings`
     - `mod_guestinvoice_rate_limits`
   - Modul tidak mengubah tabel inti WHMCS.
5. Setelah status berubah menjadi aktif, klik **Configure**:
   - Berikan tanda centang pada opsi **Access Control** untuk grup admin yang berhak (misal: *Full Administrator*).
   - Simpan perubahan dengan menekan tombol **Save Changes**.

---

## 3. Verifikasi Instalasi

1. **Akses Dashboard Modul:**
   Buka menu admin **Addons** > **Guest Invoice & Payment**. Halaman ringkasan modul akan muncul dengan metrik 0 token.
2. **Uji Pembuatan Tautan Pertama:**
   - Masuk ke tab **Buat Tautan (Single / Bulk)**.
   - Masukkan salah satu ID invoice WHMCS yang berstatus *Unpaid*.
   - Klik **Generate Tautan Guest Invoice**.
   - Salin tautan yang dihasilkan, lalu buka di jendela penyamaran (*Incognito / Private Window*) tanpa login.
3. **Uji Widget Invoice Admin:**
   - Buka tagihan di **Billing** > **Invoices** > klik tagihan yang Anda buatkan link tadi.
   - Panel biru **Guest Invoice & Payment Access** akan tampil otomatis dengan opsi salin atau kirim ulang tautan.

---

## 4. Konfigurasi URL Publik

Modul menggunakan sistem routing resmi WHMCS addon:
```text
https://billing.domainanda.com/index.php?m=guestinvoice&token=<TOKEN_RAHASIA>
```

### (Opsional) URL Bersih (Clean Pretty URLs) via Nginx / Apache
Jika Anda ingin menggunakan URL bersih seperti `https://billing.domainanda.com/guest-invoice/<TOKEN>`, Anda dapat menambahkan aturan rewrite di web server Anda:

#### Apache (`.htaccess`):
```apache
RewriteEngine On
RewriteRule ^guest-invoice/([a-f0-9]{64})$ index.php?m=guestinvoice&token=$1 [QSA,L]
```

#### Nginx:
```nginx
location /guest-invoice/ {
    rewrite ^/guest-invoice/([a-f0-9]{64})$ /index.php?m=guestinvoice&token=$1 last;
}
```

---

## 5. Prosedur Penonaktifan (Deactivation)

Jika Anda menonaktifkan modul (**Deactivate**) di WHMCS Admin:
- Modul berhenti berjalan.
- **Data riwayat token, audit log, dan pengaturan konfigurasi TETAP AMAN dan TIDAK DIHAPUS**.
- Jika diaktifkan kembali di masa mendatang, seluruh data tautan sebelumnya akan langsung aktif kembali.
