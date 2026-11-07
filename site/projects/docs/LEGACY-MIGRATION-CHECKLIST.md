# Legacy Website Migration Checklist

Create one checklist and one independent base-template copy per legacy website.

## Discovery

- [ ] Inventory application code, database, routes, pages, forms, assets, analytics, SEO metadata, redirects, custom functions, and integrations.
- [ ] Capture desktop and mobile screenshots for every public page and booking step.
- [ ] Export URL, redirect, metadata, form, and custom-behavior matrices.
- [ ] Record current property-to-PMS identifiers without publishing anything.

## Design Boundary

The developed legacy design must remain unchanged through:

- [ ] Public website pages.
- [ ] Property pages.
- [ ] Booking widget.
- [ ] Immediate Get Quote or quote-results page.

The preserved quote page must receive normalized live CRM/PMS availability and pricing. Only after that page submits may the hardened shared booking, payment, confirmation, communication, and admin flows take over.

## Migration

- [ ] Start from a copy of the approved base template.
- [ ] Use an independent database, credentials, integration configuration, and test environment.
- [ ] Preserve URLs, metadata, redirects, content, analytics, and required custom behavior.
- [ ] Add missing pages/functions using hardened CRM patterns without redesigning preserved pages.
- [ ] Link each property explicitly; provider synchronization must never publish a page.
- [ ] Create visual-regression baselines for desktop and mobile.

## Acceptance And Rollback

- [ ] Public, quote, checkout, payment, confirmation, email, and admin journeys pass.
- [ ] SEO crawl and redirect comparison pass.
- [ ] Integration sandbox matrix passes for the providers enabled on this site.
- [ ] Deployment checklist, backup, restore proof, rollback package, and rollback trigger are recorded.
- [ ] Site owner approves the preserved design and migrated behavior.
