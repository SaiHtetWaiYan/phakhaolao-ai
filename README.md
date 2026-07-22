# PhaKhaoLao AI

PhaKhaoLao AI is a Laravel 12 app that provides a bilingual (Lao/English) chat assistant for the
PhaKhaoLao agrobiodiversity catalogue. It answers from four curated data sources, handles
image-based species identification, speaks its answers aloud, accepts voice input, and exports
filtered species data to Excel.

## Features

- Bilingual chat UI (Lao/English) with persisted conversations for guests and authenticated users.
- AI agent (`ChatAssistant`) backed by tools over four sources:
  - **Species** — search, property filtering, exact counts, semantic retrieval (RAG), Excel export.
  - **Champions** — people and organisations recognised in Lao agrobiodiversity, with breakdowns.
  - **Library** — publications and reports, including full-text search inside the PDFs.
  - **Stories** — articles, filterable by story type.
- **Text-to-speech**: Google Gemini-TTS (the practical option for Lao), with edge-tts fallback,
  progressive playback and on-disk caching.
- **Speech-to-text**: Google Cloud Speech-to-Text, with confidence-based Lao/English auto-detection.
- Nightly scheduled syncs for all WordPress-sourced data.
- Local-only RAG settings page (`/settings/rag`) for runtime retrieval tuning.
- Optional per-user daily message limit.

## Tech Stack

- PHP `^8.2`
- Laravel `^12`
- PostgreSQL with `pgvector` (required for embeddings)
- `laravel/ai` (OpenAI by default)
- Google Cloud (Vertex AI for TTS, Speech-to-Text for STT)
- `phpoffice/phpspreadsheet`
- Vite + Tailwind CSS v4

## Quick Start

1. Install dependencies:

```bash
composer install
npm install
```

2. Create env file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database in `.env` (PostgreSQL is required):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=phakhaolao_ai
DB_USERNAME=postgres
DB_PASSWORD=
```

4. Add at least one AI provider key (OpenAI is the default provider):

```env
OPENAI_API_KEY=your_key_here
```

5. Run migrations and build assets:

```bash
php artisan migrate
npm run build
```

6. Start local development:

```bash
composer run dev
```

## Data Sources & Ingestion

Species come from a separate source database; the other three sync over the PhaKhaoLao WordPress
REST API.

### Species (source database)

Species are imported from the `pkl` PostgreSQL connection, configured via the `PKL_DB_*` variables.
Restore the source dump into that database first, then:

```bash
php artisan species:import
php artisan species:embed
```

`species:embed` requires PostgreSQL with vector support; the embedding migration is skipped
automatically on other drivers. Embeddings are only generated for records missing one unless
`--all` is passed.

### Champions, library and stories (WordPress REST API)

```bash
php artisan champions:import
php artisan library:import
php artisan stories:import
```

These are idempotent — re-running only writes changes.

### Library PDF full-text search

Downloads library PDFs, extracts their text and embeds it so the assistant can answer from document
contents:

```bash
php artisan library:index-pdfs
```

Uses `pdftotext` (poppler-utils) when available, since it streams within bounded memory, and falls
back to `smalot/pdfparser` otherwise. Installing poppler-utils is strongly recommended.

## Scheduled Syncs

Nightly syncs are staggered overnight and configured in `config/sync.php`, so enabling, moving or
disabling one is an env change rather than a deploy.

| Time (`SYNC_TIMEZONE`) | Command | Default |
| --- | --- | --- |
| 01:30 | `species:import` | off |
| 02:00 | `champions:import` | on |
| 02:15 | `stories:import` | on |
| 02:30 | `library:import` | on |
| 03:00 | `species:embed` | on |

```env
SYNC_TIMEZONE=Asia/Vientiane
SYNC_CHAMPIONS=true
SYNC_STORIES=true
SYNC_LIBRARY=true
SYNC_EMBED=true
SYNC_SPECIES=false
```

The species sync stays dormant until its source database is reachable: it is skipped unless
`SYNC_SPECIES=true` *and* the `pkl` connection answers, so enabling it early is harmless.

Each job runs in the background without overlapping, appends output to `storage/logs/schedule.log`,
and logs failures. Verify the plan with:

```bash
php artisan schedule:list
```

The host needs the usual Laravel cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Important Commands

- `php artisan species:import --dry-run`
- `php artisan species:embed --chunk=25 --limit=0`
- `php artisan champions:import` / `library:import` / `stories:import`
- `php artisan library:index-pdfs --limit=50 --retry-failed`
- `php artisan species:export-all` / `species:export-scientific-names` / `species:export-ai`
- `php artisan exports:cleanup` / `tts:clear-cache`
- `php artisan test`

## HTTP Endpoints

- `GET /` and `GET /chat/{id?}`: chat UI.
- `POST /chat/send`: send a message and stream assistant output.
- `POST /chat/save-response`: save the streamed assistant response.
- `POST /chat/clear`: clear the active conversation.
- `DELETE /chat/{id}`: delete a conversation.
- `POST /tts`: synthesise speech for a reply.
- `POST /transcribe`: transcribe recorded audio (`en`, `lo`, or `auto`).
- `GET /species/export-generated/{token}`: download a generated export file.
- `GET /settings/rag`: local-only retrieval settings screen.

## Request Limits

- Message max length: `5000` chars.
- Optional image upload: `jpg`, `jpeg`, `png`, `webp`, `gif`; max `10MB`.
- Audio upload for transcription: max `15MB`.
- Optional daily message cap per user (`0` disables it):

```env
CHAT_DAILY_MESSAGE_LIMIT=0
CHAT_LIMIT_TIMEZONE=Asia/Vientiane
```

## Speech Configuration

Text-to-speech and speech-to-text both use a Google service account (API keys cannot call Vertex
AI). Point `GOOGLE_APPLICATION_CREDENTIALS` at the JSON key and enable the Vertex AI and Cloud
Speech-to-Text APIs.

```env
TTS_PROVIDER=google
GOOGLE_APPLICATION_CREDENTIALS=storage/app/google/tts-credentials.json
GOOGLE_TTS_MODEL=gemini-2.5-flash-tts
GOOGLE_TTS_VOICE=Achernar
```

Setting `TTS_PROVIDER=edge` falls back to edge-tts, which requires `edge-tts` on the host.

## RAG Settings

Default retrieval settings are controlled by env and can be overridden in app settings:

```env
RAG_MIN_SIMILARITY=0.35
RAG_SEMANTIC_LIMIT=6
RAG_KEYWORD_LIMIT=8
```

The local-only settings screen is available at `GET /settings/rag`.

## Testing

Run all tests:

```bash
php artisan test
```

Feature tests cover chat behaviour, the import commands, the scheduled syncs, speech endpoints, and
the species, champion, library and story search tools. A few tests that depend on PostgreSQL-only
SQL are skipped on other drivers.
