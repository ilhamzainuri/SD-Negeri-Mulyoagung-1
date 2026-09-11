Viewed auth.php:15-100
Viewed Dashboard.tsx:1-60
Viewed Dashboard.tsx:60-120

Untuk menerapkan fitur **Single Active Session** (ketika User 1 login di HP, sesi User 1 di Laptop otomatis logout/terkick), berikut adalah penjelasan lengkap mengenai **konsep, alur kerja (flow)**, dan **rencana implementasinya**:

---

### 1. Konsep Utama: *Session Token Invalidation*

Saat ini, login hanya menyimpan data profil di `localStorage`. Agar sistem tahu perangkat mana yang paling baru login, kita menambahkan konsep **`session_token` (Token Sesi Unik)**:

1. Setiap kali user melakukan **Login**, backend men-generate kode unik baru (misalnya `abc123xxx`) dan menyimpannya di database pada kolom `session_token` user tersebut.
2. Token ini juga dikirim dan disimpan di perangkat yang baru login (HP).
3. Database **hanya menyimpan 1 token aktif terakhir**.
4. Perangkat lama (Laptop) yang masih memegang token lama (`xyz789old`) akan dianggap **tidak valid lagi** saat diverifikasi ke server.

---

### 2. Diagram Alur Kerja (Workflow)

```
[Laptop (Sesi Lama)]              [Server / Database]             [HP (Sesi Baru)]
       |                                  |                              |
 (1) Sedang aktif                         |                              |
 (token: "TOKEN-A")                       |                              |
       |                                  |                              |
       |                                  | <--- (2) User 1 Login -------+
       |                                  |                              |
       |                         (3) Generate "TOKEN-B"                  |
       |                         Update DB: session_token="TOKEN-B"      |
       |                                  |                              |
       |                                  +---- (4) Login Sukses ------->+
       |                                  |     (Simpan "TOKEN-B")       |
       |                                  |                              |
 (5) Cek Sesi (Heartbeat / API Request) ->|                              |
     Kirim "TOKEN-A"                      |                              |
       |                                  |                              |
       |                         (6) Cek DB:                             |
       |                             "TOKEN-A" != "TOKEN-B" (MISMATCH)   |
       |                                  |                              |
 <-----+-- (7) Response 401 / Kicked -----+                              |
       |   "Akun login di perangkat lain" |                              |
       |                                  |                              |
 (8) Clear localStorage                   |                              |
     Muncul Popup Peringatan              |                              |
     Redirect ke Login                    |                              |
```

---

### 3. Detail Rencana Teknis (Implementation Plan)

#### A. Database (Backend)
- Menambahkan kolom `session_token` (VARCHAR 64 / VARCHAR 128) pada tabel `users`.
  *(Dibuat otomatis via script PHP `foto_ensure_column` atau `ALTER TABLE IF NOT EXISTS` agar aman).*

#### B. Endpoint `backend/API/auth.php`
1. **Saat `action=login`**:
   - Generate token acak baru: `$sessionToken = bin2hex(random_bytes(32));`
   - Update database: `UPDATE users SET session_token = ? WHERE id = ?`
   - Kembalikan `session_token` di dalam response JSON `user`.
2. **Tambah `action=check_session`**:
   - Menerima `user_id` dan `session_token`.
   - Memeriksa apakah `session_token` yang dikirim cocok dengan yang ada di database.
   - Jika cocok $\rightarrow$ `{ status: "success", valid: true }`
   - Jika tidak cocok $\rightarrow$ `{ status: "invalid_session", message: "Akun Anda telah login di perangkat lain." }`
3. **Saat `action=logout`**:
   - Kosongkan `session_token` di database: `UPDATE users SET session_token = NULL WHERE id = ?`

#### C. Frontend CMS (React)
1. **Simpan Token**: Simpan `session_token` di dalam `user` state & `localStorage['cms_user']`.
2. **Pengecekan Otomatis (Heartbeat Hook / Interval)**:
   - Membuat hook/timer di [src/CMS/Dashboard.tsx](file:///c:/xampp/htdocs/SD-Negeri-Mulyoagung-1/src/CMS/Dashboard.tsx) yang mengecek status sesi ke backend setiap **15–30 detik** (hanya saat tab CMS aktif).
   - Juga mengecek saat user kembali membuka tab browser (`window.addEventListener('visibilitychange')`).
3. **Pengecekan di Setiap Aksi CRUD**:
   - Jika ada request CRUD yang merespons `401 Unauthorized` / `invalid_session`, sistem langsung mendeteksi sesi terputus.
4. **Trigger Logout & Notifikasi**:
   - Jika `invalid_session` terdeteksi di laptop:
     - Tampilkan modal/alert: *"Sesi Anda telah berakhir karena akun ini telah login di perangkat lain."*
     - Hapus `localStorage['cms_user']`.
     - Alihkan tampilan ke form Login.

---

### Keuntungan Pendekatan Ini:
- **Real-time & Ringan**: Polling interval 15–30 detik hanya mengirim 1 query indexed sangat cepat ke server (beban CPU < 0.01%).
- **Aman**: Mencegah multi-login tanpa otorisasi.
- **User-Friendly**: Pengguna di laptop langsung mendapat notifikasi yang jelas mengapa mereka ter-logout.

Apakah alur dan rencana di atas sudah sesuai dengan yang Anda inginkan? Jika sudah oke, silakan konfirmasi agar saya langsung terapkan kodenya!