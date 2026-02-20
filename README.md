# SULUT NEWS HUB — TVRI Sulawesi Utara

Portal Berita Digital resmi TVRI Sulawesi Utara. Menyajikan berita terkini seputar politik, ekonomi, budaya, dan kehidupan masyarakat Sulawesi Utara, dilengkapi integrasi cuaca BMKG, agregasi berita nasional/internasional, serta sistem broadcast monitoring.


## Daftar Isi

- [Informasi Umum](#informasi-umum)
- [Teknologi Stack](#teknologi-stack)
- [Struktur Direktori](#struktur-direktori)
- [Instalasi & Setup](#instalasi--setup)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [Integrasi API Eksternal](#integrasi-api-eksternal)
- [Sistem Autentikasi & Peran Pengguna](#sistem-autentikasi--peran-pengguna)
- [Fitur Keamanan](#fitur-keamanan)
- [Panel Admin](#panel-admin)
- [Halaman Publik](#halaman-publik)
- [Statistik Kode](#statistik-kode)



## Informasi Umum

| Parameter | Nilai |
|---|---|
| **APP_NAME** | `SULUT NEWS HUB` |
| **Versi** | `2.0.1` (ASSET_VERSION) |
| **Environment** | `development` (configurable ke `production`) |
| **Timezone** | `Asia/Makassar` (WITA) |
| **Locale** | `id_ID.UTF-8` (Bahasa Indonesia) |
| **Database** | `tvri_sulut_db` |
| **DB Host** | `127.0.0.1` |
| **DB Engine** | MySQL/MariaDB (InnoDB) via `mysqli` |
| **Charset** | `utf8mb4` / `utf8mb4_unicode_ci` |
| **Max Upload** | 5 MB (configurable) |
| **Pagination** | 12 items per halaman |
| **Cache Default** | 30 menit (database-backed via `api_cache`) |


## Teknologi Stack

### Backend
- **PHP 8.x** — Server-side scripting
- **MySQLi** — Database driver (procedural & OOP hybrid)
- **cURL** — HTTP client untuk fetch API eksternal (BMKG, NewsAPI)
- **SimpleXML / DOMDocument** — Parsing XML/RSS feeds (BMKG CAP, ANTARA RSS)
- **password_hash / password_verify** — Bcrypt password hashing (PASSWORD_BCRYPT)
- **Session-based authentication** — PHP native sessions

### Frontend
- **Bootstrap 5.3.0** — CSS framework (via CDN)
- **Font Awesome 6.4.0** — Icon library (via CDN)
- **Google Fonts** — Inter, Playfair Display, Roboto
- **Leaflet.js 1.9.4** — Interactive maps (halaman cuaca/peringatan dini)
- **Chart.js 4.4.1** — Dashboard charts (News Intelligence, Broadcast Statistics)
- **DataTables 1.13.6** — Server-side data tables (admin panel)
- **Vanilla JavaScript** — No jQuery dependency on frontend, AJAX calls
- **Custom CSS** — `assets/css/style.css` (public), `assets/css/admin.css` (admin)

### Infrastructure
- **XAMPP** — Apache + MySQL local development stack
- **mod_rewrite** — URL handling
- **mod_deflate** — GZIP compression
- **mod_expires** — Browser caching


## Struktur Direktori


TVRI NEWS HUB/
├── index.php                    # Homepage
├── berita.php                   # Detail berita
├── berita-nasional.php          # Berita nasional (NewsAPI)
├── cari.php                     # Pencarian berita (fulltext)
├── cuaca.php                    # Halaman cuaca BMKG + peta Leaflet
├── kategori.php                 # Berita per kategori
├── tentang.php                  # Halaman tentang
├── video.php                    # Galeri video
├── build_weather_cache.php      # CLI: Pre-build weather cache
├── .htaccess                    # Security & performance rules
│
├── admin/                       # Panel Admin (session-protected)
│   ├── login.php                # Halaman login
│   ├── logout.php               # Logout handler
│   ├── auth.php                 # Auth system (roles, CSRF, flash messages)
│   ├── dashboard.php            # Dashboard utama + statistik
│   ├── berita-add.php           # Tambah/edit berita
│   ├── berita-edit.php          # Edit berita
│   ├── berita-list.php          # Daftar berita
│   ├── kategori-add.php         # Tambah kategori
│   ├── kategori-list.php        # Daftar kategori
│   ├── user-add.php             # Tambah/edit user
│   ├── user-list.php            # Daftar user
│   ├── settings.php             # Pengaturan website
│   ├── api-settings.php         # Konfigurasi API & Widget (admin-only)
│   ├── news-intelligence.php    # Dashboard Intelijen Berita (RSS analytics)
│   ├── broadcast-schedule.php   # Jadwal siaran live
│   ├── broadcast-statistics.php # Statistik siaran
│   ├── broadcast-export-pdf.php # Export statistik siaran ke PDF
│   ├── broadcast-update-status.php  # AJAX: Update status siaran
│   ├── ajax-clear-cache.php     # AJAX: Hapus API cache (admin-only)
│   ├── api-calendar.php         # API: Calendar events (broadcast)
│   ├── download-csv-template.php# Download template CSV broadcast
│   ├── upload-handler.php       # Upload gambar/video handler
│   └── includes/
│       ├── header.php           # Admin header template
│       └── footer.php           # Admin footer template
│
├── api/                         # REST API endpoints (JSON)
│   ├── antara-rss.php           # ANTARA RSS parser
│   ├── antara-rss-collector.php # ANTARA RSS batch collector
│   ├── bmkg-prakiraan.php       # BMKG prakiraan cuaca
│   ├── bmkg-peringatan.php      # BMKG peringatan dini
│   ├── bmkg-peringatan-map.php  # BMKG peringatan + polygon
│   ├── bmkg-cap-detail.php      # BMKG CAP alert detail
│   ├── cuaca-ajax.php           # AJAX cuaca progressive loader
│   ├── newsapi-fetch.php        # NewsAPI multi-provider
│   └── rss-analytics.php        # RSS analytics data
│
├── assets/
│   ├── css/
│   │   ├── style.css            # Public styles
│   │   └── admin.css            # Admin panel styles
│   ├── images/
│   └── js/
│       └── main.js              # Public JavaScript
│
├── cache/                       # Cache files (blocked via .htaccess)
│   └── bmkg-adm3-adm4-map-sulut.json
│
├── config/
│   ├── config.php               # Konfigurasi utama + helper functions
│   └── db.php                   # Database connection + auto-create
│
├── data/
│   └── wilayah/
│       └── sulawesi_utara_full.csv  # Data wilayah ADM1-ADM4 Sulut
│
├── database/                    # SQL schema & migration files
│   ├── tvri_sulut_db.sql        # Schema utama (users, categories, news, etc.)
│   ├── 04_api_integration_update.sql    # API cache + settings
│   ├── migration_live_broadcast.sql     # Broadcast schedule tables
│   ├── migration_rss_intelligence.sql   # RSS articles + fetch log
│   ├── migration_rss_v2_status.sql      # RSS editorial status update
│   └── update_failure_reasons.sql       # Broadcast failure reasons
│
├── includes/                    # Shared PHP includes
│   ├── header.php               # Public header (navbar, SEO meta, OG tags)
│   ├── footer.php               # Public footer (social links, scripts)
│   ├── bmkg-api.php             # BMKG API shared library
│   ├── weather-helpers.php      # Weather helper functions (ADM2/3/4)
│   ├── widget-berita.php        # Berita widget component
│   └── widget-cuaca.php         # Cuaca widget component
│
└── uploads/
    ├── .htaccess                # Blocks PHP execution in uploads
    ├── thumbnails/              # Uploaded news thumbnails
    └── videos/                  # Uploaded news videos
```

---

## Database Schema

Database: **`tvri_sulut_db`** — 10 tabel (5 core + 5 supporting)

### Core Tables

#### 1. `users` — Pengguna Sistem
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID unik user |
| `username` | VARCHAR(50) UNIQUE | Username login |
| `password` | VARCHAR(255) | Hash bcrypt |
| `full_name` | VARCHAR(100) | Nama lengkap |
| `email` | VARCHAR(100) UNIQUE | Email |
| `role` | ENUM('admin','editor','reporter') | Level akses (default: reporter) |
| `avatar` | VARCHAR(255) | Foto profil |
| `is_active` | TINYINT(1) | Status aktif (1/0) |
| `created_at` | TIMESTAMP | Waktu dibuat |
| `last_login` | TIMESTAMP NULL | Login terakhir |

#### 2. `categories` — Kategori Berita
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID kategori |
| `name` | VARCHAR(100) | Nama kategori |
| `slug` | VARCHAR(100) UNIQUE | URL-friendly name |
| `description` | TEXT | Deskripsi |
| `icon` | VARCHAR(50) | Icon Font Awesome |
| `color` | VARCHAR(7) | Warna hex |
| `is_active` | TINYINT(1) | Status aktif |
| `created_at` | TIMESTAMP | Waktu dibuat |

Kategori default: Berita Utama, Politik, Ekonomi, Pendidikan, Budaya, Olahraga, Kesehatan.

#### 3. `news` — Berita
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID berita |
| `slug` | VARCHAR(255) UNIQUE | URL slug |
| `title` | VARCHAR(255) | Judul berita |
| `subtitle` | VARCHAR(255) | Sub judul |
| `content` | TEXT | Isi berita (HTML) |
| `excerpt` | VARCHAR(500) | Ringkasan berita |
| `thumbnail` | VARCHAR(255) | Path thumbnail |
| `video_url` | VARCHAR(255) | Video lokal / YouTube embed |
| `category_id` | INT(11) FK | Referensi ke `categories` |
| `author_id` | INT(11) FK | Referensi ke `users` |
| `views` | INT(11) | Jumlah pembaca |
| `is_featured` | TINYINT(1) | Berita headline |
| `meta_description` | VARCHAR(160) | SEO meta description |
| `tags` | VARCHAR(255) | Tags (koma-separated) |
| `status` | ENUM('draft','published') | Status publikasi |
| `published_at` | TIMESTAMP NULL | Waktu publikasi |
| `created_at` | TIMESTAMP | Waktu dibuat |
| `updated_at` | TIMESTAMP | Waktu update |

Indexes: FULLTEXT di `title`, `content`, `excerpt` untuk fitur pencarian.

#### 4. `settings` — Pengaturan Website
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID setting |
| `setting_key` | VARCHAR(100) UNIQUE | Kunci setting |
| `setting_value` | TEXT | Nilai setting |
| `setting_type` | VARCHAR(20) | Tipe data (text/number/boolean/json) |
| `description` | VARCHAR(255) | Deskripsi setting |
| `is_public` | TINYINT(1) | Apakah publik |
| `updated_at` | TIMESTAMP | Waktu update |

Menyimpan: site identity, social links, contact info, NewsAPI keys, BMKG config, widget display toggles, weather widget locations JSON.

#### 5. `api_cache` — Cache API Eksternal
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID cache |
| `cache_key` | VARCHAR(255) UNIQUE | Identifier cache |
| `cache_data` | LONGTEXT | JSON response dari API |
| `expires_at` | DATETIME | Waktu expiry |
| `created_at` | TIMESTAMP | Waktu dibuat |
| `updated_at` | TIMESTAMP | Waktu update |

### Supporting Tables

#### 6. `news_logs` — Log Aktivitas Berita
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT(11) PK AI | ID log |
| `news_id` | INT(11) FK NULL | Referensi ke `news` |
| `user_id` | INT(11) FK | User yang melakukan aksi |
| `action` | VARCHAR(50) | Jenis aksi (upload/update/delete) |
| `status` | ENUM('success','failed') | Status aksi |
| `error_message` | TEXT | Pesan error |
| `file_name` | VARCHAR(255) | Nama file |
| `file_size` | INT(11) | Ukuran file (bytes) |
| `file_type` | VARCHAR(50) | Tipe MIME |
| `ip_address` | VARCHAR(45) | IP address user |
| `user_agent` | TEXT | Browser info |
| `created_at` | TIMESTAMP | Waktu log |

#### 7. `live_broadcast_schedule` — Jadwal Siaran Live
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK AI | ID jadwal |
| `news_id` | INT FK NULL | Link ke `news` (optional) |
| `broadcast_date` | DATE | Tanggal siaran |
| `news_title` | VARCHAR(255) | Judul berita |
| `news_category` | VARCHAR(100) | Kategori |
| `assigned_to` | INT FK NULL | User yang ditugaskan |
| `status` | ENUM('scheduled','broadcasted','failed') | Status siaran |
| `failure_reason_type` | VARCHAR(100) | Alasan gagal (predefined) |
| `failure_reason_custom` | TEXT | Alasan custom |
| `created_by` | INT FK | User pembuat entry |
| `created_at` | DATETIME | Waktu dibuat |
| `updated_at` | DATETIME | Waktu update |

Auto-cleanup via MySQL Event Scheduler: hapus data > 1 bulan.

#### 8. `broadcast_failure_reasons` — Alasan Gagal Siaran
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK AI | ID |
| `reason` | VARCHAR(100) UNIQUE | Deskripsi alasan |
| `display_order` | INT | Urutan tampilan |
| `is_active` | BOOLEAN | Status aktif |
| `created_at` | DATETIME | Waktu dibuat |

Alasan default: Kualitas video buruk, Kualitas Audio buruk, Masalah teknis, Konten tidak layak tayang, Batal dari manajemen, Lainnya (tulis manual).

#### 9. `rss_articles` — Artikel RSS Terkumpul
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK AI | ID artikel |
| `article_hash` | VARCHAR(64) UNIQUE | SHA256 URL (deduplication) |
| `title` | VARCHAR(500) | Judul artikel |
| `description` | TEXT | Deskripsi |
| `url` | VARCHAR(1000) | Link asli |
| `image_url` | VARCHAR(1000) | Link gambar |
| `source_feed` | VARCHAR(100) | Feed key (terkini, kota-manado, dst.) |
| `category` | VARCHAR(100) | Kategori dari RSS |
| `region` | VARCHAR(100) | Label wilayah |
| `author` | VARCHAR(200) | Penulis |
| `published_at` | DATETIME | Waktu publikasi asli |
| `fetched_at` | DATETIME | Waktu fetch |
| `editorial_status` | ENUM('new','reviewed','picked','rejected') | Status editorial |
| `reviewed_by` | INT NULL | User reviewer |
| `reviewed_at` | DATETIME NULL | Waktu review |
| `editorial_notes` | TEXT | Catatan editorial |
| `is_top_news` | TINYINT(1) | Dari feed top-news |
| `is_read` | TINYINT(1) | Sudah dibaca |
| `is_bookmarked` | TINYINT(1) | Di-bookmark |
| `created_at` | TIMESTAMP | Waktu dibuat |

#### 10. `rss_fetch_log` — Log Pengambilan RSS
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK AI | ID log |
| `started_at` | DATETIME | Waktu mulai fetch |
| `completed_at` | DATETIME NULL | Waktu selesai |
| `feeds_fetched` | INT | Jumlah feed sukses |
| `feeds_failed` | INT | Jumlah feed gagal |
| `articles_new` | INT | Artikel baru |
| `articles_updated` | INT | Artikel di-update |
| `articles_total` | INT | Total artikel |
| `duration_ms` | INT | Durasi (ms) |
| `error_details` | TEXT | Detail error |
| `created_at` | TIMESTAMP | Waktu dibuat |

---

## API Endpoints

Semua endpoint mengembalikan **JSON**, dengan header `Content-Type: application/json; charset=utf-8`.

### BMKG (Cuaca & Peringatan Dini)

| Endpoint | Method | Parameter | Deskripsi | Cache |
|---|---|---|---|---|
| `/api/bmkg-prakiraan.php` | GET | `city`, `adm3`, `adm4` | Prakiraan cuaca multi-kota & ADM3/ADM4 level | 5 menit |
| `/api/bmkg-peringatan.php` | GET | — | Peringatan dini cuaca dari Nowcast RSS | 5 menit |
| `/api/bmkg-peringatan-map.php` | GET | — | Peringatan dini + batch polygon data untuk peta | 5 menit |
| `/api/bmkg-cap-detail.php` | GET | `kode` | Detail peringatan CAP (Common Alerting Protocol) + polygon wilayah terdampak | — |
| `/api/cuaca-ajax.php` | GET | `type` (adm2/adm3/adm4), `code` | Progressive weather loading per level administratif | 30 menit |

### ANTARA RSS (Berita Lokal/Nasional)

| Endpoint | Method | Parameter | Deskripsi | Cache |
|---|---|---|---|---|
| `/api/antara-rss.php` | GET | `feed` (terkini/top-news/sulut-update), `limit` (1-20) | Parse feed RSS ANTARA Sulut secara real-time | 10 menit |
| `/api/antara-rss-collector.php` | GET/POST | — | Batch fetch, deduplicate, dan store artikel dari 27 feed | Admin session |

### NewsAPI (Berita Nasional & Internasional)

| Endpoint | Method | Parameter | Deskripsi | Cache |
|---|---|---|---|---|
| `/api/newsapi-fetch.php` | GET | `category` (indonesia/international), `page` | Multi-provider: NewsData.io + NewsAPI.org | 10 menit |

### RSS Analytics (News Intelligence)

| Endpoint | Method | Parameter | Deskripsi |
|---|---|---|---|
| `/api/rss-analytics.php` | GET | `action` | Multi-action analytics endpoint |

Actions tersedia:
- `overview` — Statistik umum artikel terkumpul
- `trend` — Tren publikasi over time
- `categories` — Distribusi per kategori
- `regions` — Distribusi per wilayah
- `keywords` — Top keywords
- `spikes` — Spike detection
- `articles` — Daftar artikel
- `top-news` — Artikel top news
- `update-status` — Update status editorial
- `last-fetch` — Info pengambilan terakhir

### Admin Internal API

| Endpoint | Method | Deskripsi | Auth |
|---|---|---|---|
| `/admin/api-calendar.php` | GET/POST | Calendar events (broadcast schedule) | Login required |
| `/admin/broadcast-update-status.php` | POST | Update status siaran (scheduled/broadcasted/failed) | Login required |
| `/admin/ajax-clear-cache.php` | POST | Hapus seluruh API cache | Admin only |
| `/admin/download-csv-template.php` | GET | Download template CSV broadcast | — |

---

## Integrasi API Eksternal

### 1. BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)

- **Prakiraan Cuaca** — Data XML prakiraan cuaca per kota/kabupaten di Sulawesi Utara  
  Source: `https://api.bmkg.go.id/publik/prakiraan-cuaca?adm2=XX.XX` (dan ADM3/ADM4)
- **Peringatan Dini** — RSS feed nowcast peringatan cuaca ekstrem  
  Source: `https://data.bmkg.go.id/DataMKG/MEWS/DigitalForecast/` (Nowcast RSS)
- **CAP Alert** — Common Alerting Protocol detail peringatan  
  Parsing via DOMDocument & SimpleXML per kode peringatan
- **Peta Interaktif** — Polygon wilayah terdampak ditampilkan di Leaflet.js
- **Wilayah ADM1-ADM4** — Data mapping dari `data/wilayah/sulawesi_utara_full.csv`
- **Caching** — Database-backed caching (5-30 menit tergantung endpoint)

### 2. NewsAPI (Multi-Provider)

- **NewsData.io** — Berita Indonesia
- **NewsAPI.org** — Berita internasional  
  API key disimpan di tabel `settings`, configurable dari admin panel.  
  Endpoint: `/api/newsapi-fetch.php?category=indonesia|international`

### 3. ANTARA News (RSS Feed)

- **27 feed** dari `manado.antaranews.com/rss/` — Berbagai kategori & wilayah Sulut
- **Batch collector** — Deduplicate via SHA256 hash, store ke `rss_articles`
- **News Intelligence** — Dashboard analitik: tren, distribusi kategori/wilayah, keyword analysis, spike detection
- **Editorial workflow** — Read/bookmark/review status per artikel

---

## Sistem Autentikasi & Peran Pengguna

### Login Flow

1. User mengakses `/admin/login.php`
2. `redirectIfLoggedIn()` — redirect ke dashboard jika sudah login
3. POST: username & password divalidasi
4. Query prepared statement ke tabel `users` (WHERE `is_active = 1`)
5. Password diverifikasi dengan `password_verify()` (bcrypt)
6. `session_regenerate_id(true)` — prevent session fixation
7. Session variables di-set: `admin_logged_in`, `admin_user_id`, `admin_username`, `admin_full_name`, `admin_role`
8. `last_login` di-update di database
9. Redirect ke `/admin/dashboard.php`

### Peran Pengguna (Roles)

| Role | Level Akses | Keterangan |
|---|---|---|
| **admin** | Full access | Akses semua fitur termasuk: kelola user, API settings, clear cache, semua CRUD |
| **editor** | Editor access | Kelola berita, kategori, broadcast schedule, news intelligence |
| **reporter** | Basic access | Tambah berita, lihat dashboard |

### Role Functions

- `requireLogin()` — Redirect ke login jika belum authenticated
- `hasRole($role)` — Cek apakah user punya role tertentu (admin selalu `true`)
- `requireRole($role)` — Enforce role, redirect + flash message jika forbidden
- `requireAdmin()` — Shortcut: `requireLogin()` + `requireRole('admin')`
- `getLoggedInUser()` — Return array user data dari session

### Default Users (Seed Data)

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin |
| `editor` | `editor123` | editor |
| `reporter` | `reporter123` | reporter |

---

## Fitur Keamanan

### Authentication & Session

- **Bcrypt password hashing** — `PASSWORD_BCRYPT` via `password_hash()`
- **Session regeneration** — `session_regenerate_id(true)` saat login untuk mencegah session fixation
- **Session-based auth** — Tidak menggunakan token di URL
- **Active user check** — Hanya user dengan `is_active = 1` yang bisa login

### CSRF Protection

- **Token generation** — `bin2hex(random_bytes(32))` — 64 karakter hex
- **Token validation** — `hash_equals()` untuk timing-safe comparison
- **csrfField()** — Helper untuk output hidden input field
- **requireCSRF()** — Middleware untuk validasi semua POST request

### Input Sanitization

- **sanitizeInput()** — `trim()` + `stripslashes()` + `htmlspecialchars(ENT_QUOTES, 'UTF-8')`
- **h()** — Output escaping helper: `htmlspecialchars(ENT_QUOTES, 'UTF-8')`
- **Prepared statements** — Semua query database menggunakan `mysqli_prepare()` + `bind_param()`

### .htaccess Security

- **Sensitive file protection** — Block akses ke `.env`, `.sql`, `.log`, `.ini`, `.bak`, `.json`, `.csv`, `.md`
- **Directory blocking** — Block `/config/`, `/database/`, `/cache/`, `/data/`, `/docs/`
- **CLI-only protection** — Block akses web ke `build_weather_cache.php`
- **Directory listing disabled** — `Options -Indexes`
- **Upload folder hardening** — PHP execution disabled di `/uploads/`, hanya izinkan JPEG/PNG/GIF/WebP/MP4/MOV/AVI/PDF

### HTTP Security Headers

- **X-XSS-Protection** — `1; mode=block`
- **X-Frame-Options** — `SAMEORIGIN` (prevent clickjacking)
- **X-Content-Type-Options** — `nosniff` (prevent MIME sniffing)

### Upload Security

- **MIME type validation** — Check header dan actual content
- **Allowed types** — JPEG, PNG, WebP only
- **Max size** — Configurable (default 5 MB)
- **Unique filename** — Prevent overwrites via unique naming

### Performance & Caching

- **GZIP compression** — `mod_deflate` untuk HTML, CSS, JS, JSON
- **Browser caching** — `mod_expires` (images: 1 month, CSS/JS: 1 week)
- **Database-backed API cache** — `api_cache` table dengan configurable TTL
- **Asset versioning** — `ASSET_VERSION` constant untuk cache busting

---

## Panel Admin

### Dashboard (`/admin/dashboard.php`)
- Statistik: total berita published/draft, kategori aktif, total views, user aktif
- Live broadcast stats hari ini (scheduled/broadcasted/failed)
- Chart.js visualisasi

### Manajemen Berita
- **Tambah/Edit Berita** — WYSIWYG editor, upload thumbnail, embed video
- **Daftar Berita** — DataTables dengan filter, sorting, pagination
- **Status** — Draft / Published workflow
- **Fulltext Search** — MySQL FULLTEXT index di title, content, excerpt

### Manajemen Kategori
- CRUD kategori dengan slug, icon Font Awesome, warna hex

### Manajemen User (Admin Only)
- CRUD user, assign role, activate/deactivate

### Broadcast Monitoring
- **Jadwal Siaran** — Calendar view + list view, CRUD jadwal
- **Status Tracking** — Scheduled → Broadcasted / Failed
- **Failure Reasons** — Predefined + custom reasons
- **Statistik** — Grafik performa siaran (harian/bulanan/tahunan)
- **Export PDF** — Export statistik siaran ke PDF
- **CSV Import Template** — Download template untuk bulk import

### News Intelligence (`/admin/news-intelligence.php`)
- Dashboard analitik RSS ANTARA
- Trend analysis, distribusi kategori & wilayah
- Keyword extraction, spike detection
- Editorial workflow: read/bookmark artikel

### Pengaturan
- **Settings** — Site name, tagline, social links, contact info
- **API & Widget Settings** (Admin Only) — NewsAPI keys, BMKG config, widget toggles, weather widget locations (sortable JSON)
- **Clear Cache** — Hapus seluruh API cache

---

## Halaman Publik

| Halaman | File | Deskripsi |
|---|---|---|
| Homepage | `index.php` | Berita headline, terkini, widget cuaca, berita nasional |
| Detail Berita | `berita.php` | Detail berita + metadata + related news |
| Berita Nasional | `berita-nasional.php` | Aggregasi dari NewsAPI |
| Pencarian | `cari.php` | Fulltext search berita |
| Cuaca | `cuaca.php` | Prakiraan cuaca BMKG + peringatan dini + peta Leaflet |
| Kategori | `kategori.php` | Berita per kategori dengan pagination |
| Tentang | `tentang.php` | Halaman tentang TVRI Sulut |
| Video | `video.php` | Galeri video berita |

### SEO

- Open Graph meta tags per halaman
- Meta description & keywords
- Semantic HTML5 structure
- Indonesian locale & date formatting

---

## Statistik Kode

| Tipe | Jumlah File | Total Baris |
|---|---|---|
| PHP | 51 | 15,534 |
| CSS | 2 | 4,162 |
| JavaScript | 1 | 285 |
| SQL | 6 | 367 |
| **Total** | **60** | **20,348** |

---

## Instalasi & Setup

### Prasyarat

- **XAMPP** (Apache + MySQL/MariaDB + PHP 8.x)
- PHP Extensions: `mysqli`, `curl`, `simplexml`, `dom`, `json`, `mbstring`

### Langkah Instalasi

1. **Clone/copy** project ke `htdocs/`:
   ```
   C:\xampp\htdocs\TVRI NEWS HUB\
   ```

2. **Start XAMPP** — Apache + MySQL harus berjalan

3. **Database auto-create** — Saat pertama kali diakses, sistem akan otomatis:
   - Membuat database `tvri_sulut_db`
   - Import semua SQL files secara berurutan
   - Seed data default (users, categories, settings, sample news)

4. **Akses website**:
   - Public: `http://localhost/TVRI%20NEWS%20HUB/`
   - Admin: `http://localhost/TVRI%20NEWS%20HUB/admin/login.php`
   - Login: `admin` / `admin123`

5. **(Opsional) Konfigurasi API**:
   - Buka Admin → Pengaturan API & Widget
   - Set NewsAPI key jika ingin berita nasional/internasional
   - Konfigurasi widget cuaca (ADM2 locations)

### Konfigurasi Kustom

Edit `config/config.php`:
- `ENVIRONMENT` — `'development'` atau `'production'`
- `ITEMS_PER_PAGE` — Jumlah item per halaman
- `CACHE_DURATION` — Durasi cache default (detik)
- `MAX_UPLOAD_SIZE` — Batas upload (bytes)

Edit `config/db.php`:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`

---

## Lisensi

Internal project — TVRI Sulawesi Utara.
