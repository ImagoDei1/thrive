<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RTMP Ingest URL
    |--------------------------------------------------------------------------
    |
    | Base RTMP URL that providers push their live stream to via MediaMTX.
    | Each channel's individual rtmp_key is appended by the client encoder.
    |
    */

    'rtmp_ingest_url' => env('STREAMING_RTMP_INGEST_URL', 'rtmp://stream.' . parse_url(env('APP_URL', ''), PHP_URL_HOST) . '/live'),

    /*
    |--------------------------------------------------------------------------
    | MediaMTX Shared Secret
    |--------------------------------------------------------------------------
    |
    | Sent as the X-MediaMTX-Secret header by mediamtx.yml's runOnReady /
    | runOnNotReady hooks, and required by the 'mediamtx' middleware on
    | /api/mediamtx/* routes so only MediaMTX itself can call them.
    |
    */

    'mediamtx_secret' => env('STREAMING_MEDIAMTX_SECRET'),

];
