# Internal vendor reference

Developer-only reference. Maps the **generic names** used in admin UI, env vars,
and config to the **actual underlying vendors** so you know what to provision
in each customer install. Keep this file out of any customer-visible deploy
(it's already under `docs/`, not served publicly, but worth noting).

| Generic name in UI / env | Actual vendor | Where to provision |
|---|---|---|
| **Mail / Outbound mail / "Mail" sidebar entry** | Amazon SES (API, not SMTP) | aws.amazon.com → SES |
| `MAILER_KEY_ID` / `MAILER_SECRET` / `MAILER_REGION` | AWS IAM access key for SES | IAM → Users → Security credentials |
| `MAIL_FROM_EMAIL` / `MAIL_DOMAIN` | Verified SES identity | SES → Identities |
| `MAIL_CONFIGURATION_SET` | SES configuration set name | SES → Configuration sets |
| **SNS topic** for bounces/complaints | AWS SNS | SNS → Topics → subscribe to `/webhooks/ses` |
| | | |
| **Listing imports / "Listing Imports" sidebar entry** | Apify | console.apify.com |
| `IMPORTER_TOKEN` | Apify Personal API token | Console → Settings → Integrations |
| `IMPORTER_ACTOR_AIRBNB` | tri_angle/airbnb-rooms-urls-scraper | Apify Store |
| `IMPORTER_ACTOR_AIRBNB_REVIEWS` | tri_angle/airbnb-reviews-scraper | Apify Store |
| | | |
| **Bot challenge / Spam Protection page** | Cloudflare Turnstile (invisible CAPTCHA) | dash.cloudflare.com → Turnstile |
| `BOTGUARD_SITE_KEY` / `BOTGUARD_SECRET_KEY` | Turnstile widget keys | Cloudflare → Turnstile → Site |

## Why renamed

Customers / admin users shouldn't be able to identify the upstream services
from the admin UI, env file, or HTML form-field names. Vendor names tell them
what they could replicate themselves; generic names obscure the stack.

## Backwards compatibility

The config layer reads new names first, falls back to old vendor-specific
names. Existing installs that have `AWS_ACCESS_KEY_ID` / `APIFY_API_TOKEN` /
`TURNSTILE_SITE_KEY` keep working unchanged. New installs should use the
generic names from `.env.example`.

## What CAN'T be hidden

Two unavoidable leaks (front-end visitor source-view):

1. **Turnstile script tag** — `<script src="https://challenges.cloudflare.com/turnstile/v0/api.js">`
   loads from Cloudflare. The widget div has a `cf-turnstile` class (Cloudflare
   reads it). Hiding requires proxying through your own domain — meaningful
   work, not yet done.
2. **AWS SES bounce webhook** — `/webhooks/ses` accepts SNS notifications. The
   route name is generic but a curious admin reading routes/web.php sees `ses`.

These appear only to people viewing HTML source / route file — not in the
admin UI proper.
