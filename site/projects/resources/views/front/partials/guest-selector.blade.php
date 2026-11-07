@php
    $prefix = $prefix ?? 'guestSelector';
    $adults = max(1, (int) ($adults ?? request('adults', 1)));
    $children = max(0, (int) ($children ?? request('children', 0)));
    $pets = max(0, (int) ($pets ?? request('pets', 0)));
    $maxGuests = max(1, (int) ($maxGuests ?? 12));
    $maxPets = max(0, (int) ($maxPets ?? 5));
    $showPets = (bool) ($showPets ?? true);
@endphp

<div class="vr-guest-selector {{ $class ?? '' }}"
     data-vr-guest-selector
     data-max-guests="{{ $maxGuests }}"
     data-max-pets="{{ $maxPets }}">
    <button type="button"
            class="vr-guest-trigger"
            aria-haspopup="true"
            aria-expanded="false">
        <span class="vr-guest-summary" data-vr-guest-summary>{{ $adults + $children }} guest{{ ($adults + $children) === 1 ? '' : 's' }}{{ $pets > 0 ? ', ' . $pets . ' pet' . ($pets === 1 ? '' : 's') : '' }}</span>
        <svg class="vr-guest-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
            <path d="M6 9l6 6 6-6"></path>
        </svg>
    </button>

    <div class="vr-guest-popover" role="dialog" hidden>
        <div class="vr-guest-row" data-target="adults">
            <div class="vr-guest-row__label">
                <strong>Adults</strong>
                <small>Ages 13+</small>
            </div>
            <div class="vr-stepper">
                <button type="button" class="vr-stepper-btn" data-action="dec" aria-label="Decrease adults">-</button>
                <span class="vr-stepper-value" data-vr-value="adults">{{ $adults }}</span>
                <button type="button" class="vr-stepper-btn" data-action="inc" aria-label="Increase adults">+</button>
            </div>
        </div>
        <div class="vr-guest-row" data-target="children">
            <div class="vr-guest-row__label">
                <strong>Children</strong>
                <small>Ages 2-12</small>
            </div>
            <div class="vr-stepper">
                <button type="button" class="vr-stepper-btn" data-action="dec" aria-label="Decrease children">-</button>
                <span class="vr-stepper-value" data-vr-value="children">{{ $children }}</span>
                <button type="button" class="vr-stepper-btn" data-action="inc" aria-label="Increase children">+</button>
            </div>
        </div>
        @if($showPets)
            <div class="vr-guest-row" data-target="pets">
                <div class="vr-guest-row__label">
                    <strong>Pets</strong>
                    <small>Dogs or cats</small>
                </div>
                <div class="vr-stepper">
                    <button type="button" class="vr-stepper-btn" data-action="dec" aria-label="Decrease pets">-</button>
                    <span class="vr-stepper-value" data-vr-value="pets">{{ $pets }}</span>
                    <button type="button" class="vr-stepper-btn" data-action="inc" aria-label="Increase pets">+</button>
                </div>
            </div>
        @endif
        <button type="button" class="vr-guest-popover-close">Close</button>
    </div>

    <input type="hidden" name="adults" value="{{ $adults }}" data-vr-input="adults">
    <input type="hidden" name="children" value="{{ $children }}" data-vr-input="children">
    <input type="hidden" name="pets" value="{{ $pets }}" data-vr-input="pets">
</div>
