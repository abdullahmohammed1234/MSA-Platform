# EMS Event Documents — Developer Documentation

## 1. Overview & Core Design Principle

The **EMS Event Documents** subsystem allows administrators to upload supplementary PDF files (itineraries, menus, event schedules, venue maps, programs, travel information) and attach them directly to specific EMS events.

### Security Invariant: Private Storage & Opaque Access URLs

Underlying PDF files are **never** stored in public storage (`/storage/app/public`) or served via direct public storage URLs.

Access is controlled via high-entropy access tokens and secure application endpoints:

```text
Private Storage (`storage/app/private/ems_documents`)
       ▲
       │ (internal resolution)
Access Controller (`/event-documents/{documentUuid}/{token}`)
       ▲
       │ (token validation + active/soft-delete checks)
Opaque QR Code / Access URL
       ▲
       │ (scan / click)
Attendee / Mobile Device
```

> **Security Note:** Event document QR codes act as bearer credentials. Anyone who possesses a valid QR code or access URL can view the attached PDF until the access token is rotated or the document is disabled/deleted.

---

## 2. Database Schema

The `event_documents` table structure:

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Auto-increment primary key (internal only). |
| `event_id` | `BIGINT UNSIGNED` | Foreign key referencing `ems_events.id` (`cascadeOnDelete`). |
| `uuid` | `CHAR(36)` | Unique ULID/UUID for application-level identification. Indexed. |
| `name` | `VARCHAR(255)` | Human-readable document title (e.g. "Dinner Menu"). |
| `document_type` | `VARCHAR(64)` | Enum type (`itinerary`, `menu`, `schedule`, `map`, `program`, `information`, `other`). |
| `description` | `TEXT` | Optional summary notes for attendees. |
| `original_filename` | `VARCHAR(255)` | Original uploaded file name (metadata only). |
| `storage_disk` | `VARCHAR(64)` | Private filesystem disk (e.g. `ems_documents`). |
| `storage_path` | `VARCHAR(255)` | Generated private path (`events/{event_uuid}/documents/{doc_uuid}.pdf`). |
| `mime_type` | `VARCHAR(127)` | Content MIME type (`application/pdf`). |
| `file_size` | `BIGINT UNSIGNED` | Size of the stored PDF in bytes. |
| `access_token_hash` | `CHAR(64)` | `SHA-256` hash of the raw bearer token. Indexed. |
| `is_active` | `BOOLEAN` | Active toggle flag. Inactive documents return HTTP 404. |
| `sort_order` | `INT` | Display order sorting value (ascending). |
| `uploaded_by` | `BIGINT UNSIGNED` | Foreign key referencing `users.id` (`nullOnDelete`). |
| `created_at` | `TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | Record last modification timestamp. |
| `deleted_at` | `TIMESTAMP` | Soft delete timestamp (`null` if active). |

---

## 3. Storage Configuration

Files are stored using Laravel's private filesystem abstraction configured in `config/filesystems.php`:

```php
'ems_documents' => [
    'driver' => 'local',
    'root' => storage_path('app/private/ems_documents'),
    'throw' => false,
    'report' => false,
],
```

The storage filename is generated deterministically using the document's UUID (`events/{event_uuid}/documents/{doc_uuid}.pdf`) to avoid relying on user-provided original filenames or path traversal vectors.

---

## 4. API Endpoints

### Administrative API (`/api/v1/ems`)

| Method | Endpoint | Description | Permission Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/events/{event}/documents` | List metadata for all documents attached to an event | `event_documents.view` |
| `POST` | `/events/{event}/documents` | Upload a new PDF document (multipart/form-data) | `event_documents.manage` |
| `PATCH` | `/events/{event}/documents/{document}` | Update document metadata (`name`, `document_type`, `description`, `sort_order`, `is_active`) | `event_documents.manage` |
| `POST` | `/events/{event}/documents/{document}/replace` | Replace underlying PDF file while maintaining UUID & QR URL | `event_documents.manage` |
| `POST` | `/events/{event}/documents/{document}/rotate-access` | Invalidate previous token and issue fresh QR / access URL | `event_documents.manage` |
| `GET` | `/events/{event}/documents/{document}/qr` | Generate QR code PNG Data URI for admin UI & printing | `event_documents.view` |
| `DELETE` | `/events/{event}/documents/{document}` | Soft delete document record and purge physical file | `event_documents.manage` |

### Public Secure Access Endpoint

| Method | Endpoint | Description | Authentication |
| :--- | :--- | :--- | :--- |
| `GET` | `/event-documents/{documentUuid}/{token}` | Stream private PDF with security headers | Public / Bearer Token |

#### Served Response Headers:
- `Content-Type: application/pdf`
- `Content-Disposition: inline; filename="document_name.pdf"`
- `X-Content-Type-Options: nosniff`
- `Cache-Control: private, no-store, max-age=0, must-revalidate`
- `Pragma: no-cache`

---

## 5. Token Handling & Security Model

1. **Token Generation:** When a document is created or rotated, `EventDocumentService` generates a 48-character hex token via `bin2hex(random_bytes(24))`.
2. **Token Storage:** Only `hash('sha256', $rawToken)` is stored in the database (`access_token_hash`). The raw token is **never** saved to disk, logged, or returned in standard document list payloads.
3. **Token Verification:** When an access request arrives at `/event-documents/{documentUuid}/{token}`, the controller hashes the provided token using `hash('sha256', $token)` and uses `hash_equals()` for constant-time comparison.
4. **Token Rotation:** Administrators can rotate access tokens at any time via `POST /events/{event}/documents/{document}/rotate-access`. This immediately revokes old QR codes.
5. **PDF Integrity & MIME Verification:** Uploads are validated against magic bytes (`%PDF-`) at the beginning of the file, rejecting spoofed `.php`, `.exe`, `.html`, or non-PDF contents even if disguised with `.pdf` extensions.

---

## 6. Document Replacement Behavior

When an administrator uploads a replacement PDF for an existing document:
- The `uuid` remains unchanged.
- The `access_token_hash` remains unchanged.
- The existing printed/distributed QR code **continues working** seamlessly.
- The physical storage file is overwritten atomically with the new PDF content.

---

## 7. Audit Trail

All document management operations trigger audit log entries via `EmsActivityLogger`:
- `document.created`
- `document.updated`
- `document.replaced`
- `document.token_rotated`
- `document.deleted`

---

## 8. Verification & Test Commands

To run the complete EMS Event Documents feature test suite:

```bash
php artisan test tests/Feature/Ems/EmsEventDocumentsTest.php
```

To run the complete EMS test suite:

```bash
php artisan test --filter=Ems
```
