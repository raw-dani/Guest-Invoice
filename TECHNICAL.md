# Dokumentasi Teknis (Technical Architecture & Reference)
## Guest Invoice & Payment — WHMCS Addon Module

Dokumentasi ini menjelaskan secara mendalam arsitektur sistem, alur kerja (workflow), skema database, sistem keamanan kriptografi, model integrasi payment gateway, dan referensi API internal modul **Guest Invoice & Payment** untuk WHMCS.

---

## 📑 Daftar Isi
1. [Ikhtisar Arsitektur](#1-ikhtisar-arsitektur)
2. [Spesifikasi Teknis & Kompatibilitas](#2-spesifikasi-teknis--kompatibilitas)
3. [Struktur Direktori & Komponen](#3-struktur-direktori--komponen)
4. [Skema & Migrasi Database](#4-skema--migrasi-database)
5. [Mekanisme Keamanan & Token Kriptografis](#5-mekanisme-keamanan--token-kriptografis)
6. [Data Sanitization & Privacy Protection (GDPR-Safe)](#6-data-sanitization--privacy-protection-gdpr-safe)
7. [Arsitektur Payment Gateway Adapter](#7-arsitektur-payment-gateway-adapter)
8. [Sistem Hooks WHMCS](#8-sistem-hooks-whmcs)
9. [Siklus Alur Kerja (Workflows)](#9-siklus-alur-kerja-workflows)
10. [Audit Logging & Forensik](#10-audit-logging--forensik)
11. [Rate Limiting & Mitigasi Brute-Force](#11-rate-limiting--mitigasi-brute-force)
12. [Panduan Pemecahan Masalah (Troubleshooting)](#12-panduan-pemecahan-masalah-troubleshooting)

---

## 1. Ikhtisar Arsitektur

Modul **Guest Invoice & Payment** dibangun mengikuti standar arsitektur OOP modern (PHP 7.4 - 8.2+) dengan pemisahan tanggung jawab yang jelas (*Separation of Concerns*):

```
┌────────────────────────────────────────────────────────────────────────┐
│                          WHMCS Core Ecosystem                          │
└──────────────┬───────────────────────────────┬─────────────────────────┘
               │ Hooks                         │ Gateway Functions
               ▼                               ▼
┌──────────────────────────────┐ ┌───────────────────────────────────────┐
│     WHMCS Hooks Handler      │ │     Payment Gateway Adapters          │
│   (hooks.php)                │ │   (AbstractGatewayAdapter, Midtrans,  │
│   - InvoiceCreation          │ │    WhmcsNativeAdapter, Tripay, dll)   │
│   - InvoicePaid              │ └───────────────────▲───────────────────┘
│   - AdminInvoicesControls    │                     │
└──────────────┬───────────────┘                     │
               │ Dispatches                          │ Initiates Payment
               ▼                                     │
┌────────────────────────────────────────────────────┴───────────────────┐
│                        Controllers Layer                               │
│  - GuestInvoiceController : Validasi token & render tampilan invoice   │
│  - GuestPaymentController : Orkestrasi pembayaran & auto-redirect      │
│  - PaymentResultController: Verifikasi return/callback status          │
│  - AdminController        : Manajemen token, audit log & konfigurasi   │
└──────────────┬───────────────────────────────┬─────────────────────────┘
               │ Uses                          │ Uses
               ▼                               ▼
┌──────────────────────────────┐ ┌───────────────────────────────────────┐
│     Core Services Layer      │ │      Security & Access Layer          │
│  - InvoiceService            │ │  - TokenService (CSPRNG 256-bit SHA)  │
│  - PaymentService            │ │  - AccessPolicy (Status & Expiry)     │
│  - MailService               │ │  - RateLimiter (Sliding Window Limit) │
│                              │ │  - AuditLogger (Anonymized Forensics) │
└──────────────┬───────────────┘ └───────────────────┬───────────────────┘
               │                                     │
               └───────────────────┬─────────────────┘
                                   │ Eloquent Capsule DB
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│                   Database Tables (MySQL / MariaDB)                    │
│  - mod_guestinvoice_tokens    - mod_guestinvoice_logs                  │
│  - mod_guestinvoice_settings  - mod_guestinvoice_ratelimits            │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Spesifikasi Teknis & Kompatibilitas

| Parameter | Spesifikasi |
|---|---|
| **Platform Host** | WHMCS v8.0 hingga v8.12+ |
| **Versi PHP** | PHP 7.4, PHP 8.0, PHP 8.1, PHP 8.2+ |
| **Database Engine** | MySQL 5.7+ / MariaDB 10.3+ (InnoDB) |
| **Ekstensi PHP Wajib** | `OpenSSL`, `cURL`, `JSON`, `PDO`, `mbstring` |
| **Namespace** | `GuestInvoice\` (PSR-4 compliant) |
| **Frontend Assets** | Vanilla CSS (tanpa framework eksternal), Vanilla JS |

---

## 3. Struktur Direktori & Komponen

```
modules/addons/guestinvoice/
├── assets/
│   ├── css/
│   │   ├── admin.css           # Styling antarmuka admin WHMCS
│   │   └── guest.css           # Styling faktur guest & antarmuka checkout
│   └── js/
│       ├── admin.js           # AJAX, clipboard utility, filter tabel admin
│       └── guest.js           # Interaksi pemilihan gateway & salin rekening
├── controllers/
│   ├── AdminController.php     # Controller backend admin WHMCS
│   ├── GuestInvoiceController.php # Controller publik faktur guest
│   ├── GuestPaymentController.php # Controller pemroses pembayaran guest
│   └── PaymentResultController.php# Controller halaman status/konfirmasi
├── lang/
│   ├── english.php             # Bahasa Inggris
│   └── indonesian.php          # Bahasa Indonesia
├── lib/
│   ├── Gateway/
│   │   ├── AbstractGatewayAdapter.php # Base class adapter & helper decrypt
│   │   ├── BankTransferAdapter.php    # Handler instruksi transfer manual
│   │   ├── DokuAdapter.php            # Adapter DOKU Hosted Checkout
│   │   ├── DuitkuAdapter.php          # Adapter Duitku Pop / Redirection
│   │   ├── GatewayInterface.php       # Interface standar adapter pembayaran
│   │   ├── MidtransAdapter.php        # Adapter Midtrans Snap (Direct & Fallback)
│   │   ├── TripayAdapter.php          # Adapter TriPay Closed Payment
│   │   ├── WhmcsNativeAdapter.php     # Bridge modul gateway bawaan WHMCS
│   │   └── XenditAdapter.php          # Adapter Xendit Invoice API
│   ├── AccessPolicy.php        # Penilai aturan akses (status, expiry, dll)
│   ├── AuditLogger.php         # Engine forensik & logging keamanan
│   ├── InvoiceService.php      # Formatting data invoice & masking privasi
│   ├── MailService.php         # Dispatcher email template dengan merge tags
│   ├── PaymentService.php      # Gateway router & payment executor
│   ├── RateLimiter.php         # Proteksi rate limit per IP & Token
│   └── TokenService.php        # Kriptografi, hashing, dan URL builder
├── migrations/
│   └── MigrationManager.php    # Skema DDL tabel database
├── templates/
│   ├── errors.tpl              # Template halaman error guest (403, 404, 429)
│   ├── invoice.tpl             # Template utama tampilan tagihan guest
│   └── payment.tpl             # Template instruksi pembayaran & offline transfer
├── guestinvoice.php            # Entry-point modul addon WHMCS
└── hooks.php                   # Listener event otomatis WHMCS
```

---

## 4. Skema & Migrasi Database

Modul menggunakan tabel terisolasi dengan prefiks `mod_guestinvoice_` untuk menjaga integritas database bawaan WHMCS:

### 1. `mod_guestinvoice_tokens`
Menyimpan token akses untuk setiap invoice.
```sql
CREATE TABLE `mod_guestinvoice_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `raw_token` varchar(128) DEFAULT NULL,
  `status` enum('Active','Revoked','Expired') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_by_admin_id` int(10) unsigned DEFAULT NULL,
  `last_access_at` datetime DEFAULT NULL,
  `access_count` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash_unique` (`token_hash`),
  KEY `invoice_id_index` (`invoice_id`),
  KEY `status_index` (`status`),
  KEY `expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. `mod_guestinvoice_logs`
Menyimpan catatan audit trail untuk setiap aktivitas.
```sql
CREATE TABLE `mod_guestinvoice_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `result` varchar(30) NOT NULL DEFAULT 'success',
  `actor_type` enum('admin','guest','system') NOT NULL DEFAULT 'guest',
  `actor_id` int(10) unsigned DEFAULT NULL,
  `ip_hash` varchar(64) DEFAULT NULL,
  `user_agent_hash` varchar(64) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token_id_index` (`token_id`),
  KEY `invoice_id_index` (`invoice_id`),
  KEY `event_type_index` (`event_type`),
  KEY `ip_hash_index` (`ip_hash`),
  KEY `created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. `mod_guestinvoice_settings`
Menyimpan konfigurasi modul (masa berlaku token, preferensi email, dll).

### 4. `mod_guestinvoice_ratelimits`
Pencatat frekuensi request untuk mekanisme *Sliding Window Rate Limiter*.

---

## 5. Mekanisme Keamanan & Token Kriptografis

Akses guest dibangun dengan standar keamanan perbankan tanpa bergantung pada sesi login klien:

1. **Generasi Entropi Tinggi (CSPRNG):**
   Token dihasilkan menggunakan generator acak berkekuatan kriptografis:
   ```php
   $rawBytes = random_bytes(32); // 256-bit entropy
   $rawToken = bin2hex($rawBytes); // 64-karakter string heksadesimal
   ```
2. **Penyimpanan Token Terenkripsi / Ter-Hash:**
   - Kolom `token_hash` menyimpan `hash('sha256', $rawToken)`.
   - Token mentah tidak dapat direkonstruksi dari database jika terjadi kebocoran read-only.
   - Kolom `raw_token` disimpan secara selektif untuk mendukung fitur tautan langsung pada dashboard admin dengan hak akses terkontrol.
3. **Pencarian Constant-Time:**
   Verifikasi hash menggunakan fungsi `hash_equals()` untuk mencegah serangan *Timing Attack*:
   ```php
   $computedHash = hash('sha256', $inputToken);
   // Lookup database dilakukan via token_hash
   ```
4. **Kebijakan Akses Berlapis (`AccessPolicy`):**
   Sebelum faktur ditampilkan atau pembayaran diproses, sistem memeriksa 4 kriteria:
   - Status Token = `Active`.
   - `expires_at` > `NOW()`.
   - Status Invoice WHMCS bukan `Cancelled` atau `Draft`.
   - Sisa tagihan (`balance`) > 0 untuk inisiasi pembayaran baru.

---

## 6. Data Sanitization & Privacy Protection (GDPR-Safe)

Saat invoice dibuka oleh guest, informasi identitas klien difilter untuk mencegah pengintaian (*information harvesting*):

| Kolom Data | Tampilan Publik (Guest View) | Internal Gateway Payload |
|---|---|---|
| **Nama Klien** | Ditampilkan (`Pras D Setiyo`) | Dikirim penuh ke gateway |
| **Alamat Email** | Dimasking (`p***o@domain.com`) | Dikirim asli (`unmasked`) |
| **Nomor Telepon** | Dimasking (`+62 812-****-5678`) | Dikirim asli (`unmasked`) |
| **Catatan Admin** | **Dihapus total** (tidak di-load) | Tidak dikirim |
| **Metode Pembayaran Tersimpan** | **Dihapus total** (CC hash/token) | Tidak diekspos |

---

## 7. Arsitektur Payment Gateway Adapter

Sistem adapter menggunakan pola **Adapter Pattern** berbasis `GatewayInterface` untuk memastikan modul dapat berkomunikasi dengan berbagai gateway nasional maupun internasional:

```
                  ┌──────────────────────┐
                  │   GatewayInterface   │
                  └──────────▲───────────┘
                             │ implements
                  ┌──────────┴───────────┐
                  │AbstractGatewayAdapter│
                  └──────────▲───────────┘
                             │
     ┌───────────────────────┼───────────────────────┐
     │                       │                       │
┌────┴────────────┐  ┌───────┴────────┐  ┌───────────┴──────────┐
│ MidtransAdapter │  │  XenditAdapter │  │  WhmcsNativeAdapter  │
└─────────────────┘  └────────────────┘  └──────────────────────┘
```

### Penanganan Kredensial Terenkripsi WHMCS
Modul gateway WHMCS resmi mengenkripsi kunci API (`server_key`, `secret_key`) di tabel `tblpaymentgateways`. `AbstractGatewayAdapter` memuat file resmi WHMCS:
```php
require_once ROOTDIR . '/includes/gatewayfunctions.php';
$params = getGatewayVariables($gatewayName, $invoiceId);
```
Fungsi ini secara otomatis mendekripsi kredensial gateway menggunakan kunci `cc_encryption_hash` resmi WHMCS.

### Logika Eksekusi Pembayaran Midtrans (`MidtransAdapter`)
1. **Direct Snap API Call (Prioritas 1):**
   - Mengambil Server Key terdekripsi (baik mode Sandbox berawalan `SB-Mid-server-...` maupun Live berawalan `Mid-server-...`).
   - Mengirim request langsung ke API Snap (`/snap/v1/transactions`).
   - Menerima `redirect_url` (`https://app.midtrans.com/snap/v2/vtweb/...`) dan melakukan **302 HTTP Redirect** langsung.
2. **Native Module Fallback (Prioritas 2):**
   - Jika panggilan langsung menemui kendala, adapter beralih ke `veritrans_link($params)`.
   - Menyaring output modul native: jika menghasilkan error (seperti 401 Unauthorized), error dicegat dan tidak ditampilkan sebagai form rusak.
   - Jika modul native menghasilkan form atau tautan redirect Snap VT-Web, tautan diekstrak dan browser langsung dialihkan ke portal pembayaran Midtrans.

---

## 8. Sistem Hooks WHMCS

Modul mendaftarkan hook otomatis pada file `hooks.php`:

| Nama Hook | Titik Eksekusi | Aksi yang Dilakukan |
|---|---|---|
| `InvoiceCreation` | Saat invoice baru dibuat oleh WHMCS / Cron | Menghasilkan token unik secara otomatis dan menyimpan log. |
| `InvoicePaid` | Saat invoice berstatus Paid (Lunas) | Mengubah status token menjadi `Expired` atau membatasi pembayaran ganda. |
| `InvoiceCancelled` | Saat invoice dibatalkan oleh admin | Mencabut hak akses token (`Revoked`). |
| `AdminInvoicesControlsOutput` | Pada panel kanan halaman edit invoice di Admin | Menampilkan widget berisi URL pembayaran guest yang siap disalin dengan tombol **Salin URL**. |
| `EmailPreSend` | Sebelum email tagihan dikirim ke klien | Menyuntikkan merge tag `{$guest_invoice_url}` dan `{$guest_payment_url}` ke template email. |

---

## 9. Siklus Alur Kerja (Workflows)

### Alur Pembayaran Tamu (Guest Checkout Flow)
```
1. Pelanggan membuka URL:
   https://domain.com/index.php?m=guestinvoice&token=xxxxxx
                                 │
2. Rate Limiting Check ──────────┴────► (Jika melebihi 30 req/min: Error 429)
                                 │
3. Validasi Token & AccessPolicy ─────► (Jika expired / invalid: Error 403/404)
                                 │
4. InvoiceService Sanitization ───────► Render Halaman Faktur (invoice.tpl)
                                 │
5. Pelanggan memilih Gateway & Klik Bayar
                                 │
6. PaymentService::processGuestPayment()
                                 │
7. Gateway Adapter Eksekusi:
   - Ambil kredensial terdekripsi
   - Bangun transaksi ke Provider API
   - Dapatkan Redirect URL
                                 │
8. HTTP 302 Redirect Instan ──────────► Portal Pembayaran Gateway (Midtrans/Xendit/dll)
                                 │
9. Pelanggan Menyelesaikan Transaksi
                                 │
10. Webhook / Callback Gateway ───────► WHMCS Memverifikasi & Menandai Invoice LUNAS
```

---

## 10. Audit Logging & Forensik

Setiap interaksi dengan invoice guest dicatat pada tabel `mod_guestinvoice_logs`:

- **Event Types:**
  - `token_generated`: Pembuatan token baru.
  - `invoice_viewed`: Pembukaan halaman tagihan oleh guest.
  - `payment_initiated`: Inisiasi pembayaran ke gateway.
  - `token_revoked`: Pencabutan token oleh admin.
  - `rate_limit_exceeded`: Deteksi anomali akses berulang.
- **Anonimisasi Privasi:**
  - Alamat IP dicatat dalam bentuk hash SHA-256 satu arah (`ip_hash`) untuk mematuhi regulasi privasi data.

---

## 11. Rate Limiting & Mitigasi Brute-Force

Untuk mencegah serangan tebak token atau serangan penolakan layanan (DDoS), `RateLimiter.php` menerapkan algoritma *Sliding Window*:

- **Batas IP:** Maksimal 30 request per menit per alamat IP.
- **Batas Token:** Maksimal 15 request per menit per token.
- **Respon Pelanggaran:** HTTP 429 Too Many Requests dengan tampilan pesan ramah pengguna.

---

## 12. Panduan Pemecahan Masalah (Troubleshooting)

### Kendala 1: `Midtrans Error (401): {"error":"Unauthorized"}`
- **Penyebab:**
  1. Server Key yang digunakan pada WHMCS adalah key Production (`Mid-server-...`), namun checkbox *Sandbox / Test Mode* di WHMCS dalam keadaan aktif (atau sebaliknya).
  2. Kunci Server Key terenkripsi belum didekripsi oleh helper resmi WHMCS.
- **Solusi:**
  - Pastikan file `lib/Gateway/AbstractGatewayAdapter.php` dan `lib/Gateway/MidtransAdapter.php` versi terbaru sudah terunggah.
  - Periksa menu **Setup > Payments > Payment Gateways > Manage Existing Gateways > Veritrans/Midtrans**:
    - Jika key berawalan `SB-` $\rightarrow$ Wajib centang *Sandbox / Test Mode*.
    - Jika key berawalan `Mid-` $\rightarrow$ Hapus centang *Sandbox / Test Mode*.

### Kendala 2: `Class 'GuestInvoice\Lib\Gateway\WhmcsNativeAdapter' not found`
- **Penyebab:** Perbedaan sensitivitas huruf besar/kecil (*case-sensitivity*) pada server Linux / cPanel.
- **Solusi:**
  - File autoloader di `guestinvoice.php` telah mendukung pencarian direktori ganda (`lib/Gateway/` dan `lib/gateway/`). Pastikan seluruh struktur folder berawalan huruf kapital sesuai namespace (`lib/Gateway/`).

### Kendala 3: URL Guest Invoice Kosong di Admin Invoice
- **Penyebab:** Invoice dibuat sebelum modul diaktifkan, sehingga belum memiliki record token di tabel `mod_guestinvoice_tokens`.
- **Solusi:**
  - Buka halaman detail invoice di admin WHMCS. Widget modul akan secara otomatis mendeteksi ketiadaan token dan langsung membuatkan token baru (*auto-generate on demand*).
