# Synclyz Video CMS (PHP + MySQL)

Classic server-rendered PHP application for self-hosted video sharing and media community management.

## Features
- User registration and login.
- Video upload and per-video playback pages.
- Embeddable video player endpoint (`embed.php?id={video_id}`).
- Video gallery with category and tag filtering.
- Photo albums with per-album image uploads.
- Public user profiles with published videos/albums.
- Basic user-to-user messaging inbox.
- Admin panel under `/siteadmin` for user/video/settings management.

## Requirements
- PHP 8.1+
- MySQL 8+
- Web server (Apache or Nginx with PHP-FPM)

## Setup
1. Create database and import schema:
   ```bash
   mysql -u root -p video_cms < sql/schema.sql
   ```
2. Set environment variables as needed:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `APP_NAME`, `APP_BASE_URL`
3. Ensure upload folders are writable:
   ```bash
   mkdir -p public/uploads/videos public/uploads/photos
   chmod -R 775 public/uploads
   ```
4. Serve the project root as web root.

## Notes
- The first registered user is automatically marked as admin.
- DB-backed `settings.site_name` is editable from admin for future extension; runtime title currently comes from `config/config.php`.
- This starter implementation is intentionally minimal and should be hardened (CSRF protection, stricter validation, rate limiting, etc.) before production deployment.
