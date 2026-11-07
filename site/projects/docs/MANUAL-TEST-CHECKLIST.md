# Manual test checklist

_Last machine-test: see `BOOKING-SCENARIOS-REPORT.md`_

This is the post-deploy / pre-release pass to do by hand once mailer keys
and gateway keys are filled in. Each item should be testable without
specialised tooling — just a browser, the admin login, and an inbox.

## Pre-flight setup

- [ ] `/admin/settings/mail` — Provider keys saved, **Test connection** says "Authenticated as: …"
- [ ] `/admin/settings/payment-gateways` — At least one gateway has live (or test) keys
- [ ] `/admin/setting` → main tab — Site email + From email set to your real addresses
- [ ] Owner email (`info@…`) saved in `booking_receiving_mail` setting
- [ ] `/admin/settings/spam-protection` — honeypot + time-trap on; bot challenge optional

## Public-facing property page

For every active property, hit `/condos/{slug}` or `/houses/{slug}` and confirm:

- [ ] Hero gallery shows 5 photos + **Show all photos (N)** button opens the full set
- [ ] Description renders as prose (no `<br>` tags showing as text)
- [ ] Sleep cards section populated (1 card per bedroom)
- [ ] Amenities inline tile (6 items) + **View all amenities** drawer (full list)
- [ ] Highlights chips visible
- [ ] Map iframe loads (Google Maps embed)
- [ ] Reviews section: aggregate stars + recent published reviews; offcanvas drawer with all
- [ ] Booking quote-form has add-on checkboxes for every property fee

## Booking flow — happy path

1. Pick dates that satisfy the property's min-stay / Sun-checkout rules
2. Select adults + children + pets matching property capacity
3. Tick a few add-on fees (cleaning, hot tub, beach gear)
4. Apply coupon code `TEST10` if you want to verify discount line
5. Submit — should redirect to "Booking request received" page

**Verify:**

- [ ] Renter inbox receives **"We received your enquiry — {property}"** within seconds
- [ ] Owner inbox receives **"[New enquiry] {property} — {guest_name}"**
- [ ] `/admin/booking-enquiries` list shows the new row with: total amount, dates, **Pending approval** pill
- [ ] Click View → modal shows full breakdown (nights, fees, tax, deposit, total)

## Booking flow — approval + payment

From the booking-enquiries modal:

1. Click **Approve** (or update status to "booking-accepted")
2. Click **Send payment link** (or use a real gateway test card)

**Verify:**

- [ ] Renter receives **"Complete your booking — {property}"** with payment CTA
- [ ] Owner receives **"[Payment pending] #N"** alert
- [ ] After test payment posts, status flips to **booked / paid**
- [ ] **Renter receives Receipt** with: big green "Amount paid: $X" hero, payment date, method, transaction ID, outstanding="Paid in full"
- [ ] **Owner receives Receipt copy** "[Payment received $X] #N"
- [ ] Renter receives **"Booking confirmed — {property} ({dates})"**
- [ ] Owner receives **"[Booking confirmed] #N"**

## Lifecycle emails (admin-triggered or scheduled)

For each, click the matching **Resend** button on `/admin/booking-enquiries/{id}` modal:

- [ ] **Welcome package** — Renter sees "Welcome to {property} — your stay begins {date}". Owner gets sent-confirmation alert.
- [ ] **Check-in details** — Renter sees check-in instructions, door code, wifi. Owner gets sent-confirmation alert.
- [ ] **Check-out reminder** — Renter sees pre-departure checklist. Owner gets sent-confirmation alert.
- [ ] **Review request** — Renter sees "How was your stay at {property}?" with review CTA. Owner gets sent-confirmation alert.

## Multi-instalment payments

1. Create a booking. Approve.
2. Mark payment_status as `pending`.
3. Record a partial payment (50% of total) via admin or `php artisan tinker`:
   ```php
   $b = BookingRequest::find(<id>);
   $pay = Payment::create(['booking_id'=>$b->id,'tran_id'=>'test1','amount'=>$b->total_amount/2,'type'=>'stripe','status'=>'complete']);
   ModelHelper::finalEmailAndUpdateBookingPayment($b->id,$b,$pay,Property::find($b->property_id),'payment');
   ```

