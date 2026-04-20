# Coqui Toolkit Images

`carmelosantana/coqui-toolkit-images` adds AI image generation and image-library tools to Coqui.

## Features

- Text-to-image generation with OpenAI (`gpt-image-1`) and Ollama CLI backends
- Workspace image library with JSON index at `workspace/images/index.json`
- PNG tEXt metadata embedding (prompt, vendor, model, profile, owner, tags, category)
- Low-fidelity colored block previews via `ext-gd` (optional)
- Atomic index writes to prevent corruption
- Workspace path boundary enforcement
- Configurable OpenAI timeout

## Tools

| Tool | Description |
|---|---|
| `image_preflight` | Check readiness before generation (credentials, model availability) |
| `image_generate` | Generate and save an image |
| `image_preview` | Render an existing workspace image as a low-fidelity REPL preview |
| `image_library` | List, search, get, tag, delete, and inspect saved images |
| `image_config` | Inspect the resolved image toolkit configuration |

## REPL Commands

When used with Coqui core, the `/image` slash command provides:

```
/image generate <prompt> [--model=vendor/model] [--vendor=openai|ollama] [--tags=a,b] [--category=name]
/image preview <path> [--width=60]
/image list [--profile=name] [--vendor=openai|ollama]
/image search <query> [--category=name]
/image get <record-id>
/image tag <record-id> <tag1,tag2> [--category=name]
/image delete <record-id>
/image config
```

## Configuration

The toolkit resolves its defaults from Coqui context when available, then environment variables:

| Variable | Purpose |
|---|---|
| `COQUI_IMAGE_MODEL` | Fallback image model in `vendor/model` format |
| `OPENAI_API_KEY` | Required for OpenAI image generation |
| `OLLAMA_HOST` | Optional local Ollama host hint |
| `COQUI_WORKSPACE` | Fallback workspace path when no Coqui context is provided |
| `COQUI_ACTIVE_PROFILE` | Fallback active profile name when no Coqui context is provided |

OpenAI vendor settings (via `openclaw.json` `agents.defaults.imageConfig.vendors.openai`):

- `apiKey` — API key override
- `baseUrl` — API base URL override
- `timeout` — HTTP request timeout in seconds (default: 240)

Fallback model resolution: if no model is configured, the toolkit infers one — preferring `openai/gpt-image-1` when `OPENAI_API_KEY` is set, or `ollama/gemma3` when the Ollama CLI is available.

Default output path:

```
workspace/images/{profile-name}/{file-hint}-{timestamp}.png
```

Generated-image responses and `image_preview` both emit ANSI-colored block previews using the brightness ramp `░ ░ ▒ ▓ █`, so the REPL can show a low-resolution impression of the image without opening an external viewer.

## Development

```bash
composer install
composer test
composer analyse
```
