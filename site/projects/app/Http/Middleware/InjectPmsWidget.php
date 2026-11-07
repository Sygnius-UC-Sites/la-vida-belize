<?php

namespace App\Http\Middleware;

/**
 * [PMS-MODULE] Widget mode — injects the booking-widget enhancer script into
 * property pages that are backed by a PMS room. The page itself is served by
 * the site's OWN controller and views, completely untouched; this middleware
 * only appends a <script> before </body>.
 *
 * Active only when config('pms.page_mode') === 'widget' (the default).
 */

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InjectPmsWidget
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (config('pms.page_mode', 'widget') !== 'widget') {
            return $response;
        }
        if (!$request->isMethod('GET')) {
            return $response;
        }
        $status = $response->getStatusCode();
        if ($status !== 200 && $status !== 404) {
            return $response;
        }
        $type = (string) $response->headers->get('Content-Type', '');
        if ($type !== '' && stripos($type, 'text/html') === false) {
            return $response;
        }

        // Property slug = last path segment (covers /{seo} and /properties/detail/{seo}).
        // rawurldecode: slugs can contain multibyte chars (emoji in listing
        // titles) which arrive percent-encoded — the DB stores them raw.
        $slug = rawurldecode(basename(trim($request->path(), '/')));
        if ($slug === '' || $slug === '/' || str_starts_with($request->path(), 'admin')) {
            return $response;
        }

        $room = Cache::remember('pms_widget_room_' . md5($slug), 60, function () use ($slug) {
            try {
                return \App\Models\Pms\PmsRoom::where('status', 'true')
                    ->where(function ($q) use ($slug) {
                        // slug URLs (/{seo}) AND legacy id URLs (/properties/{_id})
                        $q->where('seo_url', $slug)->orWhere('pms_room_id', $slug);
                    })
                    ->first() ?: false;
            } catch (\Throwable $e) {
                return false; // tables not migrated yet — do nothing
            }
        });
        if (!$room) {
            return $response;
        }

        $linkedProperty = Cache::remember('pms_widget_link_' . $room->id, 60, function () use ($room) {
            try {
                return \App\Models\Property::where('status', 'true')
                    ->where('seo_url', $room->seo_url)
                    ->where('pms_source', $room->pms_source)
                    ->where('pms_room_id', $room->id)
                    ->first() ?: false;
            } catch (\Throwable $e) {
                return false;
            }
        });
        if (!$linkedProperty) {
            return $response;
        }

        // CANONICAL URL: legacy id-style links (/properties/{_id}) redirect to
        // the property's real slug URL — one URL everywhere, sitewide.
        if (!empty($room->seo_url) && $slug !== $room->seo_url) {
            $qs = $request->getQueryString();
            return redirect()->to('/' . $room->seo_url . ($qs ? '?' . $qs : ''), 301);
        }

        // A 404 must never create, reactivate, or rename a public listing.
        if ($status === 404) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || stripos($content, '</body>') === false) {
            return $response;
        }
        // Support both the legacy form widget and the current themed booking card.
        if (!preg_match('/(?:booking_form|id=["\']booking-widget["\'])/i', $content)) {
            return $response;
        }

        $cfg = [
            'roomId'    => $room->id,
            'currency'  => $room->currency ?: 'USD',
            'availUrl'  => url('pms-availability'),
            'quoteUrl'  => url('pms-quote'),
            'funnelUrl' => url('pms/get-quote'),
            'petsAllowed' => $room->pets_allowed === null ? null : (bool) $room->pets_allowed,
            'maxPets' => (int) ($room->max_pets ?: 0),
        ];

        $configScript = "\n<script>window.PMS_WIDGET = " . json_encode($cfg) . ";</script>\n";
        $assetScript = "\n<script src=\"" . url('js/pms-widget.js') . "?v=" . config('pms.widget_version', '1') . "\"></script>\n";

        // The current themed widget reads the configuration from its existing
        // inline controller, so the data must exist before the body is parsed.
        if (stripos($content, '</head>') !== false) {
            $content = preg_replace('/<\/head>/i', $configScript . '</head>', $content, 1);
        } else {
            $content = $configScript . $content;
        }

        $response->setContent(str_ireplace('</body>', $assetScript . '</body>', $content));
        // Content length changed
        $response->headers->remove('Content-Length');

        return $response;
    }
}
