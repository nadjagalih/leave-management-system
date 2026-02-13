# Sistem Manajemen Cuti Karyawan

Ini adalah RESTful API untuk sistem manajemen cuti karyawan yang dibangun dengan Laravel.

## Fitur

- Autentikasi pengguna (Register, Login, Logout) menggunakan Laravel Sanctum.
- Autentikasi menggunakan OAuth dengan GitHub (bisa untuk login dan register).
- Manajemen peran (Admin & Employee).
- Pengajuan cuti oleh karyawan dengan lampiran.
- Persetujuan atau penolakan cuti oleh admin.
- Kuota cuti tahunan untuk setiap karyawan.
- Validasi untuk memastikan karyawan hanya bisa memiliki satu pengajuan `pending`.

## Prasyarat

- PHP >= 8.2
- Composer
- Node.js & NPM
- Database (MySQL direkomendasikan)
- Git

## Panduan Instalasi

1.  **Clone repository:**
    ```bash
    git clone https://github.com/username/leave-management-system.git
    cd leave-management-system
    ```

2.  **Install dependensi PHP:**
    ```bash
    composer install
    ```

3.  **Install dependensi JavaScript:**
    ```bash
    npm install
    npm run build
    ```

4.  **Buat file `.env`:**
    Salin file `.env.example` menjadi `.env`.
    ```bash
    cp .env.example .env
    ```

5.  **Generate kunci aplikasi:**
    ```bash
    php artisan key:generate
    ```

6.  **Buat symbolic link untuk storage:**
    Perintah ini penting agar file lampiran yang di-upload bisa diakses publik.
    ```bash
    php artisan storage:link
    ```

## Konfigurasi `.env`

Buka file `.env` dan atur konfigurasi database Anda:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=leave_management
DB_USERNAME=root
DB_PASSWORD=
```

Pastikan Anda sudah membuat database dengan nama `leave_management` (atau nama lain yang Anda tentukan).

Untuk fitur OAuth, tambahkan kredensial Anda:

```env
# GitHub OAuth
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=http://localhost:8000/api/auth/github/callback

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

Detail langkah-langkah untuk mendapatkan kredensial GitHub:
...
(instruksi yang ada)
...

Detail langkah-langkah untuk mendapatkan kredensial Google:

