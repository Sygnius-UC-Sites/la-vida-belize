{{-- Quick-insert merge-token bar.
     Renders a chip toolbar above a CKEditor textarea. Each chip, when clicked,
     inserts the corresponding merge token (e.g. {{guest_name}}) at the
     cursor position inside the editor.

     Phase 3 of the rental-agreement rebuild
     (plan: docs/superpowers/plans/2026-05-08-rental-agreement-rebuild.md).

     Usage from a Blade view:
         @include('admin.settings._partials.merge-token-bar', ['for' => 'template_html'])

     The `for` value is the textarea id — must match what CKEditor.replace was
     called with.

     Tokens are grouped (Guest / Trip / Property / Brand) so the bar is
     scannable; the JS wiring lives in merge-token-bar-js.blade.php (single
     handler for the whole page, event-delegated). --}}
@php
    /* Single source of truth for the displayed tokens. Keep aligned with
       StayCommunicationsResolver::expandMergeFields(). */
    $tokenGroups = [
        'Guest' => [
            'guest_name'      => 'Guest name',
            'guest_email'     => 'Email',
            'guest_phone'     => 'Phone',
        ],
        'Trip' => [
            'check_in'        => 'Check-in',
            'check_out'       => 'Check-out',
            'nights'          => 'Nights',
            'adults'          => 'Adults',
            'children'        => 'Children',
            'pets'            => 'Pets',
            'total_amount'    => 'Total',
            'booking_id'      => 'Booking ID',
        ],
        'Property' => [
            'property_name'    => 'Property name',
            'property_address' => 'Address',
        ],
        'Host / Brand' => [
            'host_name'       => 'Host name',
            'host_phone'      => 'Host phone',
            'emergency_phone' => 'Emergency phone',
            'brand'           => 'Brand',
        ],
        'Signing' => [
            'signed_date'     => 'Date signed',
            'guest_signature' => 'Signature',
        ],
    ];
@endphp

<div class="merge-token-bar" data-for-editor="{{ $for }}">
    <div class="merge-token-bar__head">
        <strong>Insert merge field:</strong>
        <small class="text-muted">click a chip to drop the token at the cursor</small>
    </div>
    @foreach($tokenGroups as $groupLabel => $tokens)
        <div class="merge-token-group">
            <span class="merge-token-group__label">{{ $groupLabel }}</span>
            @foreach($tokens as $tokenKey => $tokenLabel)
                {{-- Build the literal token string in PHP and emit via raw
                     echo. Trying to inline `'{{'.$tokenKey.'}}'` inside a
                     Blade `{{ }}` directive trips the parser — the inner
                     `}}` closes the directive too early. --}}
                @php $literalToken = '{{'.$tokenKey.'}}'; @endphp
                <button type="button"
                        class="merge-token-chip"
                        data-token="{{ $literalToken }}"
                        title="Insert {{ $literalToken }}">
                    {{ $tokenLabel }}
                </button>
            @endforeach
        </div>
    @endforeach
</div>
