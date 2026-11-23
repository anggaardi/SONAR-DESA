# M3 Project (Fitur 2: Halaman proyek publik & QR)

Owner: Dev 1 · Jadwal: 23-26 Nov
Spec: PRD Fitur Inti v1.1 Bagian 4 (P1-P12); PRD Arsitektur v1.2 M3 (6.4, 6.8, 6.9, 8)

## Task List

### M3-01 · Migrations, Enums, Models & Factories
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Arsitektur 6.4, 6.9
- Scope:
  - Migrations: `price_standards`, `audits`, `audit_items`, `projects`, `project_measurements`, `progress_photos`, `project_changes`, `reports`, `review_cycles`, `review_notes`.
  - Enums: `ProjectType`, `PublicStatus`, `MeasurementOrigin`, `PriceLabel`, `AuditStatus`, `ReportKind`, `ReviewOutcome`.
  - Models: `Project`, `ProjectMeasurement`, `ProgressPhoto`, `ProjectChange`, `Audit`, `AuditItem`, `Report`, `ReviewCycle`, `ReviewNote`, `PriceStandard`.
  - Factories: `ProjectFactory`, `ProjectMeasurementFactory`, `ProgressPhotoFactory`, `AuditFactory`, `AuditItemFactory`, `ReportFactory`.
- Files:
  - `database/migrations/*`
  - `app/Modules/Project/Models/*`
  - `app/Modules/Project/Enums/*`
  - `app/Modules/Audit/Models/*`
  - `app/Modules/Audit/Enums/*`
  - `database/factories/*`

### M3-02 · Public Project Endpoint & Resource (`GET /p/{slug}`)
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Fitur Inti 4.2, 4.3 (P1-P7, P10, P12); PRD Arsitektur 6.8, 6.9 (F3-2)
- Scope:
  - `ProjectController@show` returning Inertia page `Project/Show`.
  - `ProjectResource` providing items, measurements, progress photos, changes, in-radius report count ("Dicek warga di lokasi: N orang").
  - Friendly 404/page when project is draft or nonexistent (P10).
  - Absolute privacy: no citizen email, user_id, or invalid reason in props (F3-2).
- Files:
  - `app/Modules/Project/Http/Controllers/ProjectController.php`
  - `app/Modules/Project/Http/Resources/ProjectResource.php`

### M3-03 · Village Public Projects List (`GET /d/{village}`)
- Status: done · Ukuran: S · Owner: Dev 1
- Spec: PRD Fitur Inti 4.1; PRD Arsitektur 6.8
- Scope:
  - `VillageProjectController@index` returning list of published projects with total budget, progress_pct, public_status.
- Files:
  - `app/Modules/Project/Http/Controllers/VillageProjectController.php`
  - `app/Modules/Project/Http/Resources/VillageProjectResource.php`

### M3-04 · Stream Original RAB Document (`GET /p/{slug}/dokumen`)
- Status: done · Ukuran: S · Owner: Dev 1
- Spec: PRD Fitur Inti 4.2 Step 7 (P11, F20); PRD Arsitektur 6.8, 6.9
- Scope:
  - Stream original PDF/image from storage lazily.
- Files:
  - `app/Modules/Project/Http/Controllers/OriginalDocumentController.php`

### M3-05 · Progress Photo Upload & Media Serving (`POST /projects/{project}/progress`, `GET /media/progress/{photo}`)
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Fitur Inti 4.2 (P8); PRD Arsitektur 6.8, 6.9 (F3-3)
- Scope:
  - Upload up to 5 photos per request, update `progress_pct`.
  - Resize max 1600px and convert to WebP via Intervention Image to strip EXIF/GPS.
  - Policy authorization: only village owner desa role.
  - Stream photo with cache headers.
- Files:
  - `app/Modules/Project/Actions/StoreProgressPhoto.php`
  - `app/Modules/Project/Http/Controllers/ProgressPhotoController.php`
  - `app/Modules/Project/Http/Requests/StoreProgressPhotoRequest.php`
  - `app/Modules/Project/Policies/ProjectPolicy.php`

### M3-06 · QR Code Generator & Printable A4 View (`GET /projects/{project}/qr`)
- Status: done · Ukuran: S · Owner: Dev 1
- Spec: PRD Fitur Inti 4.2 (P9); PRD Arsitektur 6.8
- Scope:
  - Generate SVG QR code via `chillerlan/php-qrcode` pointing to `/p/{slug}`.
  - Return A4 poster printable view with short link, project title, and scan prompt.
- Files:
  - `app/Modules/Project/Actions/GenerateProjectQrSvg.php`
  - `app/Modules/Project/Http/Controllers/ProjectQrController.php`

### M3-07 · Tests (Feature & Unit)
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Fitur Inti P1-P12; PRD Arsitektur 6.9, 9.6
- Scope:
  - Feature tests covering all endpoints, privacy rules, IDOR prevention, and uploads.
- Files:
  - `tests/Feature/Project/*Test.php`
  - `tests/Unit/Project/*Test.php`
