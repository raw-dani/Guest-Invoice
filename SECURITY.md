# Kebijakan & Model Keamanan — Modul WHMCS Guest Invoice & Payment

Dokumen ini mendokumentasikan arsitektur keamanan informasi, kontrol akses, mitigasi kerentanan, dan kebijakan pelaporan keamanan untuk modul **Guest Invoice & Payment** (`guestinvoice`).

---

## 1. Prinsip Keamanan Utama

1. **Prinsip Hak Akses Terbatas (Least Privilege):**
   Tautan guest hanya memberikan hak akses untuk membaca satu dokumen tagihan tertentu yang dituju oleh token. Token **tidak dapat** digunakan untuk membaca profil klien, tiket bantuan, paket hosting, domain, atau tagihan lain.
2. **Kriptografi Standar Industri:**
   - Dihasilkan via CSPRNG PHP `random_bytes(32)`.
   - Panjang 256-bit (64 karakter heksadesimal).
   - Di-hash menggunakan SHA-256 sebelum disimpan ke basis data.
   - Token asli/mentah tidak pernah disimpan di database atau dicatat pada berkas log.
3. **Pemisahan Otoritas Pembayaran:**
   Status pembayaran tagihan bersumber tunggal pada data terverifikasi WHMCS. Manipulasi query string browser (misal: `&status=paid` atau `&success=1`) tidak memiliki efek apa pun terhadap status pembayaran.

---

## 2. Mitigasi Vektor Serangan

### A. IDOR (Insecure Direct Object Reference)
- **Vektor:** Penyerang mencoba mengganti parameter `invoiceid=101` menjadi `invoiceid=102` untuk melihat tagihan orang lain.
- **Mitigasi:** Modul publik **tidak pernah** menerima parameter `invoiceid` sebagai kunci akses. Akses hanya didasarkan pada token 64 karakter acak. Pada sisi backend, `AccessPolicy::canViewInvoice` memverifikasi bahwa token hash secara ketat terikat dengan invoice ID terkait.

### B. Brute-Force & Token Enumeration
- **Vektor:** Penyerang mengirimkan jutaan request acak untuk menebak token yang valid.
- **Mitigasi:** 
  1. Ruang kunci token adalah $16^{64} = 2^{256}$, mustahil ditebak secara acak dalam waktu realistis.
  2. Modul menerapkan `RateLimiter`: maksimal 15 percobaan gagal per IP per menit sebelum IP dikunci sementara selama 15 menit.
  3. Respon untuk token yang salah, kedaluwarsa, atau dicabut menghasilkan pesan error umum yang sama, mencegah serangan *timing attack* atau enumerasi status.

### C. Injeksi SQL (SQL Injection)
- **Vektor:** Manipulasi input token atau parameter admin untuk menjalankan kueri SQL berbahaya.
- **Mitigasi:** Seluruh interaksi basis data menggunakan `WHMCS\Database\Capsule` (Laravel Query Builder) dengan *PDO Prepared Statements* dan parameter binding otomatis. Tidak ada kueri SQL mentah yang dirangkai secara manual.

### D. Cross-Site Scripting (XSS)
- **Vektor:** Injeksi skrip jahat melalui nama item tagihan atau profil perusahaan.
- **Mitigasi:** Seluruh variabel template pada antarmuka publik dan admin di-escape secara ketat menggunakan modifier Smarty `|escape` dan `htmlspecialchars`.

### E. Cross-Site Request Forgery (CSRF)
- **Vektor:** Menjebak admin yang sedang login untuk membuat atau mencabut token tanpa disadari.
- **Mitigasi:** Seluruh aksi POST pada Admin Controller memvalidasi token CSRF WHMCS resmi melalui fungsi `check_token()`.

### F. Perlindungan Cache & Kebocoran Referrer
Halaman publik menyertakan header keamanan HTTP:
```http
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Referrer-Policy: no-referrer
X-Robots-Tag: noindex, nofollow, noarchive
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
```
Header ini memastikan tagihan tidak di-cache oleh proxy/browser bersama, URL token tidak bocor melalui header Referer ke pihak ketiga, dan mesin pencari (Googlebot, dll.) dilarang mengindeks halaman tagihan publik.

### G. Privasi Data Klien (GDPR & UU PDP)
- Email klien disamarkan pada tampilan umum: `budi.santoso@example.com` $\rightarrow$ `bu*i@example.com`.
- Nomor telepon klien disamarkan: `081234567890` $\rightarrow$ `081****890`.
- Pada tabel audit log, alamat IP dan User-Agent pengunjung dienkripsi menggunakan *Salted SHA-256 Hash*. Alamat IP asli tidak pernah disimpan dalam format teks polos (*plain text*).

---

## 3. Prosedur Pelaporan Kerentanan

Jika Anda menemukan potensi celah keamanan pada modul ini:
1. Hubungi tim teknis melalui email: `security@domainanda.com`.
2. Jangan mempublikasikan kerentanan di forum umum sebelum patch/perbaikan dirilis (*Coordinated Disclosure*).
3. Berikan rincian langkah reproduksi (Proof-of-Concept) agar tim dapat memvalidasi dan merilis perbaikan segera.
