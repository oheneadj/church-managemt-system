# Pattern — File Uploads & Media

> Read this file before building any file upload feature, media management system, or document storage.

## 1. When to Read This

Read this file before:

- Adding any file upload capability to a project
- Building document storage (KYC, invoices, contracts)
- Implementing profile photo, product image, or media upload
- Setting up a CDN for serving user-uploaded content
- Processing uploaded files (resizing, converting, extracting)

**Why file uploads need their own pattern:** File uploads are one of the most common attack surfaces in web applications. An improperly handled file upload can allow an attacker to execute arbitrary code on your server, serve malicious content to other users, or exfiltrate data. Every file upload must be validated, stored safely, and served through a controlled path.

## 2. Validation

Validate every uploaded file on the server — never trust client-side validation alone.

**The four validation layers — all required:**

**1. File size**

- Set a maximum file size at both the HTTP layer (before the file is fully read) and the application layer
- Different limits for different upload types: profile photo (2 MB), document (10 MB), video (500 MB)
- Reject oversized files before processing — do not read the entire file then reject it
- Set the limit in your web server / framework configuration, not only in application code

**2. MIME type (from file contents)**

- Read the file's magic bytes (first few bytes) to determine the actual type — do not trust the `Content-Type` header or file extension
- An attacker can upload a PHP script named `photo.jpg` with `Content-Type: image/jpeg`
- Use a library that reads magic bytes: `file-type` (Node), `finfo_file()` (PHP), `python-magic` (Python)
- Only allow MIME types you explicitly support — reject everything else

**3. File extension**

- Validate the extension against your allowed list — in addition to MIME type, not instead of it
- Never serve uploaded files with executable extensions: `.php`, `.js`, `.html`, `.exe`, `.sh`, `.py`
- Rename every uploaded file — never use the original filename for storage (see Section 6)

**4. File content (for documents)**

- PDF: verify it is a valid PDF structure — an attacker can craft a PDF that exploits PDF readers
- Images: re-encode with an image library (strip EXIF data, re-compress) — neutralizes embedded malicious content
- Office documents: treat as untrusted — do not open server-side without sandboxing

**Virus/malware scanning:**

- For any file that will be downloaded or opened by other users (KYC documents, shared files), run a virus scan before storing
- Use ClamAV (self-hosted) or a cloud service (VirusTotal API, Cloudmersive)
- Quarantine files that fail scanning — do not delete immediately (may need for investigation)
- Scanning is async — queue it as a background job after upload, mark the file as `pending_scan` until cleared

## 3. Storage

**Never store uploaded files in the web root or public directory.** Files stored in a publicly accessible path can be accessed directly, bypassing your access controls.

**Private vs public storage:**

| Type | Storage | Served via | Use for |
| --- | --- | --- | --- |
| **Private** | Non-public bucket/path | Signed temporary URLs | KYC docs, invoices, user documents, anything user-specific |
| **Public** | Public CDN bucket | Direct CDN URL | Blog images, product photos, public assets |

**S3-compatible storage (recommended):**

- AWS S3, Cloudflare R2, DigitalOcean Spaces, MinIO (self-hosted)
- Never store files on the application server's local filesystem — it does not survive a redeploy or scaling event
- Use a single storage abstraction in your code — swap providers by changing config, not code

**File path / key design:**

```
// Private — tenant and user scoped
private/{tenantId}/{userId}/{ulid}.{ext}

// Public — content-addressed
public/products/{ulid}/{hash}.webp
public/blog/{slug}/cover.webp
```

- Never use the original filename as the storage key — see Section 6
- Use the file's ULID as the filename — unique, sortable, non-guessable
- Include the tenant/user scope in the path for private files

**Signed temporary URLs for private files:**

```ts
// Generate a URL that expires in 5 minutes
const url = await storage.getSignedUrl({
  key: file.storageKey,
  expiresIn: 300, // seconds
});
```

- Never return a permanent URL for private files
- Regenerate the signed URL on every request — never cache it longer than its expiry
- Set expiry based on use case: document download (5 min), thumbnail preview (1 hour)
- Log every signed URL generation for sensitive documents (KYC, financial)

## 4. Chunked Uploads for Large Files

For files larger than \~10 MB, a single HTTP request upload is unreliable — network interruptions will cause the upload to fail and require starting over. Use chunked / multipart uploads.

**When to use chunked uploads:**

- Videos, large documents, archives — anything the user would be frustrated to re-upload on failure
- Rule of thumb: chunk anything over 10 MB

**S3 Multipart Upload pattern (recommended):**

```
1. Client → API: initiate upload (filename, size, type)
2. API → S3: create multipart upload → returns uploadId
3. API → Client: returns uploadId + presigned URLs for each chunk
4. Client → S3: uploads each chunk directly to S3 using presigned URLs
5. Client → API: notify completion with uploadId + ETags
6. API → S3: complete multipart upload
7. API: validate file, trigger post-processing
```

**Why client-to-S3 direct upload:**

- The file never passes through your application server — no memory pressure, no bandwidth cost
- Chunks upload in parallel — faster for large files
- Resumable — if the upload fails mid-way, resume from the last successful chunk

**Frontend progress tracking:**

- Track each chunk's upload progress with `XMLHttpRequest` or `axios` `onUploadProgress`
- Show aggregate progress: `(completedChunks / totalChunks) * 100`
- Provide pause, resume, and cancel controls for large uploads
- Store chunk state in `localStorage` keyed by file hash — allows resume after page reload

