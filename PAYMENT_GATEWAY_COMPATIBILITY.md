# Kompatibilitas Payment Gateway — Modul WHMCS Guest Invoice & Payment

Dokumen ini menjelaskan status dukungan teknis, mode transaksi, dan batasan operasional untuk masing-masing payment gateway yang diintegrasikan.

---

## 1. Matriks Kompatibilitas

| Gateway | Kategori | Alur Guest | Status Validasi | Catatan & Batasan |
|---|---|---|---|---|
| **Transfer Bank Manual** (`banktransfer`) | Manual / Offline | Full (Instruksi Lengkap) | ✅ **100% Teruji** | Menampilkan nama bank, nomor rekening, pemilik, dan kode referensi unik tagihan dengan tombol salin otomatis. |
| **Midtrans Snap** (`midtrans`) | Hosted Checkout / API | Full Redirect / Snap | ✅ **100% Teruji** | Mendukung QRIS (GoPay/ShopeePay/dll), VA Semua Bank, Indomaret/Alfamart. Verifikasi webhook SHA-512 signature. |
| **Xendit Invoice** (`xendit`) | Hosted Checkout / API | Full Redirect | ✅ **100% Teruji** | Mendukung Virtual Account, QRIS, E-Wallet (OVO/Dana), Retail Outlet. Verifikasi callback token header. |
| **Tripay** (`tripay`) | Closed Payment / API | Full Redirect | ✅ **100% Teruji** | Mendukung QRIS Realtime & VA Bank lokal. Verifikasi tanda tangan HMAC-SHA256. |
| **DOKU Checkout** (`doku`) | Hosted Checkout / API | Full Redirect | ✅ **100% Teruji** | Mendukung DOKU Hosted Checkout & QRIS. Verifikasi digest HMAC-SHA256 signature. |
| **Duitku** (`duitku` / `duitkupop`) | Hosted Checkout / API | Full Redirect | ✅ **100% Teruji** | Mendukung Virtual Account Semua Bank, QRIS, E-Wallet (OVO, DANA, LinkAja, ShopeePay), Gerai Retail. Verifikasi MD5 signature. |
| **WHMCS Native Hosted** (PayPal / Stripe Checkout / dll) | Hosted Form / Redirect | Didukung | ✅ **Teruji via Adapter** | Menggunakan form submit atau fallback URL tagihan resmi WHMCS. |
| **Direct Merchant Elements** (Simpan Kartu / Vault) | Direct Form / Merchant API | Terbatas | ⚠️ **Fallback Aman** | Gateway yang mewajibkan token kartu kredit tersimpan pada profil akun diarahkan untuk login demi keamanan PCI-DSS. |

---

## 2. Rincian Teknis Integrasi

### A. Midtrans Snap
- **Dokumentasi Resmi:** https://docs.midtrans.com/
- **Arsitektur:** Menggunakan Snap API (`/snap/v1/transactions`) untuk membuat sesi transaksi sementara.
- **Data yang dikirimkan:** `order_id` (format: `INV-{id}-{timestamp}`), `gross_amount`, `customer_details` (nama & email pelanggan).
- **Callback / Webhook:**
  - Verifikasi tanda tangan:
    $$\text{Signature} = \text{SHA-512}(\text{order\_id} + \text{status\_code} + \text{gross\_amount} + \text{ServerKey})$$
  - Status pemetaan:
    - `settlement` & `capture (accept)` $\rightarrow$ **Paid**
    - `pending` $\rightarrow$ **Pending**
    - `cancel`, `deny`, `expire` $\rightarrow$ **Failed**
- **Idempotency:** Transaksi yang sudah tercatat di `tblaccounts` tidak akan diduplikasi bila Midtrans mengirimkan webhook berulang.

### B. Xendit Invoice
- **Dokumentasi Resmi:** https://developers.xendit.co/
- **Arsitektur:** Menggunakan Xendit Invoice API v2 (`/v2/invoices`).
- **Verifikasi Webhook:** Header `x-callback-token` dicocokkan dengan token rahasia webhook yang dikonfigurasi di WHMCS.
- **Status pemetaan:** `PAID` / `SETTLED` $\rightarrow$ **Paid**, `EXPIRED` $\rightarrow$ **Expired**.

### C. Tripay
- **Dokumentasi Resmi:** https://tripay.co.id/developer
- **Arsitektur:** Closed Payment API (`/api/transaction/create`).
- **Verifikasi Webhook:** Header `X-Callback-Signature` divalidasi menggunakan algoritma HMAC-SHA256 terhadap raw body payload dengan `Private Key`.

### D. Duitku
- **Dokumentasi Resmi:** https://docs.duitku.com/
- **Arsitektur:** Menggunakan Duitku Inquiry API v2 (`/webapi/api/merchant/v2/inquiry`).
- **Pembuatan Transaksi:**
  - Signature Request: `MD5(merchantCode + merchantOrderId + paymentAmount + apiKey)`
  - URL Redirect: Sesi pembayaran otomatis dialihkan ke `paymentUrl` Duitku (Pop / Hosted).
- **Verifikasi Webhook / Callback:**
  - Signature Callback:
    $$\text{Signature} = \text{MD5}(\text{merchantCode} + \text{amount} + \text{merchantOrderId} + \text{apiKey})$$
  - Status pemetaan: `resultCode == '00'` $\rightarrow$ **Paid**, kode lain $\rightarrow$ **Failed**.
- **Idempotency:** Menjamin transaksi yang berulang tidak menyebabkan duplikasi pembayaran pada invoice.

---

## 3. Penanganan Transaksi & Idempotency

1. **Anti-Duplikasi (Idempotency):**
   Setiap webhook yang masuk memeriksa keberadaan `transid` dan `invoiceid` pada tabel transaksi `tblaccounts`. Jika sudah pernah tercatat, modul merespons kode HTTP 200 dengan status transaksi telah dicatat sebelumnya tanpa menambah saldo ganda.
2. **Pengecekan Nominal & Mata Uang:**
   Nominal yang masuk dicocokkan dengan sisa tagihan aktual di database WHMCS sebelum menandai tagihan menjadi lunas (*Paid*).
3. **Pencatatan Pembayaran:**
   Menggunakan API resmi WHMCS `localAPI('AddInvoicePayment')` atau insert standar `tblaccounts` sehingga seluruh hook aktivasi layanan otomatis WHMCS (`InvoicePaid`, `AfterModuleCreate`, dll) tetap terpicu secara normal.
