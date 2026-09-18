# Chat IA

A full-stack chat application built with Laravel 13, Vue 3, and Inertia.js. Supports multiple LLM models via OpenRouter, real-time streaming responses, a RAG (Retrieval-Augmented Generation) knowledge base, and an admin panel for usage management.

## Stack

- **Backend:** PHP 8.3, Laravel 13, Laravel Sanctum
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS, Reka UI
- **Database:** MySQL 9.0+
- **Queue:** Laravel Jobs (document processing)
- **Embeddings:** Ollama (local embeddings with nomic-embed-text model)
- **PDF export:** barryvdh/laravel-dompdf
- **Document parsing:** smalot/pdfparser, phpoffice/phpword

## Features

### Chat
- Real-time streaming responses via Server-Sent Events (SSE)
- Multiple LLM model selection per message (via OpenRouter)
- Configurable temperature and max token parameters
- File attachment support (PDF, DOCX, TXT parsed server-side)
- Pinned conversations
- Conversation search
- Export conversations as Markdown or PDF
- Share conversations via public or restricted token links
- Message comments/annotations

### Knowledge Base (RAG)
- Create named knowledge collections
- Upload documents (PDF, DOCX, TXT) to a collection
- Documents are chunked and embedded asynchronously via queued jobs
- Cosine similarity search against stored embeddings at query time
- Source attribution shown below responses ("Fontes utilizadas")

### Saved Prompts
- Create, edit, and delete reusable prompt templates
- Accessible from the message input

### Admin Panel
- Per-user token and cost usage limits
- Reset individual user usage
- Audit log viewer with CSV export
- Usage limits dashboard

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 9.0+
- An [OpenRouter](https://openrouter.ai) API key
- [Ollama](https://ollama.ai)

## Installation

```bash
git clone <repo-url>
cd chat-ia

composer install
npm install
```

Install Ollama from https://ollama.ai, then pull the embedding model:

```bash
ollama pull nomic-embed-text
```

On macOS, start Ollama as a background service:

```bash
brew services start ollama
```

Copy and configure your environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env`:

```env
OPENROUTER_API_KEY=your_key_here
OLLAMA_URL=http://localhost:11434
```

Run migrations and start the development server:

```bash
php artisan migrate
php artisan queue:work &
npm run dev
php artisan serve
```

## Testing

```bash
php artisan test
```

## Environment Variables

| Variable | Description |
|---|---|
| `OPENROUTER_API_KEY` | API key for OpenRouter model access |
| `OLLAMA_URL` | URL for local Ollama embedding service |
| `QUEUE_CONNECTION` | Set to `database` for persistent job queuing (default: `sync`) |

## Project Structure

```
app/
  Http/Controllers/     # API and page controllers
  Jobs/                 # Queued document processing jobs
  Models/               # Eloquent models
  Services/
    OpenRouterService   # Streaming LLM calls
    EmbeddingService    # Text embedding via Ollama (local)
    RagService          # Cosine similarity search
resources/js/
  Pages/                # Inertia page components
  Components/Chat/      # Chat UI components
  Components/Knowledge/ # Knowledge base UI components
tests/
  Feature/              # HTTP and integration tests
```
