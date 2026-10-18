# Catatan Rilis (Changelog) — Guest Invoice & Payment

Format berkas ini mengacu pada [Keep a Changelog](https://keepachangelog.com/id/1.0.0/), dan proyek ini mematuhi [Semantic Versioning](https://semver.org/lang/id/).

---

## [1.0.0] — 2026-10-18

### Ditambahkan (Added)
- **Arsitektur Inti Addon:**
  - Entrypoint modul resmi WHMCS `guestinvoice.php` dengan fungsi `guestinvoice_config`, `guestinvoice_activate`, `guestinvoice_deactivate`, `guestinvoice_upgrade`, `guestinvoice_output`, dan `guestinvoice_clientarea`.
  - Dukungan perutean publik WHMCS tanpa sesi login (`requirelogin => false`).
  - Mekanisme autoloader kelas PSR-4 otomatis untuk namespace `GuestInvoice\`.
- **Kriptografi & Manajemen Token:**
  - Pembuatan token kriptografis 256-bit URL-safe menggunakan `random_bytes(32)`.
  - Penyimpanan token berbasis hash satu arah SHA-256 (`token_hash`) dengan indeks unik; token mentah tidak pernah disimpan di basis data.
  - Masa berlaku token yang dapat diatur (7, 14, 30, 60, 90 hari) dengan deteksi kedaluwarsa otomatis.
  - Fitur pencabutan token seketika (*instant revoke*) oleh administrator.
- **Payment Gateway Adapters:**
  - `BankTransferAdapter`: Menampilkan rincian rekening tujuan, nama bank, pemilik, jumlah pembayaran, dan kode referensi unik tagihan dengan tombol salin otomatis.
  - `MidtransAdapter`: Integrasi Midtrans Snap API (QRIS, VA, E-Wallet) dengan verifikasi tanda tangan SHA-512.
  - `XenditAdapter`: Integrasi Xendit Invoice API dengan verifikasi callback token.
  - `TripayAdapter`: Integrasi Tripay Closed Payment dengan verifikasi tanda tangan HMAC-SHA256.
  - `DokuAdapter`: Integrasi DOKU Checkout dengan HMAC-SHA256 digest signature.
  - `DuitkuAdapter`: Integrasi Duitku Pop/Hosted Checkout dengan verifikasi tanda tangan MD5.
  - `WhmcsNativeAdapter`: Fallback pembayaran untuk gateway standar WHMCS.
- **Keamanan & Kebijakan Akses:**
  - `RateLimiter`: Pembatasan IP dan mitigasi serangan brute-force dengan penguncian sementara (*lockout*).
  - `AccessPolicy`: Penegakan aturan tagihan *Paid* terkunci menjadi *read-only* (pembayaran baru ditolak).
  - Header keamanan ketat HTTP (`Cache-Control: no-store`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`).
  - Penyamaran otomatis (*masking*) email dan nomor telepon klien pada tampilan publik.
  - `AuditLogger`: Pencatatan log keamanan forensik dengan hashing IP (*Salted SHA-256*).
  - Pembersihan log otomatis (*log pruning*) terintegrasi dengan cron harian WHMCS.
- **Antarmuka Pengguna (UI/UX):**
  - Desain Smarty responsif modern (`invoice.tpl`, `payment.tpl`, `payment-result.tpl`, `errors.tpl`).
  - Tampilan siap cetak (*print-ready CSS*) untuk cetak PDF browser (Ctrl+P).
  - Pengunduh PDF resmi tagihan terintegrasi (opsional sesuai izin admin).
  - Dashboard Admin lengkap dengan metrik tautan aktif, generator massal (*bulk*), log audit, dan pengaturan.
- **Integrasi Hook WHMCS:**
  - `InvoiceCreated`: Pembuatan dan pengiriman tautan otomatis saat tagihan baru terbit.
  - `AdminInvoicesControlsOutput`: Widget tombol pembuatan dan penyalinan tautan langsung di halaman kelola tagihan admin.
  - `InvoiceCancelled` & `InvoiceDeleted`: Pencabutan token otomatis saat invoice dibatalkan/dihapus.
  - `DailyCronJob`: Pemeliharaan data kadaluwarsa harian.
- **Pengujian & QA:**
  - 44 automated test assertions mencakup unit, integrasi gateway, idempotency, dan end-to-end flow dengan 100% kelulusan.
