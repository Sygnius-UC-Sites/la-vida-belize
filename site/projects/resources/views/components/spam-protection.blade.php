@if($honeypotOn)
<div aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
    <label for="{{ $honeypotField }}_hp">Website (leave blank)</label>
    <input type="text" id="{{ $honeypotField }}_hp" name="{{ $honeypotField }}" tabindex="-1" autocomplete="off" value="">
</div>
@endif

@if($timeTrapOn)
<input type="hidden" name="{{ $timeTrapField }}" value="{{ $timeToken }}">
@endif

@if($turnstileOn && $turnstileKey !== '')
<div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-size="invisible"></div>
@endif
