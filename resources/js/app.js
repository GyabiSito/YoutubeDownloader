const state = {
    video: null,
    analyzedUrl: '',
    mode: 'video',
    quality: null,
    loadingInfo: false,
    downloading: false,
    downloadStatusTimeout: null,
};

const allowedHosts = new Set([
    'youtube.com',
    'www.youtube.com',
    'm.youtube.com',
    'music.youtube.com',
    'youtu.be',
]);

const elements = {
    urlForm: document.querySelector('#url-form'),
    urlInput: document.querySelector('#youtube-url'),
    clearUrl: document.querySelector('#clear-url'),
    searchButton: document.querySelector('#search-button'),
    feedback: document.querySelector('#form-feedback'),
    feedbackText: document.querySelector('#form-feedback span'),
    analysis: document.querySelector('#analysis-state'),
    result: document.querySelector('#result'),
    thumbnailWrap: document.querySelector('#thumbnail-wrap'),
    thumbnail: document.querySelector('#video-thumbnail'),
    title: document.querySelector('#video-title'),
    channel: document.querySelector('#video-channel'),
    duration: document.querySelector('#video-duration'),
    metaSeparator: document.querySelector('#meta-separator'),
    modeInputs: document.querySelectorAll('input[name="download-mode"]'),
    qualitySection: document.querySelector('#quality-section'),
    qualityOptions: document.querySelector('#quality-options'),
    audioSummary: document.querySelector('#audio-summary'),
    downloadForm: document.querySelector('#download-form'),
    downloadButton: document.querySelector('#download-button'),
    downloadLabel: document.querySelector('#download-label'),
    downloadStatus: document.querySelector('#download-status'),
    downloadFrame: document.querySelector('#download-target'),
};

function isValidYoutubeUrl(value) {
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol)
            && allowedHosts.has(url.hostname.toLowerCase())
            && !url.username
            && !url.password
            && !url.port;
    } catch {
        return false;
    }
}

function showFeedback(message) {
    elements.feedbackText.textContent = message;
    elements.feedback.hidden = false;
}

function clearFeedback() {
    elements.feedback.hidden = true;
    elements.feedbackText.textContent = '';
}

function setInfoLoading(loading) {
    state.loadingInfo = loading;
    elements.urlForm.classList.toggle('is-loading', loading);
    elements.urlInput.disabled = loading;
    elements.searchButton.disabled = loading;
    elements.analysis.hidden = !loading;
}

function resetResult() {
    state.video = null;
    state.analyzedUrl = '';
    state.quality = null;
    elements.result.hidden = true;
    elements.qualityOptions.replaceChildren();
    setDownloadStatus('');
}

function pickDefaultQuality(qualities) {
    return qualities.find((quality) => quality === 1080)
        ?? qualities.filter((quality) => quality < 1080).sort((a, b) => b - a)[0]
        ?? [...qualities].sort((a, b) => a - b)[0];
}

function formatDuration(seconds) {
    if (!Number.isFinite(seconds)) return '';

    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const remainingSeconds = total % 60;

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`
        : `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
}

function renderQualities(qualities) {
    elements.qualityOptions.replaceChildren();
    state.quality = pickDefaultQuality(qualities);

    qualities.forEach((quality) => {
        const label = document.createElement('label');
        const input = document.createElement('input');
        const text = document.createElement('span');

        label.className = 'quality-chip';
        input.type = 'radio';
        input.name = 'quality';
        input.value = String(quality);
        input.checked = quality === state.quality;
        text.textContent = `${quality}p`;

        input.addEventListener('change', () => {
            state.quality = quality;
            updateDownloadControls();
        });

        label.append(input, text);
        elements.qualityOptions.append(label);
    });
}

function renderVideo(video) {
    state.video = video;
    state.analyzedUrl = elements.urlInput.value.trim();

    elements.title.textContent = video.title;
    elements.channel.textContent = video.channel ?? '';
    elements.duration.textContent = formatDuration(video.duration);
    elements.metaSeparator.hidden = !video.channel || !Number.isFinite(video.duration);

    if (video.thumbnail) {
        elements.thumbnail.src = video.thumbnail;
        elements.thumbnail.alt = `Miniatura de ${video.title}`;
        elements.thumbnailWrap.classList.remove('is-empty');
    } else {
        elements.thumbnail.removeAttribute('src');
        elements.thumbnail.alt = '';
        elements.thumbnailWrap.classList.add('is-empty');
    }

    renderQualities(video.qualities);
    state.mode = 'video';
    elements.modeInputs.forEach((input) => { input.checked = input.value === 'video'; });
    updateDownloadControls();
    elements.result.hidden = false;
}

