/**
 * Browser → Bunny Stream TUS uploads for the provider "Upload Videos" page.
 *
 * Livewire (App\Livewire\Provider\ContentUpload) owns the queue: titles,
 * descriptions, statuses, validation. This module only:
 *  1. keeps the selected File objects (keyed by a random id) and sends their
 *     metadata to the component via addFiles(),
 *  2. on `bunny-upload-start` (presigned TUS headers per file) streams each
 *     file directly to Bunny, MAX_PARALLEL at a time,
 *  3. reports back with `bunny-upload-finished` / `bunny-upload-failed`.
 * Files never pass through Laravel.
 */
import * as tus from 'tus-js-client';

const MAX_PARALLEL = 2;

const files = new Map();   // key → File
const waiting = [];        // [{ key, tus }]
const active = new Map();  // key → tus.Upload

const isBusy = () => active.size > 0 || waiting.length > 0;

function newKey() {
    return (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`).replace(/[^A-Za-z0-9-]/g, '');
}

function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return `${bytes.toFixed(i ? 1 : 0)} ${units[i]}`;
}

function row(key) {
    return document.querySelector(`[data-bunny-row="${key}"]`);
}

function showProgress(key, percent, text) {
    const el = row(key);
    if (!el) return;
    el.classList.remove('hidden');
    el.querySelector('[data-bunny-progress]').value = percent;
    el.querySelector('[data-bunny-progress-text]').textContent = text;
}

function componentFor(el) {
    const root = el.closest('[wire\\:id]');
    return root ? Livewire.find(root.getAttribute('wire:id')) : null;
}

function addFiles(fileList, sourceEl) {
    const component = componentFor(sourceEl);
    if (!component || !fileList?.length) return;

    const meta = [];
    for (const file of fileList) {
        const key = newKey();
        files.set(key, file);
        meta.push({ key, name: file.name, size: file.size, type: file.type || 'video/mp4' });
    }
    component.addFiles(meta);
}

// ---------------------------------------------------------------------------
// File selection: click-to-choose and drag & drop
// ---------------------------------------------------------------------------

document.addEventListener('change', (e) => {
    if (!e.target.matches('[data-bunny-files]')) return;
    addFiles(e.target.files, e.target);
    e.target.value = ''; // allow choosing the same files again later
});

for (const type of ['dragover', 'dragleave', 'drop']) {
    document.addEventListener(type, (e) => {
        const zone = e.target.closest?.('[data-bunny-dropzone]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.toggle('border-primary', type === 'dragover');
        if (type === 'drop') addFiles(e.dataTransfer.files, zone);
    });
}

// ---------------------------------------------------------------------------
// Upload queue
// ---------------------------------------------------------------------------

function pump() {
    while (active.size < MAX_PARALLEL && waiting.length) {
        const job = waiting.shift();
        start(job.key, job.tus);
    }
}

async function start(key, tusConfig) {
    const file = files.get(key);
    const id = tusConfig.content_item_id;

    if (!file) {
        Livewire.dispatch('bunny-upload-failed', { key, id, message: 'File no longer selected' });
        pump();
        return;
    }

    showProgress(key, 0, 'Starting…');

    const upload = new tus.Upload(file, {
        endpoint: tusConfig.endpoint,
        retryDelays: [0, 3000, 5000, 10000, 20000, 60000],
        chunkSize: 50 * 1024 * 1024,
        headers: tusConfig.headers,
        metadata: tusConfig.metadata,
        removeFingerprintOnSuccess: true,
        onProgress(sent, total) {
            const pct = total ? Math.floor((sent / total) * 100) : 0;
            showProgress(key, pct, `${pct}% · ${formatBytes(sent)} of ${formatBytes(total)}`);
        },
        onSuccess() {
            active.delete(key);
            files.delete(key);
            showProgress(key, 100, 'Uploaded. Processing on Bunny…');
            Livewire.dispatch('bunny-upload-finished', { key, id });
            pump();
        },
        onError(error) {
            active.delete(key);
            showProgress(key, 0, 'Interrupted.');
            Livewire.dispatch('bunny-upload-failed', { key, id, message: String(error?.message ?? error) });
            pump();
        },
    });

    active.set(key, upload);

    // Resume an earlier interrupted upload of the same file to the same video.
    const previous = await upload.findPreviousUploads();
    if (previous.length) upload.resumeFromPreviousUpload(previous[0]);

    upload.start();
}

document.addEventListener('livewire:init', () => {
    Livewire.on('bunny-upload-start', ({ uploads }) => {
        for (const job of uploads) {
            if (active.has(job.key) || waiting.some((w) => w.key === job.key)) continue;
            waiting.push(job);
            showProgress(job.key, 0, 'Queued…');
        }
        pump();
    });
});

// ---------------------------------------------------------------------------
// Guard against leaving the page mid-upload
// ---------------------------------------------------------------------------

window.addEventListener('beforeunload', (e) => {
    if (isBusy()) { e.preventDefault(); e.returnValue = ''; }
});

document.addEventListener('livewire:navigate', (e) => {
    if (!isBusy()) return;
    if (!confirm('Uploads are still in progress. Leave this page and stop them?')) {
        e.preventDefault();
        return;
    }
    waiting.length = 0;
    for (const upload of active.values()) upload.abort();
    active.clear();
    files.clear();
});

// A fresh visit to the page starts with an empty queue; drop stale File refs.
document.addEventListener('livewire:navigated', () => {
    if (!document.querySelector('[data-bunny-uploader]')) files.clear();
});
