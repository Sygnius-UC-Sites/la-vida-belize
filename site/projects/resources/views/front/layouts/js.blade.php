<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@php
    $assetVersion = $setting_data['version_css_js'] ?? '20260619.1';
    $assetVer = function ($path) use ($assetVersion) {
        $path = ltrim($path, '/');
        $candidates = [
            public_path($path),
            base_path('../' . $path),
            rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $path,
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) return filemtime($full);
        }
        return $assetVersion;
    };
@endphp
<script src="{{ asset('front/base/js/script.js') }}?ver={{ $assetVer('front/base/js/script.js') }}"></script>
<script src="{{ asset('front/js/guest-selector.js') }}?ver={{ $assetVer('front/js/guest-selector.js') }}"></script>
<script src="{{ asset('toastr/toastr.js') }}?ver={{ $assetVer('toastr/toastr.js') }}"></script>
{{-- Base Vacation Rentals booking calendar (function from the base theme) --}}
<script>
window.VR_DATEPICKER_CONFIG = { easepickCss:"{{ asset('easepick/easepick.css') }}?ver={{ $assetVer('easepick/easepick.css') }}", themeCss:"{{ asset('front/css/vr-datepicker.css') }}?ver={{ $assetVer('front/css/vr-datepicker.css') }}", adminBlockedUrl:"{{ url('admin/api/datepicker/blocked-dates') }}" };
</script>
<script src="{{ asset('easepick/easepick.umd.min.js') }}?ver={{ $assetVer('easepick/easepick.umd.min.js') }}"></script>
<script src="{{ asset('front/js/vr-datepicker.js') }}?ver={{ $assetVer('front/js/vr-datepicker.js') }}"></script>
<script>
// Guard for layouts that don't load jQuery (e.g. shared-master booking/status
// pages) — toastr depends on jQuery, so skip cleanly instead of throwing
// "$ is not defined" (QA D-005 secondary console error).
if (typeof jQuery !== 'undefined') {
    jQuery(function(){ @if(Session::has('success')) toastr.success("{{ Session::get('success') }}"); @endif @if(Session::has('danger')) toastr.error("{{ Session::get('danger') }}"); @endif });
}
</script>
