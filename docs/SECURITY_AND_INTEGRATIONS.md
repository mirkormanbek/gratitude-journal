# Security and integrations

## Secrets
Production secrets must remain outside committed source:
- OpenAI API key
- Quran Foundation Client ID/Client Secret
- MariaDB password

Use production `config.php` or environment variables. Keep only `config.example.php` in git.

## Authentication
Existing app uses PHP sessions and login. Preserve auth checks on private endpoints.

## CSRF
All state-changing endpoints should require the current CSRF token.

## OpenAI privacy behavior
Diary data is not automatically sent wholesale to OpenAI.
User explicitly chooses an analysis action/scope, e.g.:
- today;
- last 7 days;
- latest CEO Review;
- latest capital entry.

Only the selected context should be included in the request.

## Quran Foundation
Use backend OAuth2 Client Credentials.
Do not expose Client Secret/access token to browser.
Arabic Quran text comes from a trusted source, not model generation.

## Tafsir labeling
Never imply a source is official QMDB/Muftiyat Kazakhstan unless it actually is.
When the exact QMDB verse-level digital source is unavailable:
- use a transparently named classical tafsir if appropriate;
- AI may produce a concise Russian summary based on retrieved tafsir;
- label it as a summary based on that source.
