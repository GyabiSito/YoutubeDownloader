<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Descargá videos de YouTube en MP4 o extraé el audio en MP3, de forma simple.">
        <meta name="theme-color" content="#08090d">
        <title>Yutu — YouTube Downloader</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="ambient ambient--coral" aria-hidden="true"></div>
        <div class="ambient ambient--violet" aria-hidden="true"></div>

        <div class="site-shell">
            <header class="site-header" aria-label="Yutu">
                <a class="brand" href="{{ route('home') }}" aria-label="Yutu, inicio">
                    <span class="brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 28 28" fill="none">
                            <path d="M8 8.5 14 14l6-5.5M14 14v6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="m11 18 3 3 3-3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Yutu</span>
                </a>
                <span class="format-badge">MP4 <i></i> MP3</span>
            </header>

            <main>
                <section class="hero" aria-labelledby="page-title">
                    <p class="eyebrow"><span></span> Simple. Directo. Sin registros.</p>
                    <h1 id="page-title">Descargá sólo<br><em>lo que necesitás.</em></h1>
                    <p class="hero__copy">Pegá un enlace de YouTube y elegí video o audio.</p>

                    <form id="url-form" class="url-form" novalidate>
                        @csrf
                        <label class="sr-only" for="youtube-url">URL de YouTube</label>
                        <div class="url-field">
                            <span class="url-field__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M10.6 13.4a4.5 4.5 0 0 0 6.36.08l2.12-2.12a4.5 4.5 0 0 0-6.36-6.36L11.5 6.22M13.4 10.6a4.5 4.5 0 0 0-6.36-.08l-2.12 2.12A4.5 4.5 0 0 0 11.28 19l1.22-1.22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <input
                                id="youtube-url"
                                name="url"
                                type="url"
                                inputmode="url"
                                autocomplete="url"
                                maxlength="2048"
                                placeholder="Pegá una URL de YouTube"
                                aria-describedby="form-feedback"
                                required
                            >
                            <button id="clear-url" class="clear-button" type="button" aria-label="Limpiar enlace" hidden>
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                </svg>
                            </button>
                            <button id="search-button" class="search-button" type="submit">
                                <span class="search-button__label">Analizar</span>
                                <svg class="search-button__arrow" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>

                    <div id="form-feedback" class="feedback" role="status" aria-live="polite" hidden>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 6.5v4M10 13.5h.01M17.3 10A7.3 7.3 0 1 1 2.7 10a7.3 7.3 0 0 1 14.6 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <span></span>
                    </div>
                </section>

                <section id="analysis-state" class="analysis-state" aria-label="Analizando video" hidden>
                    <div class="skeleton skeleton--thumbnail"></div>
                    <div class="skeleton-copy">
                        <div class="skeleton skeleton--title"></div>
                        <div class="skeleton skeleton--line"></div>
                        <p><span class="mini-spinner" aria-hidden="true"></span> Consultando formatos disponibles…</p>
                    </div>
                </section>

                <section id="result" class="result" aria-label="Opciones de descarga" hidden>
                    <article class="video-preview">
                        <div id="thumbnail-wrap" class="video-preview__media">
                            <img id="video-thumbnail" src="" alt="" loading="lazy">
                            <span class="video-preview__play" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="m7.5 5.8 7 4.2-7 4.2V5.8Z"/></svg>
                            </span>
                        </div>
                        <div class="video-preview__content">
                            <span class="video-preview__label">VIDEO ENCONTRADO</span>
                            <h2 id="video-title"></h2>
                            <p class="video-preview__meta">
                                <span id="video-channel"></span>
                                <i id="meta-separator"></i>
                                <span id="video-duration"></span>
                            </p>
                        </div>
                        <span class="video-preview__check" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none"><path d="m5 10.3 3.1 3.1L15.5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </article>

                    <div class="download-panel">
                        <div class="section-heading">
                            <div>
                                <span class="step-number">01</span>
                                <h2>¿Qué querés descargar?</h2>
                            </div>
                            <span class="section-heading__hint">Elegí un formato</span>
                        </div>

                        <fieldset class="format-selector">
                            <legend class="sr-only">Formato de descarga</legend>
                            <label class="format-option">
                                <input type="radio" name="download-mode" value="video" checked>
                                <span class="format-option__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <rect x="3.5" y="5" width="17" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/>
                                        <path d="m10 9 5 3-5 3V9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="format-option__copy"><strong>Video</strong><small>Archivo MP4</small></span>
                                <span class="format-option__radio" aria-hidden="true"></span>
                            </label>
                            <label class="format-option">
                                <input type="radio" name="download-mode" value="audio">
                                <span class="format-option__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M9.5 17.5V6.8L18 5v10.5M9.5 17.5c0 1.38-1.34 2.5-3 2.5s-3-1.12-3-2.5 1.34-2.5 3-2.5 3 1.12 3 2.5Zm8.5-2c0 1.38-1.34 2.5-3 2.5s-3-1.12-3-2.5 1.34-2.5 3-2.5 3 1.12 3 2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="format-option__copy"><strong>Audio</strong><small>Archivo MP3</small></span>
                                <span class="format-option__radio" aria-hidden="true"></span>
                            </label>
                        </fieldset>

                        <div id="quality-section" class="quality-section">
                            <div class="section-heading section-heading--quality">
                                <div>
                                    <span class="step-number">02</span>
                                    <h2>Elegí la calidad</h2>
                                </div>
                                <span class="section-heading__hint">Resolución máxima</span>
                            </div>
                            <fieldset>
                                <legend class="sr-only">Calidad del video</legend>
                                <div id="quality-options" class="quality-options"></div>
                            </fieldset>
                        </div>

                        <div id="audio-summary" class="audio-summary" hidden>
                            <span class="audio-summary__icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="none"><path d="M10 3v9m0 0-3-3m3 3 3-3M4 15.5h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span><strong>Audio en MP3</strong><small>Usaremos la mejor pista disponible.</small></span>
                        </div>

                        <form
                            id="download-form"
                            data-prepare-url="{{ route('download.prepare') }}"
                            data-download-url="{{ url('/download') }}"
                        >
                            @csrf
                            <button id="download-button" class="download-button" type="submit">
                                <span class="download-button__icon" aria-hidden="true">
                                    <svg viewBox="0 0 22 22" fill="none"><path d="M11 3v10m0 0L7 9m4 4 4-4M4 17.5h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span id="download-label">Descargar MP4</span>
                                <span class="spinner" aria-hidden="true"></span>
                            </button>
                        </form>
                        <p id="download-status" class="download-status" role="status" aria-live="polite"></p>
                    </div>
                </section>
            </main>

            <footer class="site-footer">
                <span>Yutu <i></i> Descargas simples</span>
                <p>Usá esta herramienta únicamente con contenido que tengas derecho a descargar.</p>
            </footer>
        </div>

        <iframe id="download-target" title="Descarga de archivo" hidden></iframe>
    </body>
</html>