**Server-side rules for chunked uploads:**

- Validate the final assembled file — do not skip validation because chunks were pre-signed
- Set a timeout for incomplete multipart uploads — S3 will accumulate storage cost for abandoned uploads
- Abort incomplete uploads older than 24 hours with a lifecycle rule
- The `uploadId` must be scoped to the authenticated user — prevent one user from completing another's upload

## 5. Image Processing

**Always process images server-side after upload — never serve the raw upload directly.**

**Why process images:**

- Raw uploads contain EXIF metadata — GPS location, device info, sometimes sensitive data
- Raw uploads may be enormous — a phone camera image can be 8–15 MB
- Malicious images can contain embedded scripts or exploits
- Consistent output format and dimensions improves UI reliability

**Processing pipeline:**

```
Upload received
  → Validate (MIME, size, extension)
  → Store raw to private bucket (original preserved)
  → Queue processing job
    → Strip EXIF metadata
    → Re-encode (removes embedded payloads)
    → Resize to required dimensions
    → Convert to WebP (modern format, smaller size)
    → Store processed versions to CDN-backed public bucket
    → Update file record with processed URLs
```

**Image variants — generate on upload, not on demand:**

- Define a set of variants per upload type: thumbnail (150×150), medium (800px wide), large (1600px wide)
- Generate all variants during the processing job — not lazily on first request
- Lazy generation on first request means the first user to view an image waits for processing

**Tools:**

- Node: `sharp` (fast, excellent quality, recommended)
- PHP: `Intervention Image` (wrapper around GD or Imagick)
- Cloud: Cloudflare Images, Imgix, Cloudinary (transform via URL parameters)

**WebP conversion:**

- Always convert to WebP for web delivery — typically 25–35% smaller than JPEG at equal quality
- Keep the original format in private storage for archival
- Serve WebP with a JPEG fallback for very old browsers (use `<picture>` element)

**EXIF stripping — always:**

- Every image upload must have EXIF metadata stripped before being served publicly
- EXIF can contain GPS coordinates — a user's home address embedded in a photo
- `sharp` strips EXIF by default; verify this in your processing code

## 6. Security

**Path traversal attacks:**

- Never use the original filename as the storage key or path
- An attacker can upload a file named `../../config/.env` or `../index.php`
- Always generate a new filename: `${ulid}.${sanitizedExtension}`
- Sanitize extensions — extract only the extension from the MIME type, not from the filename
- Store the original filename in the database for display only — never for filesystem operations

**Content sniffing:**

- Browsers will try to "sniff" the content type of a file if the server sends the wrong type
- An SVG file can contain JavaScript — if served as `image/svg+xml` from your domain, it executes in your users' browsers
- Set `X-Content-Type-Options: nosniff` on all file serving responses
- Serve user-uploaded files from a separate domain or subdomain (e.g. `uploads.yourdomain.com`) — isolates any executed scripts from your main app's origin
- Never serve uploaded HTML, SVG, or JavaScript files from the same origin as your app

**Access control:**

- Private files must go through your application to verify authorization before generating a signed URL
- Never generate a signed URL for a file without first confirming the requesting user has permission to access it
- Signed URLs are time-limited — an expired URL must not work
- Log every access to sensitive files (KYC documents, financial records)

**Upload endpoint hardening:**

- Rate limit the upload endpoint — a user should not be able to upload 1000 files per minute
- Require authentication on all upload endpoints — never allow anonymous uploads unless it is an explicit feature
- Return the file's storage key (ULID), not the full storage path, in the response — the client does not need to know the bucket structure
- Do not process uploaded files synchronously in the HTTP request — queue processing as a background job

## 7. File Upload Checklist

**Validation:**

- [ ] File size limit enforced at the HTTP layer before the file is fully read?
- [ ] MIME type validated from magic bytes — not from Content-Type header or extension?
- [ ] File extension validated against an explicit allowed list?
- [ ] Executable extensions rejected (`.php`, `.js`, `.html`, `.exe`, `.sh`)?
- [ ] Images re-encoded to neutralize embedded content?
- [ ] Virus scanning queued for files shared between users?

**Storage:**

- [ ] Files stored outside the web root — not in a publicly accessible directory?
- [ ] Private files stored in a private bucket — not publicly accessible directly?
- [ ] Public files stored in a CDN-backed public bucket?
- [ ] Original filename never used as the storage key?
- [ ] Storage key is a ULID — not guessable or sequential?
- [ ] Tenant/user scope included in the storage path for private files?
- [ ] Files never stored on the application server's local filesystem?

**Access control:**

- [ ] Authorization checked before generating signed URLs for private files?
- [ ] Signed URLs have appropriate expiry (5 min–1 hour depending on use case)?
- [ ] Access to sensitive files logged?

**Image processing:**

- [ ] EXIF metadata stripped from all images before public serving?
- [ ] Images re-encoded and converted to WebP?
- [ ] Image variants generated on upload — not lazily on first request?

**Security:**

- [ ] `X-Content-Type-Options: nosniff` set on file serving responses?
- [ ] User-uploaded HTML, SVG, and scripts served from a separate domain?
- [ ] Upload endpoint rate-limited?
- [ ] Upload endpoint requires authentication?
- [ ] File processing queued as a background job — not synchronous?

**Large files:**

- [ ] Files over 10 MB use multipart/chunked upload?
- [ ] S3 lifecycle rule aborts incomplete multipart uploads after 24 hours?
- [ ] Resumable upload state stored client-side by file hash?
