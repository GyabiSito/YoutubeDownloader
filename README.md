# Yutu

Yutu es una aplicación Laravel deliberadamente pequeña para analizar una URL de YouTube y descargar un video MP4 en una calidad disponible o extraer su audio como MP3. No tiene usuarios, historial, base de datos, playlists ni procesamiento en segundo plano.

## Arquitectura

```text
Blade + JavaScript vanilla
        ↓ fetch
POST /download/prepare
        ↓
YoutubeDownloaderService → Symfony Process → yt-dlp + ffmpeg
        ↓ JSON con token
GET /download/{token} → attachment nativo
```

`POST /video-info` devuelve sólo título, canal, miniatura, duración y alturas de video deduplicadas. `POST /download/prepare` vuelve a validar la URL y la calidad, prepara el archivo completo en un directorio UUID y devuelve un token temporal. Después, `GET /download/{token}` consume ese token una sola vez y transmite el archivo como attachment sin cargarlo completo en memoria.

La asociación token → archivo usa el cache de archivos de Laravel, sin base de datos ni Redis. El loader termina cuando la preparación devuelve el token, antes de abrir el diálogo nativo de guardado. El archivo y su directorio temporal se eliminan al terminar la respuesta; los archivos preparados pero nunca solicitados se eliminan mediante la limpieza oportunista o el comando `downloads:cleanup`.

## Requisitos

- PHP 8.4.1 o superior con las extensiones requeridas por Laravel 13.
- Composer 2.
- Node.js 22 o superior y npm.
- `yt-dlp` actualizado y disponible en `PATH`.
- `ffmpeg` disponible en `PATH`.
- Deno 2.3 o superior, requerido por el solucionador JavaScript actual de YouTube.
- Un proveedor de PO Tokens compatible cuando YouTube los exige para las URLs de video.

Comprobación manual:

```bash
yt-dlp --version
ffmpeg -version
deno --version
```

Instalación de las herramientas:

- Windows: `winget install yt-dlp.yt-dlp`, `winget install Gyan.FFmpeg` y `winget install DenoLand.Deno`.
- macOS: `brew install yt-dlp ffmpeg deno`.
- Ubuntu/Debian: instalar `ffmpeg`, Deno y `yt-dlp[default]` mediante los mecanismos recomendados por cada proyecto.

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

En PowerShell, el equivalente para copiar el entorno es:

```powershell
Copy-Item .env.example .env
```

No se necesita ejecutar migraciones. Las sesiones usan cookies cifradas y el rate limiting usa archivos, por lo que el flujo principal no abre una conexión de base de datos.

Durante desarrollo, ejecutar en terminales separadas:

```bash
php artisan serve
npm run dev
```

Para servir assets compilados:

```bash
npm run build
php artisan serve
```

La aplicación queda disponible por defecto en `http://127.0.0.1:8000`.

## Variables de entorno

```dotenv
YT_DLP_BINARY=yt-dlp
FFMPEG_BINARY=ffmpeg
DENO_BINARY=deno
YOUTUBE_POT_PROVIDER_URL=http://pot-provider:4416
YOUTUBE_INFO_TIMEOUT=45
YOUTUBE_DOWNLOAD_TIMEOUT=1800
YOUTUBE_PREPARED_TTL=10
YOUTUBE_CLEANUP_AFTER=120
```

Las dos primeras variables pueden ser rutas absolutas. Esto es útil en Windows, por ejemplo:

```dotenv
YT_DLP_BINARY=C:\Users\usuario\bin\yt-dlp.exe
FFMPEG_BINARY=C:\ffmpeg\bin\ffmpeg.exe
DENO_BINARY=C:\Users\usuario\.deno\bin\deno.exe
```

Los timeouts están expresados en segundos. `YOUTUBE_PREPARED_TTL` y `YOUTUBE_CLEANUP_AFTER` están expresados en minutos. El directorio `storage/app/downloads` debe ser escribible por PHP.

Para forzar manualmente la limpieza de descargas abandonadas:

```bash
php artisan downloads:cleanup
```

## Docker

El contenedor multi-stage compila Vite, instala dependencias Composer sin paquetes de desarrollo e incluye la versión nightly de `yt-dlp[default]`, el plugin `bgutil-ytdlp-pot-provider`, ffmpeg y Deno. Compose levanta además el proveedor de PO Tokens como sidecar privado y configura `visionos` para usar los formatos HLS que YouTube entrega de forma estable; el puerto del sidecar no se publica en el host.

Primero crear `.env` y generar una `APP_KEY`; después:

```bash
docker compose up --build
```

La aplicación queda en `http://localhost:8000`. El compose no levanta bases de datos, Redis ni workers.

## Seguridad y límites

- Sólo se aceptan URLs HTTP/HTTPS de hosts conocidos de YouTube.
- Los argumentos se entregan a Symfony Process como una lista; nunca se concatena input en un comando shell.
- No se soportan playlists ni streams activos.
- La calidad recibida se limita y se contrasta nuevamente con los formatos reales antes de descargar.
- Cada operación usa un directorio temporal UUID independiente.
- Los PO Tokens se generan automáticamente por video; no se almacenan cookies ni credenciales de YouTube.
- Los endpoints tienen throttling básico de Laravel.
- La arquitectura es síncrona. Varias descargas 4K simultáneas pueden consumir bastante disco, CPU y ancho de banda.
- El usuario es responsable de descargar únicamente contenido sobre el que tenga derechos o permiso.

YouTube cambia con frecuencia; mantener `yt-dlp` actualizado es parte del mantenimiento operativo de la aplicación.

## Validación

El proyecto incluye pruebas pequeñas para la regla de URL, simplificación de metadata, deduplicación de calidades, rechazo de lives y respuestas básicas de los endpoints. No requieren acceso a internet.

```bash
php artisan test
npm run build
```
