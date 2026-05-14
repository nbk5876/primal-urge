# Camio

AI-powered cat video generator for **Primal Urge Cat Rescue** (West Seattle).

Admin user Mary Wood uploads a photo of a cat, picks a background scene, and Runway AI generates a short video of that cat moving through the scene. Finished videos are published to a public gallery where visitors can browse adoptable cats.

**Live URLs**
- Public gallery: https://www.core3.com/PrimalUrge/camio/
- Admin create screen: https://www.core3.com/PrimalUrge/camio/admin.html
- Backend API: https://camio-api.vercel.app

---

## Architecture

```
Browser (admin)
    │  POST /api/generate  (image + background prompt)
    │  GET  /api/status    (poll until video ready)
    │  POST /api/publish   (save to gallery)
    ▼
Vercel Serverless Functions  (camio-api/)
    │  Runway Gen-3 Alpha Turbo API  →  generates mp4
    │  Vercel Blob                   →  stores gallery.json
    ▼
Browser (public gallery)
    │  GET /api/gallery
    │  renders video cards
```

**Frontend** is static HTML/CSS/JS deployed from this repo via GitHub Actions → DreamHost SFTP.

**Backend** is a set of Vercel serverless functions in `camio-api/`. Deployed separately to Vercel.

---

## Repository Layout

```
PrimalUrge/
├── camio/                        # Frontend — deployed to core3.com/PrimalUrge/camio/
│   ├── index.html                # Public gallery (loads from /api/gallery)
│   ├── admin.html                # Mary's create screen (v1.5)
│   ├── saphire.html              # Individual cat profile page (4-photo grid)
│   └── image/                   # Cat photos (static assets)
│
└── camio-api/                    # Backend — deployed to Vercel
    ├── api/
    │   ├── generate.js           # POST — submit Runway generation task
    │   ├── status.js             # GET  — poll task until video is ready
    │   ├── publish.js            # POST — save completed video to gallery
    │   └── gallery.js            # GET  — return all published cats
    ├── vercel.json               # CORS + Node 20 config
    └── package.json              # ESM, @vercel/blob dependency
```

---

## Key Flows

### Create a video (admin)
1. Mary opens `admin.html`, uploads a cat photo, enters cat name/details, picks a background scene.
2. "Generate" sends `POST /api/generate` → `imageBase64` + `backgroundPrompt` → Runway `gen3a_turbo`.
3. Runway returns a `taskId`. The frontend polls `GET /api/status?taskId=…` every few seconds.
4. When `status === SUCCEEDED`, the video URL is returned and plays in the page.
5. Mary clicks "Publish" → `POST /api/publish` saves the cat record to `gallery.json` in Vercel Blob.

### Public gallery
- `index.html` calls `GET /api/gallery` on load, receives the array of published cat records, and renders video cards with name, breed, adoption fee, and notes.

---

## API Reference

| Endpoint | Method | Body / Query | Returns |
|----------|--------|-------------|---------|
| `/api/generate` | POST | `{ imageBase64, backgroundPrompt, catName }` | `{ taskId, status: "pending" }` |
| `/api/status` | GET | `?taskId=…` | `{ status, videoUrl, progress, failure }` |
| `/api/publish` | POST | `{ catName, breed, fee, notes, videoUrl }` | `{ success: true }` |
| `/api/gallery` | GET | — | `{ cats: [...] }` |

**Runway task status values:** `PENDING` · `RUNNING` · `SUCCEEDED` · `FAILED`

If generation fails, `failure` contains the Runway rejection reason (e.g., content moderation message).

---

## Video Generation Details

- **Model:** Runway Gen-3 Alpha Turbo (`gen3a_turbo`)
- **Duration:** 10 seconds
- **Ratio:** 768 × 1280 (portrait)
- **Prompt pattern:** `"A cute cat walking and exploring joyfully through {backgroundPrompt}. Playful, whimsical motion. High quality."`
- **Cost:** ~5 credits/sec → 50 credits per video · ~$0.50 per video at $0.01/credit
- **Content moderation:** Avoid words like "dancing" — triggers Runway's moderation filter.

---

## Setup

### Prerequisites
- Node 20+
- [Vercel CLI](https://vercel.com/docs/cli): `npm i -g vercel`
- A Runway API key (paid plan required — free tier has no API access): [dev.runwayml.com](https://dev.runwayml.com)

### Backend (Vercel)

```bash
cd camio-api
vercel link          # link to the camio-api Vercel project
vercel env add RUNWAY_API_KEY    # paste the Runway API key when prompted
vercel --prod        # deploy to production
```

The `RUNWAY_API_KEY` environment variable must be set in the Vercel project. It is **not** committed to this repo.

### Frontend

No build step. The static files in `camio/` are deployed automatically when you push to `main` — GitHub Actions handles the SFTP transfer to DreamHost. Changes are live within 2–3 minutes.

To confirm a frontend deploy, increment the version badge in `admin.html` and check that it appears on the live page.

---

## Deploying

### Frontend (core3.com)
```bash
git add camio/
git commit -m "your message"
git push origin main
# GitHub Actions deploys automatically — live in ~2 min
```

### Backend (Vercel)
```bash
vercel --prod --cwd camio-api
```

---

## Services & Accounts

| Service | Purpose | Where to manage |
|---------|---------|----------------|
| GitHub — `nbk5876/primal-urge` | Source repo + CI/CD for frontend | github.com/nbk5876/primal-urge |
| DreamHost | Hosts core3.com frontend | Managed via GitHub Actions SFTP secret |
| Vercel — `tonys-projects-338d447d` | Hosts serverless API | vercel.com/tonys-projects-338d447d |
| Vercel Blob | Stores `gallery.json` (published cats) | Attached to camio-api Vercel project |
| Runway | AI video generation | dev.runwayml.com |

---

## Collaborators

| Name | Role | GitHub |
|------|------|--------|
| Tony Byorick | Developer | [nbk5876](https://github.com/nbk5876) |
| Max | Developer | TBD |
| Mary Wood | Admin user (Primal Urge) | — |