**Verify after each instalment:**

- [ ] Receipt email shows correct **partial amount**
- [ ] Receipt's "Outstanding balance" line shows **`$X remaining`**
- [ ] Booking status stays `partially` (not `paid`) until last payment

After final payment:
- [ ] Receipt shows **"Paid in full"**
- [ ] payment_status = `paid`

## Cancellation

1. Open a confirmed booking in admin
2. Click cancel / change status to `cancelled`

**Verify:**

- [ ] Renter receives **"Your booking at {property} has been cancelled"** with refund-timeline note
- [ ] Owner receives **"[Cancelled] #N"** alert
- [ ] Booking row in admin shows **Cancelled** pill
- [ ] iCal export at `/ical/{property_id}` no longer includes this booking

## Validation rejections (should be REJECTED at quote)

- [ ] Stay shorter than property's `min_stay` → "Min stay X nights not met" message
- [ ] Dates overlapping an existing booking → "Selected dates include unavailable nights"
- [ ] Property requires Sunday check-out and your check-out isn't Sunday → date error
- [ ] Booking with all fees + coupon → quote shows correct subtotal/tax/discount/total

## Spam protection

- [ ] Submit contact form < 2s after page load → silently dropped (no row in `contactus_requests`)
- [ ] Submit 6 contact forms in a minute from same IP → 6th gets "Too many requests"
- [ ] Submit booking with `email=foo@mailinator.com` → rejected (disposable email)
- [ ] If Cloudflare Turnstile enabled: visit form, widget loads in iframe; submit fails when token is forged

## iCal calendar

- [ ] `/ical/{property_id}` returns valid VCALENDAR with all non-cancelled bookings
- [ ] Subscribe Airbnb / VRBO to that URL — confirmed bookings block the dates on their side
- [ ] Import an Airbnb iCal feed via `/admin/properties/{id}/edit` → Calendar tab → coloured cells appear in the chosen colour

## Listing imports (Apify)

- [ ] `/admin/scrape-sources` → Settings card → **API token** stored, **Test connection** says "Authenticated as: …"
- [ ] Paste an Airbnb URL → click Import → row goes to "scraping…"
- [ ] After Apify completes (or click **Finalise import**), row says "imported", linked Property created with status=draft
- [ ] Click View raw → see captured JSON
- [ ] Click Re-map → property fields refreshed without a new Apify run
- [ ] Click Import reviews (if Max reviews > 0) → reviews land, **Reviews (N)** button appears
- [ ] Open Reviews → bulk-publish a few → they appear on the property's public detail page

## Auto-test commands (re-runnable)

```bash
# All booking scenarios + write report
php artisan booking:scenarios-test --report=docs/BOOKING-SCENARIOS-REPORT.md

# Single happy-path with full email firing
php artisan booking:full-cycle --property=2

# Quick simulate N bookings
php artisan booking:simulate --property=4 --count=3

# Refill any blanks on a fresh property
php artisan properties:fill-blanks
php artisan properties:fill-related
```

## Current CMS scenario notes

- ✅ **Overcapacity validation now exists** — the latest stored scenario report rejects a 999-guest quote with a max-capacity message.
- ⚠ **Min-stay is intentionally data-driven** — properties with `min_stay = null` or no PMS minimum can accept short stays. Set min-stay per property or through PMS/rate data when shorter stays should be rejected.
- ⚠ **This checklist covers the legacy/native booking route** — PMS-backed public flows use the `/pms/*` quote and payment routes, so PMS certification evidence belongs in the go-live/comprehensive audit reports.

## Where rendered email HTML lives for inspection

- Customer + owner emails: `email_send_log.rendered_html` — open the booking's modal and click **👁️ Preview** next to each email type. The bytes shown are the exact bytes that went out.
- Failures (gateway timeouts etc.): `email_send_log.error` column.
- SES sends specifically: `mail_send_log` (status, bounce/complaint flags). Visible at `/admin/settings/mail` → "Recent sends" table.
