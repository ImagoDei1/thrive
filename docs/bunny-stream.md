# Bunny Stream — VOD (Phase 1)

## Setup
1. Set in `.env`:
   - `BUNNY_API_KEY` — account API key (Bunny dashboard → Account → API).
   - `BUNNY_WEBHOOK_SECRET` — any long random string. Embedded in each library's webhook URL.
   - `BUNNY_TOKEN_AUTH=true` — enables CDN token auth on new libraries; playback URLs are signed.
2. `php artisan migrate`, `npm run build`, and make sure a queue worker and the scheduler
   (`php artisan schedule:work` or cron) are running.
3. `php artisan bunny:provision-libraries` queues libraries for already-approved providers.
   New approvals dispatch `ProvisionBunnyLibraryJob` automatically, and any provider
   without a library gets one on first upload.

## Flow
1. Admin approves provider → `ProvisionBunnyLibraryJob` creates a library, sets its
   `WebhookUrl` (`/webhooks/bunny?secret=…`) and token auth, then stores the library ID,
   library API key (encrypted), CDN hostname and pull-zone security key (encrypted) on
   `provider_profiles`.
2. Provider → Videos → **Upload Videos** opens a full page
   (`/provider/channels/{channel}/upload`, `ContentUpload`), not a modal, so it can't be
   dismissed by accident. Files are added by picker or drag & drop, up to 20 per queue,
   each with its own title and description.
3. "Upload" calls `ContentUpload::startAll`, which creates one Bunny video and one
   `content_items` row (`status=uploading`) per file and returns presigned TUS headers.
   `resources/js/bunny-upload.js` streams the files straight from the browser to
   `https://video.bunnycdn.com/tusupload`, two at a time, never through Laravel.
   Chunks retry automatically. A failed file shows **Retry**, which resumes the same
   Bunny video. Leaving the page mid-upload asks for confirmation.
4. On TUS success the item becomes `processing`.
5. Bunny calls `POST /webhooks/bunny`. The controller only queues `SyncBunnyVideoJob`,
   which re-reads the video from the Bunny API (the payload is not trusted) and sets
   `ready` / `failed`, plus `hls_url`, `thumbnail_url`, duration, resolution, qualities.

## Playback URLs
`content_items.hls_url` stores the **unsigned** canonical URL
(`https://{cdn}/{guid}/playlist.m3u8`). `VideoSourceService` signs it at read time with a
Bunny directory token scoped to `/{guid}/`, valid for `BUNNY_URL_TTL_SECONDS` (default 6h).
Stored URLs therefore never expire. Always go through `VideoSourceService`.

### Preview player (admin + provider)
`/admin/content` (all videos) and each provider channel's Videos page have a play button
that opens Bunny's hosted iframe player in a modal (`HasVideoPreview` trait +
`<x-video-preview-modal />`; providers can only preview their own channel's items). Player (`VideoSourceService::embedUrl()`, base `BUNNY_EMBED_BASE`). With token auth
on, the embed carries `token = sha256_hex(bunny_token_key + video_guid + expires)`.
Previews send no heartbeats, so they never count as watch time.

## Deletion and maintenance
- Deleting a content item soft-deletes the row and queues `DeleteBunnyVideoJob`, which
  removes the video from Bunny unless the row was restored first.
- `bunny:sync-pending` runs every 10 minutes. It re-syncs items stuck in `processing`
  (missed webhooks) and marks uploads older than `BUNNY_UPLOAD_TTL_SECONDS` as `failed`,
  deleting their empty Bunny videos.

## Not yet done
- Live streaming via Bunny (`channels.bunny_stream_id` is reserved).
