# Mobile App Integration Guide

Key backend facts the Flutter app must respect. Verified against the live
backend on 2026-07-05.

## Playback

- **Play `hls_playback_url` from the API as-is** — e.g.
  `https://thrive265.online/hls/{slug}/index.m3u8`. Never construct or cache
  the URL it redirects to: the final URL contains the channel's `rtmp_key`,
  which dies whenever a provider regenerates their stream key. The slug URL
  is the stable one.
- **The player must follow redirects and accept cookies.** The chain is:
  slug URL → 302 (Laravel resolves slug → rtmp_key) →
  `/live/{rtmp_key}/index.m3u8` → 302 (`?cookieCheck=1`) + an `hlsSession`
  cookie from MediaMTX. ExoPlayer (Android) and AVPlayer (iOS) handle this
  by default, but a custom HTTP data source must have a cookie jar attached.
  Requests missing the session cookie get `401 authentication error`.
- **The stream is Low-Latency HLS** (fMP4 parts). ExoPlayer ≥ 2.13 and
  iOS 14+ support it. If old-device support becomes a problem, MediaMTX can
  be switched to plain fMP4/MPEG-TS server-side — raise it with the backend
  team rather than working around it in the app.
- **Offline channels return an error playlist, not an empty stream.** Treat
  a fatal player error as "offline" and retry every ~5 s. The web page at
  `/watch/{slug}` implements exactly this and doubles as a debugging
  reference: if `/watch` plays and the app doesn't, the problem is app-side.

## Catalog & subscription gating

- `GET /api/channels` — paginated (12/page), filters `?category=`,
  `?is_live=1` and `?search=` (case-insensitive substring of the name). `GET /api/channels/{id}` for detail + content items.
  All catalog endpoints require the Bearer token from `POST /api/login`.
- **`hls_playback_url` is `null` when the user has no active subscription.**
  That is the paywall signal, not an error. Same applies to `hls_url` on
  content items.
- `is_live` in the payload is realtime (webhook-driven, flips within a
  second or two of the encoder starting/stopping). There is no push
  channel — poll/refresh the channel list; every 10–15 s on a "live now"
  screen is reasonable.

## Playlists & series

- `GET /api/channels/{id}` also returns `playlists`: `id`, `title`,
  `description`, `kind` (`playlist` | `series`), `thumbnail_url` (falls back
  to the first video's thumbnail), `item_count`. Only published playlists with
  at least one ready video are listed.
- `GET /api/playlists/{id}` returns `playlist`, `channel`, and either:
  - `items` (kind `playlist`): videos in order, each with `position` (1-based), or
  - `seasons` (kind `series`): `[{season_number, episodes: [...]}]`, each
    episode with `season_number` and `episode_number`.
  Items use the same shape as `content_items`, including the `hls_url` paywall rule.
- A video can be in several playlists, so "next episode" / autoplay must
  follow the playlist the viewer started from. Keep the `playlist_id` in
  player state. Heartbeats are unchanged: send the video's `content_id` as usual.
- Episode and position numbers are derived from order, so they can shift if
  the provider reorders. Identify videos by `id`, never by number.

## Likes

- Every content item (channel detail, playlist items, series episodes) carries
  `like_count` and `liked` (whether the current user liked it). Use them to
  render the like button's initial state.
- `POST /api/content-items/{id}/like` likes, `DELETE` on the same URL unlikes.
  Both are idempotent and return `{content_id, liked, like_count}`; update the
  button from the response rather than incrementing locally.
- `404` means the video is no longer playable (unpublished, deleted or channel
  inactive). Hide the button or drop the item.
- Likes do not affect watch time or revenue.

## Watch-time heartbeats (revenue-critical)

- `POST /api/heartbeat` every 30 s, **only while `isPlaying &&
  !isBuffering`**. Paused/buffering time must not send heartbeats — this
  data directly determines provider payouts.
- `actual_elapsed_seconds` must be real wall-clock playing time since the
  last heartbeat (1–60). **Never derive it from playback position** — seeks
  would corrupt revenue data. Position goes in `playback_position`
  separately (used for anomaly detection only).
- `session_id` is a client-generated UUID per viewing session. The backend
  recreates the session row if the app crashed and resumed, so reuse the
  same UUID on resume when available.

Payload:

```json
{
  "session_id": "uuid",
  "channel_id": "uuid",
  "actual_elapsed_seconds": 30,
  "playback_position": 1234,
  "content_type": "live",
  "content_id": null,
  "platform": "android",
  "quality": "720p"
}
```

## Subscription packages

`GET /api/plans` returns every package currently offered (24-hour pass, weekly,
monthly — admins can add or hide plans), shortest first:

```json
{
  "id": "uuid",
  "name": "24-Hour Pass",
  "slug": "daily",
  "description": "…",
  "price": "300.00",
  "currency": "MWK",
  "interval": "day",          // day | week | month | year | custom
  "duration_days": 1,
  "duration_label": "24 hours",
  "features": ["…"]
}
```

- Don't hardcode "/ month" — render `duration_label` next to the price.
- All packages unlock the full catalogue; they differ only in how long access lasts.
- Buying a package while another is still running **extends** access: the new
  one starts when the current one ends. `GET /api/subscription` returns
  `starts_at`, `ends_at` and `plan.interval` for the latest purchase.
- Access ends exactly at `ends_at` (`hls_playback_url` becomes `null`), even
  for plans with a grace period — grace only affects the account status label.

## Not ready yet

- **Payments**: `POST /api/subscription/checkout` currently returns a
  placeholder PayChangu URL — the real integration is not wired. Do not
  build the final payment flow against it yet.