function updateDownloadControls() {
    const isVideo = state.mode === 'video';
    elements.qualitySection.hidden = !isVideo;
    elements.audioSummary.hidden = isVideo;
    elements.downloadLabel.textContent = isVideo
        ? `Descargar ${state.quality}p MP4`
        : 'Descargar MP3';
    setDownloadStatus('');
}

function firstError(payload, fallback = 'No pudimos analizar ese video. Intentá nuevamente.') {
    if (payload?.message) return payload.message;

    const errors = payload?.errors;
    if (errors && typeof errors === 'object') {
        return Object.values(errors).flat()[0];
    }

    return fallback;
}

async function analyzeVideo(event) {
    event.preventDefault();
    if (state.loadingInfo) return;

    const url = elements.urlInput.value.trim();
    clearFeedback();

    if (!isValidYoutubeUrl(url)) {
        showFeedback('Pegá un enlace válido de youtube.com o youtu.be.');
        elements.urlInput.focus();
        return;
    }

    resetResult();
    setInfoLoading(true);

    try {
        const response = await fetch('/video-info', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': elements.urlForm.querySelector('input[name="_token"]').value,
            },
            body: JSON.stringify({ url }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) throw new Error(firstError(payload));
        renderVideo(payload.video);
    } catch (error) {
        showFeedback(error instanceof Error ? error.message : 'No pudimos analizar ese video.');
    } finally {
        setInfoLoading(false);
    }
}

function setDownloadStatus(message, type = '', clearAfter = 0) {
    clearTimeout(state.downloadStatusTimeout);
    state.downloadStatusTimeout = null;
    elements.downloadStatus.textContent = message;
    elements.downloadStatus.className = `download-status${type ? ` is-${type}` : ''}`;

    if (clearAfter > 0) {
        state.downloadStatusTimeout = window.setTimeout(() => {
            elements.downloadStatus.textContent = '';
            elements.downloadStatus.className = 'download-status';
            state.downloadStatusTimeout = null;
        }, clearAfter);
    }
}

function setDownloadPreparing(preparing) {
    state.downloading = preparing;
    elements.downloadButton.disabled = preparing;
    elements.downloadButton.classList.toggle('is-loading', preparing);
}

function finishDownload(message, type, clearAfter = 0) {
    setDownloadPreparing(false);
    setDownloadStatus(message, type, clearAfter);
}

function afterNextPaint() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
}

function startNativeDownload(token) {
    elements.downloadFrame.src = `${elements.downloadForm.dataset.downloadUrl}/${encodeURIComponent(token)}`;
}

async function prepareDownload(event) {
    event.preventDefault();

    if (!state.video || state.downloading) return;

    const payload = {
        url: state.analyzedUrl,
        type: state.mode,
    };

    if (state.mode === 'video') payload.quality = state.quality;

    setDownloadPreparing(true);
    setDownloadStatus('Preparando archivo…');

    try {
        const response = await fetch(elements.downloadForm.dataset.prepareUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': elements.downloadForm.querySelector('input[name="_token"]').value,
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(firstError(data, 'No pudimos preparar la descarga. Intentá nuevamente.'));
        }

        if (typeof data.download_token !== 'string' || data.download_token === '') {
            throw new Error('No pudimos iniciar la descarga. Intentá nuevamente.');
        }

        finishDownload('Descarga iniciada.', 'success', 1800);
        await afterNextPaint();
        startNativeDownload(data.download_token);
    } catch (error) {
        finishDownload(
            error instanceof Error ? error.message : 'No pudimos preparar la descarga. Intentá nuevamente.',
            'error',
        );
    }
}

elements.urlForm.addEventListener('submit', analyzeVideo);

elements.urlInput.addEventListener('input', () => {
    elements.clearUrl.hidden = elements.urlInput.value.length === 0;
    clearFeedback();

    if (state.video && elements.urlInput.value.trim() !== state.analyzedUrl) {
        resetResult();
    }
});

elements.clearUrl.addEventListener('click', () => {
    elements.urlInput.value = '';
    elements.clearUrl.hidden = true;
    clearFeedback();
    resetResult();
    elements.urlInput.focus();
});

elements.thumbnail.addEventListener('error', () => {
    elements.thumbnailWrap.classList.add('is-empty');
});

elements.modeInputs.forEach((input) => {
    input.addEventListener('change', () => {
        state.mode = input.value;
        updateDownloadControls();
    });
});

elements.downloadForm.addEventListener('submit', prepareDownload);

window.addEventListener('pagehide', () => {
    clearTimeout(state.downloadStatusTimeout);
});
