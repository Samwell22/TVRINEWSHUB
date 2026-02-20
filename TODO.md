# TVRI NEWS HUB - TODO & ROADMAP
**Last Updated:** February 9, 2026

---

## 🚀 FITUR YANG AKAN DATANG (PRIORITAS)

### 1. KOMPAS API INTEGRATION (HIGH PRIORITY)
**Status:** ⏳ Menunggu detail API dari user

**Yang Dibutuhkan:**
- [ ] Endpoint URL Kompas API (contoh: `https://api.kompas.com/v1/news`)
- [ ] Method autentikasi (API Key di header? Query parameter? Bearer token?)
- [ ] Required parameters (date, category, limit, page, dll)
- [ ] Contoh response JSON dari API
- [ ] Rate limits dan cache duration yang direkomendasikan

**Yang Akan Dikerjakan Setelah Detail Tersedia:**
- [ ] Buat file `api/kompas-fetch.php` untuk handle Kompas API
- [ ] Tambah database setting: `kompas_api_key` di tabel `settings`
- [ ] Update `berita-nasional.php`:
  - Tab baru: **"Berita Nasional"** powered by Kompas
  - Tab lama: **"Berita Umum"** (NewsData.io) - rename
  - Tab existing: **"Internasional"** (NewsAPI.org)
- [ ] Test endpoint dan verify response format
- [ ] Implement error handling untuk Kompas API
- [ ] Add Kompas settings di `admin/api-settings.php`

---

## 🔧 IMPROVEMENT & OPTIMIZATION

### 2. Database Optimization
- [ ] Hapus setting `bmkg_adm4_kotamobagu` (kota sudah tidak dipakai)
- [ ] Add indexes untuk query yang sering digunakan:
  - `news.status` (untuk filter published/draft)
  - `news.created_at` (untuk sorting latest news)
  - `api_cache.endpoint, api_cache.expires_at` (untuk cache lookup)
- [ ] Archive old API cache entries (older than 7 days)

### 3. Admin Panel Enhancements
- [ ] Dashboard analytics:
  - Chart views per hari (last 7 days)
  - Top 5 kategori paling populer
  - Top 10 berita paling banyak dibaca
- [ ] Bulk actions untuk berita:
  - Publish/unpublish multiple items
  - Delete multiple items
  - Change category for multiple items
- [ ] Image editor/cropper untuk thumbnail upload
- [ ] Markdown editor untuk content (optional)

### 4. Public Site Improvements
- [ ] Pagination untuk halaman kategori (saat ini unlimited)
- [ ] Search functionality dengan filter:
  - By date range
  - By category
  - By keywords
- [ ] Related articles di detail berita (based on same category)
- [ ] Social media share buttons (Facebook, Twitter, WhatsApp)
- [ ] Print-friendly version untuk artikel
- [ ] Dark mode toggle

### 5. Performance Optimization
- [ ] Implement image lazy loading untuk thumbnails
- [ ] Add CDN untuk Bootstrap & Font Awesome (optional)
- [ ] Minify CSS/JS untuk production
- [ ] Implement service worker untuk PWA (optional)
- [ ] Add sitemap.xml untuk SEO
- [ ] Add robots.txt

---

## 🛡️ SECURITY ENHANCEMENTS

### 6. Authentication Improvements
- [ ] Add password strength requirements (min 8 chars, uppercase, numbers)
- [ ] Implement "Remember Me" functionality dengan secure token
- [ ] Add "Forgot Password" dengan email reset link
- [ ] Account lockout setelah 5 failed login attempts
- [ ] Session timeout after 30 minutes of inactivity
- [ ] Add 2FA (Two-Factor Authentication) - optional

### 7. Security Hardening
- [ ] Add CSRF tokens untuk semua forms (currently missing)
- [ ] Rate limiting untuk login attempts
- [ ] Add Content Security Policy (CSP) headers
- [ ] Implement input sanitization di semua form inputs
- [ ] Add file type validation untuk upload (whitelist approach)
- [ ] Encrypt API keys di database
- [ ] Add security headers (X-Frame-Options, X-Content-Type-Options, dll)

---

## 📱 RESPONSIVE & UX

### 8. Mobile Optimization
- [ ] Test dan optimize untuk mobile devices
- [ ] Add touch-friendly navigation
- [ ] Optimize image sizes untuk mobile (responsive images)
- [ ] Add mobile menu dengan smooth animation

### 9. User Experience
- [ ] Add loading states untuk API calls
- [ ] Add skeleton screens untuk content loading
- [ ] Improve error messages (lebih user-friendly)
- [ ] Add breadcrumb navigation di semua pages
- [ ] Add "Back to Top" button

---

## 🧪 TESTING & MONITORING

