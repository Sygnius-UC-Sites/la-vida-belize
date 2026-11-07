{{-- COMPAT STUB.
     Phase 1 of the property-edit redesign split this kitchen-sink tab into
     four focused tabs (Pricing & taxes / Booking settings / House rules &
     cancellation / Rental agreement). The official property edit form
     (resources/views/admin/properties/form.blade.php) now includes those
     four partials directly and no longer references this file.

     This stub stays on disk so any other code path that did an
     `@include('admin.properties.sub.booking-rules')` continues to render
     all the same fields — just rendered through the four new partials in
     sequence. To remove permanently, delete this file once the staging
     deploy confirms nothing else references it.

     Plan: docs/superpowers/plans/2026-05-08-property-edit-redesign.md --}}
@include('admin.properties.sub.pricing-taxes')
@include('admin.properties.sub.booking-settings')
@include('admin.properties.sub.house-rules-cancellation')
@include('admin.properties.sub.rental-agreement')
