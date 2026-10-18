# Panduan Konfigurasi — Modul WHMCS Guest Invoice & Payment

Modul ini menyediakan pengaturan fleksibel yang dapat dikonfigurasi melalui dua tempat:
1. **WHMCS Addon Settings** (`System Settings` > `Addon Modules` > `Configure`).
2. **Dashboard Modul Pengaturan** (`Addons` > `Guest Invoice & Payment` > Tab `Pengaturan Modul`).

---

## 1. Daftar Opsi Konfigurasi

| Pengaturan | Tipe | Nilai Default | Penjelasan |
|---|---|---|---|
| **Judul Branding Portal** (`custom_branding_title`) | Teks | `Portal Pembayaran & Tagihan Aman` | Judul portal yang ditampilkan di header halaman invoice guest. |
| **Masa Berlaku Default** (`default_expiry_days`) | Angka / Pilihan | `30` (Hari) | Jumlah hari masa aktif tautan setelah dibuat. Pilihan: 7, 14, 30, 60, atau 90 hari. |
| **Otomatis Buat Tautan** (`auto_generate_on_invoice_created`) | Centang (Boolean) | `Aktif (1)` | Jika dicentang, tautan guest akan dibuat otomatis saat invoice diterbitkan di WHMCS via hook `InvoiceCreated`. |
| **Otomatis Kirim Email** (`auto_email_on_invoice_created`) | Centang (Boolean) | `Nonaktif (0)` | Jika dicentang, email pemberitahuan tautan guest langsung dikirimkan ke pelanggan saat invoice dibuat. |
| **Izinkan Pembayaran Guest** (`enable_guest_payment`) | Centang (Boolean) | `Aktif (1)` | Mengaktifkan tombol pembayaran dan alur checkout online. Jika dinonaktifkan, halaman publik hanya berfungsi sebagai *view-only invoice*. |
| **Izinkan Unduh PDF** (`allow_pdf_download`) | Centang (Boolean) | `Nonaktif (0)` | Mengizinkan pengunjung yang memiliki token valid untuk mengunduh dokumen PDF resmi tagihan. |
| **Batas Percobaan Akses** (`rate_limit_per_minute`) | Angka | `15` | Maksimal percobaan pembacaan token per IP dalam 1 menit sebelum sistem menerapkan penguncian sementara. |
| **Durasi Lockout Rate Limit** (`rate_limit_lockout_minutes`) | Angka | `15` (Menit) | Lamanya waktu blokir sementara jika IP melampaui batas percobaan akses. |
| **Retensi Audit Log** (`log_retention_days`) | Angka | `90` (Hari) | Jumlah hari riwayat audit log disimpan di database sebelum otomatis dibersihkan oleh cron harian WHMCS. |

---

## 2. Pengaturan Payment Gateway

Modul mendeteksi payment gateway yang sedang aktif di WHMCS Anda (**System Settings** > **Payment Gateways**).

### A. Transfer Bank Manual
1. Pastikan modul gateway `Bank Transfer` aktif di WHMCS.
2. Isi kolom konfigurasi bawaan WHMCS:
   - **Bank Name:** Nama Bank (misal: BCA / Mandiri).
   - **Account Number:** Nomor Rekening.
   - **Account Name:** Nama Pemilik Rekening.
   - **Instructions:** Petunjuk transfer dan konfirmasi.
3. Modul Guest Invoice akan menampilkan kotak informasi rekening rapi dengan tombol salin otomatis untuk nomor rekening dan nomor referensi invoice.

### B. Midtrans Snap
1. Aktifkan modul gateway Midtrans di WHMCS Anda.
2. Masukkan **Server Key** dan **Client Key** dari portal Dashboard Midtrans (MAP).
3. Atur mode Environment: *Sandbox* untuk pengujian atau *Production* untuk transaksi langsung.
4. Tautan balik (finish redirect) dan verifikasi webhook SHA-512 akan ditangani otomatis.

### C. Xendit Invoice
1. Aktifkan modul gateway Xendit di WHMCS Anda.
2. Masukkan **Secret API Key** dari dashboard Xendit.
3. (Opsional namun sangat disarankan): Masukkan **Callback Verification Token** agar modul dapat memvalidasi signature header webhook Xendit.

### D. Tripay
1. Aktifkan gateway Tripay di WHMCS Anda.
2. Masukkan **API Key**, **Private Key**, dan **Merchant Code**.
3. Pilih metode pembayaran default (misal: QRIS atau Virtual Account).

### E. Duitku
1. Aktifkan modul gateway Duitku di WHMCS Anda.
2. Masukkan **Merchant Code** dan **API Key** (Merchant Key) dari dashboard Duitku.
3. Atur mode Environment: *Sandbox* untuk pengujian atau *Production* untuk transaksi langsung.
4. Tautan balik (return URL) dan webhook verifikasi MD5 akan diproses secara otomatis oleh adapter Duitku.

---

## 3. Konfigurasi Template Email

Secara default, modul mengirimkan email profesional berdesain responsif melalui fungsi email inti WHMCS.

Jika Anda ingin membuat template kustom sendiri:
1. Buka **System Settings** > **Email Templates**.
2. Buat template baru bertipe **General** dengan nama: `Guest Invoice Link Delivery`.
3. Gunakan variabel Smarty berikut dalam badan email:
   - `{$client_name}`: Nama lengkap klien.
   - `{$invoice_num}`: Nomor invoice.
   - `{$invoice_total}`: Total tagihan berformat mata uang (misal: Rp 1.500.000).
   - `{$invoice_date}`: Tanggal invoice.
   - `{$invoice_due_date}`: Tanggal jatuh tempo.
   - `{$guest_invoice_url}`: Tautan lengkap akses guest invoice berenkripsi.
   - `{$link_expires_at}`: Tanggal kedaluwarsa tautan.
   - `{$company_name}`: Nama perusahaan hosting/billing Anda.
