<div aria-label="Choose check-in and check-out dates" aria-modal="false" class="booking-popover date-popover" hidden id="datePopover" role="dialog">
<div class="calendar-head"><div aria-live="polite" class="calendar-message" id="calendarPrompt">Choose your check-in date</div><div class="calendar-arrows"><button aria-label="Previous month" id="calendarPrev" type="button">‹</button><button aria-label="Next month" id="calendarNext" type="button">›</button></div></div>
<div class="calendar-months" id="calendarMonths"></div>
<div class="calendar-bottom"><button class="clear-dates" id="clearDates" type="button">Clear dates</button><span class="calendar-hint" id="calendarHint">Select your dates</span></div>
</div>
<div aria-label="Choose number of guests" aria-modal="false" class="booking-popover guest-popover" hidden id="guestPopover" role="dialog">
<div class="guest-row"><div class="guest-label"><strong>Adults</strong><span>Ages 13+</span></div><div class="guest-controls"><button aria-label="Remove adult" data-counter="adults" data-step="-1" type="button">−</button><output id="adultCount">1</output><button aria-label="Add adult" data-counter="adults" data-step="1" type="button">+</button></div></div>
<div class="guest-row"><div class="guest-label"><strong>Children</strong><span>Ages 2–12</span></div><div class="guest-controls"><button aria-label="Remove child" data-counter="children" data-step="-1" type="button">−</button><output id="childCount">0</output><button aria-label="Add child" data-counter="children" data-step="1" type="button">+</button></div></div>
<button class="guest-close" id="guestClose" type="button">Close</button>
</div>
