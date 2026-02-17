# Research Review Portal – WordPress

This is a **WordPress port** of the Research Review Portal (CityU C4CYI). It runs entirely inside WordPress: no Node.js required.

## Install

1. Copy the entire `research-review-portal` folder into your WordPress `wp-content/plugins/` directory.
2. In **WP Admin → Plugins**, activate **Research Review Portal**.
3. Create a **Page** (e.g. “Research Portal”) and add the shortcode:
   ```
   [research_review_portal]
   ```
4. Publish and view the page. The portal will load (type selection, submit form, check status, public list).

## API

All endpoints are under the WordPress REST API:

- Base URL: `https://yoursite.com/wp-json/research-portal/v1`
- Endpoints: `GET /health`, `POST /submit`, `GET /submissions`, `GET /submissions/public`, `GET /submissions/{id}`, `PATCH /submissions/{id}`, `POST /submissions/{id}/feedback`, `POST /submissions/{id}/comments`, `GET/PUT /config`, `GET /reviewers`, `GET /reviews`.

Use these from the block editor, custom themes, or the included shortcode UI.

## Data

- **Submissions:** `wp-content/plugins/research-review-portal/data/submissions.json`
- **Reviewers:** `wp-content/plugins/research-review-portal/data/reviewers.json`
- **Config:** `wp-content/plugins/research-review-portal/data/config.json`
- **Uploads:** `wp-content/plugins/research-review-portal/data/uploads/` (per-submission folders)

To **migrate from the original Node project** (“For Cityu”):

1. Copy `data/submissions.json`, `data/reviewers.json`, and `data/config.json` from the original project into this plugin’s `data/` folder (overwrite if you want to bring existing data).
2. If you use file attachments, copy the contents of the original `data/uploads/` into this plugin’s `data/uploads/` (keep the same submission-id folder names).

Make sure the `data/` and `data/uploads/` directories are writable by the web server so new submissions and config changes can be saved.

## Features (vs Node version)

- **Included:** Submit (conference, publication, student-project, grant), validation, public list, “check my submissions” by email, config get/put, reviewers list, “my reviews” by reviewer email, withdraw and status updates, review stages and assignments (via API).
- **Not included in this plugin (yet):** File attachment upload/download in the UI (API for PATCH reviewStages, stageDecision, stageFeedback, etc. is in place for a future UI or custom client). For full coordinator/reviewer workflow UI (assign by stage, decisions, feedback), you can either build a custom WP front-end that calls the REST API or keep using the original Node + React app and point its API base URL to this WordPress REST base.

## Requirements

- WordPress 5.0+ (REST API)
- PHP 7.4+

## License

MIT (same as the original Research Review Portal).
