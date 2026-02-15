# PhaKhaoLao AI

PhaKhaoLao AI is a Laravel 12 app that provides a chat assistant for Laos biodiversity data.  
It can search species records, handle image-based identification requests, and export filtered species data to Excel.

## Features

- Chat UI with persisted conversations for guests and authenticated users.
- AI agent (`ChatAssistant`) with `SearchSpecies` (keyword + semantic retrieval/RAG) and `ExportSpecies` (Excel generation).
- Species scraping pipeline from `species.phakhaolao.la`.
- Embedding generation command for semantic search (`species:embed`).
- Local-only RAG settings page (`/settings/rag`) for runtime retrieval tuning.

## Tech Stack

- PHP `^8.2`
- Laravel `^12`
- PostgreSQL (required for vector embeddings support)
- `laravel/ai`
- `phpoffice/phpspreadsheet`
- Vite + Tailwind CSS

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

3. Configure database in `.env` (defaults target PostgreSQL):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=phakhaolao_ai
DB_USERNAME=postgres
DB_PASSWORD=
```

4. Add at least one AI provider key (OpenAI is default):

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

## Data Ingestion Workflow

1. Index and scrape species:

```bash
php artisan species:scrape --phase=all
```

2. Optional: backfill category fields for older rows:

```bash
php artisan species:scrape-categories
```

3. Generate embeddings for semantic search:

```bash
php artisan species:embed
```

Notes:
- `species:embed` requires PostgreSQL with vector column migration support.
- The embedding migration is skipped automatically for non-PostgreSQL drivers.

## Important Commands

- `php artisan species:scrape --phase=index --page-start=1 --page-end=81`
- `php artisan species:scrape --phase=detail --limit=200 --retry-failed`
- `php artisan species:scrape-categories --limit=200`
- `php artisan species:embed --chunk=25 --limit=0`
- `php artisan species:embed --dry-run`
- `php artisan test`

## Chat API Endpoints

- `GET /` and `GET /chat/{id?}`: chat UI.
- `POST /chat/send`: send message and stream assistant output.
- `POST /chat/save-response`: save streamed assistant response.
- `POST /chat/clear`: clear active conversation.
- `DELETE /chat/{id}`: delete a conversation.
- `GET /species/export-generated/{token}`: download generated export file.

## Request Limits

- Message max length: `5000` chars.
- Optional image upload: `jpg`, `jpeg`, `png`, `webp`, `gif`.
- Image max size: `10MB`.

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

Feature tests cover chat behavior, scraping commands, and species search/export tools.
