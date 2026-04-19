/**
 * MediaCompressor — client-seitige Video/Bild-Komprimierung vor dem Upload.
 *
 * Nutzt ffmpeg.wasm (single-threaded Build → keine SharedArrayBuffer / COOP/COEP nötig)
 * und wird on-demand per CDN nachgeladen, damit die App-Startzeit NICHT steigt.
 *
 * API:
 *   await MediaCompressor.shouldCompress(file)      // bool
 *   await MediaCompressor.compress(file, onProgress) // returns File (ggf. komprimiert)
 *
 * Fallback-Verhalten:
 *   - Browser unterstützt kein WASM → Original zurück
 *   - ffmpeg.wasm konnte nicht laden → Original zurück
 *   - Komprimierung schlägt fehl    → Original zurück
 *   - Ergebnis > Original           → Original zurück
 *
 * Der Server-seitige MediaOptimizerService bleibt als zweite Verteidigungslinie aktiv.
 */
(function(global) {
    'use strict';

    /* ── Konfiguration (sollte mit MediaOptimizerService::*_MAX_BYTES übereinstimmen) */
    var CFG = {
        VIDEO_MAX_BYTES: 10 * 1024 * 1024,   // > 10 MB → komprimieren
        VIDEO_MAX_WIDTH: 1280,               // Ziel-Breite
        VIDEO_CRF:       28,                 // H.264 CRF
        IMAGE_MAX_BYTES: 1.5 * 1024 * 1024,  // > 1.5 MB → komprimieren
        IMAGE_MAX_DIM:   2400,               // Ziel längste Kante
        IMAGE_QUALITY:   0.82,               // JPEG/WebP Quality

        /* ffmpeg.wasm v0.12 single-threaded ESM build */
        FFMPEG_CORE_URL: 'https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-core.js',
        FFMPEG_UMD_URL:  'https://unpkg.com/@ffmpeg/ffmpeg@0.12.10/dist/umd/ffmpeg.js',
        FFMPEG_UTIL_URL: 'https://unpkg.com/@ffmpeg/util@0.12.1/dist/umd/index.js',
    };

    var ffmpegInstance = null;
    var ffmpegLoading  = null;

    /** Prüft ob Datei überhaupt eine Komprimierung rechtfertigt. */
    function shouldCompress(file) {
        if (!file) return false;
        if (file.type && file.type.indexOf('video/') === 0) {
            return file.size > CFG.VIDEO_MAX_BYTES;
        }
        if (file.type && file.type.indexOf('image/') === 0 && file.type !== 'image/gif') {
            return file.size > CFG.IMAGE_MAX_BYTES;
        }
        return false;
    }

    /** Lädt ein Script-Tag nach und resolved wenn onload feuert. */
    function loadScript(src) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.onload  = function() { resolve(); };
            s.onerror = function() { reject(new Error('Script load failed: ' + src)); };
            document.head.appendChild(s);
        });
    }

    /**
     * Lädt ffmpeg.wasm lazy beim ersten Aufruf.
     * Wiederholte Aufrufe geben die laufende Promise zurück (kein Doppel-Load).
     */
    function loadFfmpeg() {
        if (ffmpegInstance) return Promise.resolve(ffmpegInstance);
        if (ffmpegLoading)  return ffmpegLoading;

        if (typeof WebAssembly === 'undefined') {
            return Promise.reject(new Error('WASM not supported'));
        }

        ffmpegLoading = (async function() {
            await loadScript(CFG.FFMPEG_UMD_URL);
            await loadScript(CFG.FFMPEG_UTIL_URL);

            /* FFmpegWASM global wird von der UMD-Bundle angelegt */
            if (!global.FFmpegWASM || !global.FFmpegWASM.FFmpeg) {
                throw new Error('FFmpeg UMD global not exposed');
            }
            var ffmpeg = new global.FFmpegWASM.FFmpeg();
            await ffmpeg.load({
                coreURL: CFG.FFMPEG_CORE_URL,
            });
            ffmpegInstance = ffmpeg;
            return ffmpeg;
        })();

        ffmpegLoading.catch(function() { ffmpegLoading = null; });
        return ffmpegLoading;
    }

    /** Zieht einen File als Uint8Array in den Worker. */
    async function fileToBytes(file) {
        var buf = await file.arrayBuffer();
        return new Uint8Array(buf);
    }

    /**
     * Client-seitige VIDEO-Kompression.
     * H.264 + AAC + faststart, maximal 1280p, CRF 28 → entspricht Server-Optimizer.
     */
    async function compressVideo(file, onProgress) {
        var ffmpeg = await loadFfmpeg();

        /* Progress-Hook */
        var progressHandler = function(ev) {
            if (typeof onProgress === 'function' && typeof ev.progress === 'number') {
                onProgress(Math.min(0.99, Math.max(0, ev.progress)));
            }
        };
        ffmpeg.on('progress', progressHandler);

        var inputName  = 'in' + Date.now();
        var ext        = (file.name.split('.').pop() || 'mov').toLowerCase().replace(/[^a-z0-9]/g, '');
        var inputFile  = inputName + '.' + (ext || 'mov');
        var outputFile = inputName + '.mp4';

        try {
            await ffmpeg.writeFile(inputFile, await fileToBytes(file));

            await ffmpeg.exec([
                '-i', inputFile,
                '-vf', "scale='min(" + CFG.VIDEO_MAX_WIDTH + ",iw)':-2",
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-crf', String(CFG.VIDEO_CRF),
                '-c:a', 'aac',
                '-b:a', '96k',
                '-movflags', '+faststart',
                '-pix_fmt', 'yuv420p',
                outputFile
            ]);

            var data = await ffmpeg.readFile(outputFile);
            var blob = new Blob([data.buffer], { type: 'video/mp4' });

            /* Nur übernehmen wenn tatsächlich kleiner */
            if (blob.size >= file.size * 0.95) {
                console.info('[MediaCompressor] video: no significant savings, keeping original');
                return file;
            }

            var newName = file.name.replace(/\.[^.]+$/, '') + '.mp4';
            console.info('[MediaCompressor] video ' + file.size + ' → ' + blob.size + ' bytes (' +
                Math.round((1 - blob.size / file.size) * 100) + '% saved)');
            return new File([blob], newName, { type: 'video/mp4', lastModified: Date.now() });
        } finally {
            ffmpeg.off('progress', progressHandler);
            try { await ffmpeg.deleteFile(inputFile); }  catch (e) {}
            try { await ffmpeg.deleteFile(outputFile); } catch (e) {}
            if (typeof onProgress === 'function') onProgress(1);
        }
    }

    /**
     * Client-seitige BILD-Kompression via Canvas.
     * Nutzt kein ffmpeg.wasm (overkill für Bilder) — native Canvas reicht völlig.
     */
    async function compressImage(file, onProgress) {
        if (typeof onProgress === 'function') onProgress(0.1);

        var targetMime = (file.type === 'image/png') ? 'image/jpeg' : file.type;
        var bitmap;
        try {
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (e) {
            /* Safari < 16 kennt kein imageOrientation — Fallback ohne Auto-Rotate */
            bitmap = await createImageBitmap(file);
        }

        var w = bitmap.width;
        var h = bitmap.height;
        var maxDim = Math.max(w, h);
        if (maxDim > CFG.IMAGE_MAX_DIM) {
            var ratio = CFG.IMAGE_MAX_DIM / maxDim;
            w = Math.round(w * ratio);
            h = Math.round(h * ratio);
        }

        var canvas = document.createElement('canvas');
        canvas.width  = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0, w, h);
        bitmap.close && bitmap.close();

        if (typeof onProgress === 'function') onProgress(0.6);

        var blob = await new Promise(function(resolve) {
            canvas.toBlob(resolve, targetMime, CFG.IMAGE_QUALITY);
        });

        if (typeof onProgress === 'function') onProgress(1);

        if (!blob || blob.size >= file.size) {
            return file; // kein Gewinn → Original
        }

        var ext = targetMime === 'image/jpeg' ? '.jpg'
                : targetMime === 'image/webp' ? '.webp'
                : '.png';
        var newName = file.name.replace(/\.[^.]+$/, '') + ext;
        console.info('[MediaCompressor] image ' + file.size + ' → ' + blob.size + ' bytes');
        return new File([blob], newName, { type: targetMime, lastModified: Date.now() });
    }

    /**
     * Hauptfunktion. Gibt IMMER einen File zurück — nie wirft sie,
     * damit der aufrufende Upload-Flow keine Extra-Fehlerbehandlung braucht.
     */
    async function compress(file, onProgress) {
        try {
            if (!shouldCompress(file)) return file;

            if (file.type.indexOf('video/') === 0) {
                return await compressVideo(file, onProgress);
            }
            if (file.type.indexOf('image/') === 0) {
                return await compressImage(file, onProgress);
            }
            return file;
        } catch (e) {
            console.warn('[MediaCompressor] compression failed, using original:', e);
            if (typeof onProgress === 'function') onProgress(1);
            return file;
        }
    }

    global.MediaCompressor = {
        shouldCompress: shouldCompress,
        compress:       compress,
        _config:        CFG,
    };
})(window);