### 10. Quality Assurance
- [ ] Add automated testing (PHPUnit)
- [ ] Create test cases untuk critical functions:
  - User authentication
  - CRUD operations
  - API fetch functions
  - File upload handler
- [ ] Load testing untuk API endpoints
- [ ] Browser compatibility testing (Chrome, Firefox, Safari, Edge)

### 11. Monitoring & Logging
- [ ] Implement error logging ke file (`logs/error.log`)
- [ ] Add admin notification untuk critical errors
- [ ] Monitor API rate limits dan cache hit ratio
- [ ] Add analytics tracking (Google Analytics atau alternatif)
- [ ] Create backup automation script

---

## 📊 CONTENT MANAGEMENT

### 12. Media Library
- [ ] Create media library untuk manage semua uploads
- [ ] Add image gallery view
- [ ] Implement image search by filename
- [ ] Add file size limits dan storage quota per user
- [ ] Add bulk delete untuk old unused files

### 13. Category & Tag System
- [ ] Add tags/labels untuk berita (many-to-many relationship)
- [ ] Category hierarchy (parent-child categories)
- [ ] Category thumbnail/icon
- [ ] Category description dan SEO meta

---

## 🌐 ADDITIONAL FEATURES

### 14. Newsletter System (Optional)
- [ ] Email subscription form
- [ ] Newsletter template
- [ ] Send weekly digest of popular news
- [ ] Unsubscribe functionality

### 15. Comment System (Optional)
- [ ] User comments di detail berita
- [ ] Moderation panel di admin
- [ ] Reply to comments
- [ ] Report inappropriate comments

### 16. Multi-Language Support (Optional)
- [ ] Add language switcher (Indonesia / English)
- [ ] Translate UI strings
- [ ] Language-specific content management

---

## 📝 DOCUMENTATION

### 17. Project Documentation
- [ ] API documentation untuk developer
- [ ] User manual untuk admin panel (PDF)
- [ ] Installation guide untuk production deployment
- [ ] Database schema documentation
- [ ] Code comments untuk complex functions

---

## ⚙️ DEPLOYMENT

### 18. Production Readiness
- [ ] Setup production environment:
  - Configure proper `config.php` untuk production
  - Disable error display, log to file instead
  - Use environment variables untuk sensitive data
- [ ] SSL certificate setup (HTTPS)
- [ ] Database backup automation (daily)
- [ ] Setup staging environment untuk testing
- [ ] Create deployment checklist

---

## 📌 NOTES

**Current Status:**
- ✅ LANGKAH 1-3: Complete (Database, CRUD, Basic Features)
- ✅ LANGKAH 4: API Integration (BMKG, NewsData.io, NewsAPI.org) - Complete
- ✅ Config System: Centralized config dengan auto-detecting SITE_URL
- ✅ Bug Fixes: All major bugs fixed (Phase 161-175)
- ⏳ Kompas API: Waiting for user to provide API details

**Working Features:**
- ✅ Admin Panel: Login, Dashboard, Berita, Kategori, User Management
- ✅ Public Site: Homepage, Berita Nasional, Cuaca (3 cities), Video, Kategori, Tentang
- ✅ API Integration: NewsData.io (Indonesian news), NewsAPI.org (International), BMKG (Weather)
- ✅ Security: Prepared statements (SQL injection protected), password hashing, session-based auth

**Known Issues:**
- ⚠️ CSRF protection belum diimplementasi (semua forms vulnerable)
- ⚠️ No rate limiting untuk login attempts
- ⚠️ File upload hanya basic validation (need whitelist approach)

---

## 🎯 QUICK WINS (Easy Tasks - Can Implement Quickly)

1. **Add "Last Updated" timestamp di footer** (5 mins)
2. **Add favicon.ico** (2 mins)
3. **Add meta description di semua pages** (15 mins)
4. **Fix menu "Berita" active state** (5 mins)
5. **Add copyright year dynamically** `<?php echo date('Y'); ?>` (2 mins)
6. **Add loading spinner untuk API calls** (10 mins)
7. **Add "No results found" message untuk empty API responses** (5 mins)
8. **Add cache clear button di public (admin only)** (10 mins)

---

**Priority Order untuk Development:**
1. ⭐⭐⭐ **HIGH:** Kompas API Integration (when details available)
2. ⭐⭐⭐ **HIGH:** Security Enhancements (CSRF, rate limiting)
3. ⭐⭐ **MEDIUM:** Admin Panel Enhancements (analytics, bulk actions)
4. ⭐⭐ **MEDIUM:** Search functionality
5. ⭐ **LOW:** Optional features (newsletter, comments, multi-language)

---

**Contact for Questions:**
- User akan provide Kompas API details secara manual
- Semua perubahan harus tested comprehensively sebelum production
- Follow existing code patterns dan conventions
