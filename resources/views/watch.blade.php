<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $channel->name }} — {{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
    <style>
        body { margin: 0; background: #0a0a0a; color: #eee; font-family: system-ui, sans-serif; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        header { padding: 1rem; display: flex; align-items: center; gap: .75rem; width: 100%; max-width: 960px; box-sizing: border-box; }
        h1 { font-size: 1.1rem; margin: 0; font-weight: 600; }
        .badge { font-size: .7rem; font-weight: 700; padding: .2rem .5rem; border-radius: .25rem; letter-spacing: .05em; }
        .live { background: #dc2626; color: #fff; animation: pulse 1.5s infinite; }
        .offline { background: #333; color: #999; }
        @keyframes pulse { 50% { opacity: .6; } }
        video { width: 100%; max-width: 960px; aspect-ratio: 16/9; background: #000; }
        #msg { padding: 1rem; color: #888; font-size: .9rem; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $channel->name }}</h1>
        <span id="badge" class="badge {{ $channel->is_live ? 'live' : 'offline' }}">{{ $channel->is_live ? 'LIVE' : 'OFFLINE' }}</span>
    </header>

    <video id="video" controls autoplay muted playsinline></video>
    <p id="msg">{{ $channel->is_live ? '' : 'This channel is offline. Playback will start automatically when the stream begins.' }}</p>

    <script>
        const src   = @json(route('hls.playback', ['slug' => $channel->slug, 'file' => 'index.m3u8']));
        const video = document.getElementById('video');
        const badge = document.getElementById('badge');
        const msg   = document.getElementById('msg');

        function setLive(live) {
            badge.textContent = live ? 'LIVE' : 'OFFLINE';
            badge.className = 'badge ' + (live ? 'live' : 'offline');
            msg.textContent = live ? '' : 'This channel is offline. Playback will start automatically when the stream begins.';
        }

        function start() {
            if (Hls.isSupported()) {
                const hls = new Hls();
                hls.loadSource(src);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, () => setLive(true));
                hls.on(Hls.Events.ERROR, (_, data) => {
                    if (data.fatal) {
                        hls.destroy();
                        setLive(false);
                        setTimeout(start, 5000); // retry until the stream comes up
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Safari / iOS: native HLS
                video.src = src;
                video.addEventListener('loadedmetadata', () => setLive(true));
                video.addEventListener('error', () => { setLive(false); setTimeout(start, 5000); });
            } else {
                msg.textContent = 'This browser cannot play HLS streams.';
            }
        }

        start();
    </script>
</body>
</html>
