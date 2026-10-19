# Guest Invoice & Payment — WHMCS Addon Module

![WHMCS Compatibility](https://img.shields.io/badge/WHMCS-8.x%20(8.12%2B)-blue.svg)
![PHP Compatibility](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg)
![License](https://img.shields.io/badge/License-Proprietary%20%2F%20Commercial-green.svg)
![Tests](https://img.shields.io/badge/Automated%20Tests-47%2F47%20Passed-brightgreen.svg)

Modul resmi WHMCS Addon untuk memberikan akses pelanggan melihat detail invoice dan melakukan pembayaran online secara langsung **tanpa harus login ke portal klien**, menggunakan tautan rahasia berbasis token kriptografis berkecepatan tinggi dan berkeamanan bank.

---

## 🌟 Fitur Utama

### 1. Pengalaman Pelanggan (Guest Experience)
- **Akses Langsung Tanpa Autentikasi:** Pelanggan cukup membuka tautan unik yang dikirimkan via email atau WhatsApp/chat admin.
- **Tampilan Modern & Responsif:** Antarmuka responsif ramah mobile dengan tipografi bersih, badge status real-time, dan tata letak print-ready (Cetak PDF langsung dari browser).
- **Pembayaran Online Multi-Gateway:** Mendukung gateway pembayaran Indonesia terkemuka (**Midtrans Snap, Xendit, Tripay, DOKU, Duitku**) serta **Transfer Bank Manual** dan gateway bawaan WHMCS.
- **Perlindungan Privasi:** Email dan nomor telepon pelanggan disamarkan secara otomatis (`bu*i@...`) pada tampilan publik untuk mencegah pengintaian pihak ketiga.
- **Status Pembayaran Terverifikasi:** Hasil pembayaran selalu diperiksa langsung dari database WHMCS, tidak pernah mengandalkan manipulasi query string browser.

### 2. Manajemen Admin WHMCS (Admin Controls)
- **Widget Integrasi Halaman Invoice Admin:** Tombol pembuatan dan penyalinan tautan instan langsung di layar kelola tagihan admin (`invoices.php`).
- **Generator Tautan Tunggal & Massal (Bulk):** Buat tautan guest untuk puluhan invoice sekaligus dengan pelaporan status berhasil/gagal per item.
- **Otomasi Terpadu:** Pengaturan pembuatan dan pengiriman tautan otomatis saat tagihan baru terbit melalui hook `InvoiceCreated`.
- **Manajemen Siklus Token:** Atur masa berlaku (7, 14, 30, 60, 90 hari), perpanjang masa aktif, atau cabut akses (Revoke) kapan saja.
- **Audit Log Keamanan & Forensik:** Pencatatan setiap akses pelanggan, kegagalan rate limit, dan webhook gateway dengan hashing IP dan User-Agent (GDPR & PDP compliant).

---

## 🔒 Arsitektur Keamanan

- **Zero Raw Token Storage:** Database hanya menyimpan hash SHA-256 (`token_hash`) dengan indeks unik. Token mentah hanya dihasilkan di memori saat pembuatan.
- **CSPRNG 256-bit:** Dihasilkan menggunakan `random_bytes(32)` menghasilkan 64 karakter heksadesimal URL-safe.
- **Anti-Brute Force & Rate Limiting:** Pembatasan percobaan akses IP untuk mencegah pencarian acak token dan enumerasi invoice.
- **Strict Access Policy:** Invoice yang telah berstatus *Paid* dikunci menjadi *read-only*; transaksi baru ditolak otomatis.
- **Security Headers:** Halaman publik menyertakan header keamanan ketat:
  - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
  - `Referrer-Policy: no-referrer`
  - `X-Robots-Tag: noindex, nofollow, noarchive`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`

---

## 📂 Struktur Modul

```text
guestinvoice/
├── guestinvoice.php                    # Entrypoint WHMCS Addon Module
├── hooks.php                           # Integrasi hook InvoiceCreated, Controls, & Cron
├── lib/
│   ├── TokenService.php                # Pembuatan, hash SHA-256, dan validasi token
│   ├── InvoiceService.php              # Query aman data invoice, kurs, dan privasi
│   ├── PaymentService.php              # Alur checkout guest & rekonsiliasi transaksi
│   ├── AccessPolicy.php                # Kebijakan izin view, pay, dan download
│   ├── RateLimiter.php                 # Pencegahan brute force dan pembatasan IP
│   ├── AuditLogger.php                 # Pencatatan log aman (IP salt-hashed)
│   ├── MailService.php                 # Pengiriman email melalui sistem email WHMCS
│   └── Gateway/                        # Adapter payment gateway
│       ├── GatewayInterface.php        # Kontrak standar adapter gateway
│       ├── AbstractGatewayAdapter.php  # Base class konfigurasi & helper cURL
│       ├── BankTransferAdapter.php     # Instruksi transfer bank & rekening
│       ├── MidtransAdapter.php         # Midtrans Snap & verifikasi SHA-512
│       ├── XenditAdapter.php           # Xendit Invoice & callback token
│       ├── TripayAdapter.php           # Tripay Closed Payment (QRIS/VA)
│       ├── DokuAdapter.php             # DOKU Checkout & digest signature
│       ├── DuitkuAdapter.php           # Duitku Inquiry & MD5 callback verification
│       └── WhmcsNativeAdapter.php      # Fallback alur resmi WHMCS gateway
├── controllers/
│   ├── AdminController.php             # Antarmuka dashboard, bulk, log, & pengaturan admin
│   ├── GuestInvoiceController.php      # Routing publik & proteksi halaman invoice
│   ├── GuestPaymentController.php      # Inisiasi proses pembayaran gateway
│   └── PaymentResultController.php     # Tampilan status pembayaran terverifikasi
├── templates/
│   ├── invoice.tpl                     # Smarty template utama invoice publik
│   ├── payment.tpl                     # Smarty template instruksi pembayaran & form
│   ├── payment-result.tpl              # Smarty template hasil transaksi lunas/pending
│   └── errors.tpl                      # Smarty template pesan aman error publik
├── assets/
│   ├── css/ (guest.css, admin.css)     # Desain UI modern dan print-ready
│   └── js/ (guest.js, admin.js)        # Interaksi copy link, selector radio, toast
├── lang/
│   ├── english.php                     # Terjemahan Bahasa Inggris
│   └── indonesian.php                  # Terjemahan Bahasa Indonesia
└── migrations/
    └── MigrationManager.php            # Migrasi skema database aman
```

---

## 🚀 Panduan Instalasi

Clone repositori ini langsung ke dalam direktori `modules/addons/guestinvoice` instalasi WHMCS Anda:

```bash
cd /path/to/whmcs/modules/addons
git clone https://github.com/raw-dani/Guest-Invoice.git guestinvoice
```

Atau unduh berkas ZIP dan ekstrak ke `WHMCS_ROOT/modules/addons/guestinvoice`.

1. Masuk ke **WHMCS Admin Area** > **System Settings** > **Addon Modules**.
2. Temukan **Guest Invoice & Payment**, klik **Activate**.
3. Klik **Configure**, berikan centang akses ke grup administrator Anda (misal: *Full Administrator*), lalu klik **Save Changes**.
4. Buka modul melalui menu **Addons** > **Guest Invoice & Payment** untuk mengatur preferensi masa berlaku dan otomasi.

---

## 📚 Dokumentasi Lanjutan
- **[Dokumentasi Teknis & Arsitektur (TECHNICAL.md)](TECHNICAL.md)**: Arsitektur OOP, skema database, kriptografi token CSPRNG 256-bit, adapter payment gateway, sistem hooks, dan audit log.
- **[Panduan Administrator (ADMIN_GUIDE.md)](ADMIN_GUIDE.md)**: Petunjuk konfigurasi panel admin, pembuatan tautan, dan pelacakan pembayaran.
- **[Kompatibilitas Payment Gateway (PAYMENT_GATEWAY_COMPATIBILITY.md)](PAYMENT_GATEWAY_COMPATIBILITY.md)**: Panduan konfigurasi gateway Midtrans, Xendit, Tripay, DOKU, Duitku, dan transfer bank.
- **[Keamanan & GDPR (SECURITY.md)](SECURITY.md)**: Spesifikasi sanitasi data, perlindungan rate limiting, dan privasi klien.