1.  **Buat Proyek & Kredensial di Google Cloud Console**:
    *   Buka [Google Cloud Console](https://console.cloud.google.com/) dan buat proyek baru jika belum ada.
    *   Navigasi ke `APIs & Services` > `Credentials`.
    *   Klik `Create Credentials` > `OAuth client ID`.
    *   Pilih `Web application` sebagai tipe aplikasi.
    *   Pada bagian `Authorized redirect URIs`, klik `ADD URI` dan masukkan: `http://localhost:8000/api/auth/google/callback`.
    *   Klik `Create` dan salin `Client ID` serta `Client Secret` ke file `.env` Anda.
    *   Pastikan untuk mengaktifkan **Google People API** di library API proyek Anda.**Buat Aplikasi OAuth GitHub**:
    *   Buka pengaturan akun GitHub Anda.
    *   Navigasi ke `Developer settings` > `OAuth Apps` > `New OAuth App`.
    *   **Application name**: `Sistem Manajemen Cuti` (atau nama lain).
    *   **Homepage URL**: `http://localhost:8000`.
    *   **Authorization callback URL**: `http://localhost:8000/api/auth/github/callback`.
    *   Daftarkan aplikasi dan salin `Client ID` serta `Client Secret` ke file `.env` Anda.

## Migrasi dan Seeding Database

Jalankan perintah berikut untuk membuat tabel database dan mengisi data awal (roles dan user default):

```bash
php artisan migrate:fresh --seed
```

Perintah ini akan menghapus semua tabel lama dan menjalankan seeder. Seeder akan membuat data awal berikut:

-   **Peran**: `Admin` dan `Employee`.
-   **Pengguna Admin**:
    -   Email: `admin@example.com`
    -   Password: `password`
-   **Pengguna Employee**:
    -   Email: `employee@example.com`
    -   Password: `password`

## Menjalankan Server

Untuk menjalankan server pengembangan lokal, gunakan perintah:

```bash
php artisan serve
```

API akan tersedia di `http://localhost:8000`.

---

## Dokumentasi Endpoint API

### Tata Cara Autentikasi

Sebagian besar endpoint dalam API ini memerlukan autentikasi. Prosesnya adalah sebagai berikut:

1.  **Dapatkan Token**: Lakukan permintaan ke endpoint `POST /api/login` atau `POST /api/register`. Jika berhasil, respons JSON akan berisi `access_token`.
    ```json
    {
        "access_token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
        "token_type": "Bearer"
    }
    ```

2.  **Gunakan Token**: Untuk setiap permintaan ke endpoint yang terproteksi, Anda harus menyertakan dua header:
    -   `Accept: application/json`
    -   `Authorization: Bearer <your_auth_token>`

    Ganti `<your_auth_token>` dengan `access_token` yang Anda dapatkan dari langkah 1.

    **Contoh Penggunaan Header:**
    ```
    Accept: application/json
    Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...
    ```

---

### 1. Autentikasi & Pengguna

#### **POST** `/api/register`
Registrasi pengguna baru. Pengguna baru akan otomatis mendapatkan peran "Employee" dan kuota cuti 12.

**Body (raw JSON):**
```json
{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### **POST** `/api/login`
Login untuk mendapatkan token autentikasi.

**Body (raw JSON):**
```json
{
    "email": "employee@example.com",
    "password": "password"
}
```

#### **POST** `/api/logout`
Mencabut token autentikasi pengguna.
*(Membutuhkan Autentikasi)*

#### **GET** `/api/user`
Mendapatkan detail pengguna yang sedang login.
*(Membutuhkan Autentikasi)*

### 2. Autentikasi OAuth (Dinamis)

Sistem mendukung autentikasi OAuth dengan provider yang didukung oleh Laravel Socialite (contoh: GitHub, Google). Cukup ganti `{provider}` dengan nama provider yang diinginkan.

Jika email dari provider belum terdaftar, sistem akan membuat pengguna baru. Jika sudah terdaftar, sistem akan menautkan akun provider baru ke pengguna yang sudah ada.

#### **GET** `/api/auth/{provider}/redirect`
Mengalihkan pengguna ke halaman otorisasi provider.
*   Contoh GitHub: `/api/auth/github/redirect`
*   Contoh Google: `/api/auth/google/redirect`

#### **GET** `/api/auth/{provider}/callback`
Callback setelah otorisasi berhasil.
*   Contoh GitHub: `/api/auth/github/callback`
*   Contoh Google: `/api/auth/google/callback`

### 3. Manajemen Profil Pengguna

Endpoint ini memungkinkan pengguna yang terautentikasi untuk mengelola profil mereka sendiri.

#### **PUT** `/api/profile`
Memperbarui profil pengguna (nama, email, password).
*   **Membutuhkan Autentikasi**: Ya
*   **Body (raw/json)**:
    ```json
    {
        "name": "Nama Baru",
        "email": "emailbaru@example.com",
        "password": "password_baru_yang_kuat",
        "password_confirmation": "password_baru_yang_kuat"
    }
    ```
    *Catatan: Semua field bersifat opsional. Hanya kirim field yang ingin Anda ubah.*

#### **DELETE** `/api/profile`
Menghapus akun pengguna secara permanen.
*   **Membutuhkan Autentikasi**: Ya

### 4. Manajemen Cuti (Untuk Employee)

Endpoint ini hanya dapat diakses oleh pengguna dengan peran `Employee`.

#### **GET** `/api/leave-requests`
Melihat riwayat semua pengajuan cuti milik pengguna yang sedang login.
*(Membutuhkan Autentikasi sebagai Employee)*

#### **GET** `/api/leave-requests/{id}`
Melihat detail satu pengajuan cuti spesifik milik pengguna yang sedang login. Ganti `{id}` dengan ID dari `leave_request`.
*(Membutuhkan Autentikasi sebagai Employee)*

#### **POST** `/api/leave-requests`
Membuat pengajuan cuti baru.
*(Membutuhkan Autentikasi sebagai Employee)*

**Body (form-data):**
-   `start_date`: `2024-12-20` (tipe: text, format: Y-m-d)
-   `end_date`: `2024-12-21` (tipe: text, format: Y-m-d)
-   `reason`: `Cuti akhir tahun.` (tipe: text)
-   `attachment`: (tipe: file, opsional, format: jpg, jpeg, png, pdf, max: 2MB)

#### **POST** `/api/leave-requests/{id}/withdraw`
Membatalkan pengajuan cuti yang masih berstatus `pending`. Ganti `{id}` dengan ID dari `leave_request`.
*(Membutuhkan Autentikasi sebagai Employee)*

### 5. Manajemen Cuti (Untuk Admin)

Endpoint ini hanya dapat diakses oleh pengguna dengan peran `Admin` dan memiliki prefix `/api/admin`.

#### **GET** `/api/admin/leave-requests`
Mendapatkan daftar semua pengajuan cuti dari semua karyawan, diurutkan dari yang terbaru.
*(Membutuhkan Autentikasi sebagai Admin)*

#### **GET** `/api/admin/leave-requests/{id}`
Mendapatkan detail spesifik dari satu pengajuan cuti berdasarkan ID-nya. Ganti `{id}` dengan ID dari `leave_request`.
*(Membutuhkan Autentikasi sebagai Admin)*

#### **PATCH** `/api/admin/leave-requests/{id}/status`
Menyetujui atau menolak sebuah pengajuan cuti. Ganti `{id}` dengan ID dari `leave_request`.
*(Membutuhkan Autentikasi sebagai Admin)*

**Body (raw JSON):**
```json
{
    "status": "approved"
}
```
atau
```json
{
    "status": "rejected"
}
```
