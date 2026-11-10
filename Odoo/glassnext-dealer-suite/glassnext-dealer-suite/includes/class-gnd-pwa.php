<?php
if (!defined('ABSPATH')) exit;

/**
 * GND_PWA
 *
 * Routes voor de dealer-webapp:
 *   /{slug}/                       → app (volledig scherm, zonder thema)
 *   /{slug}/manifest.webmanifest   → web app manifest
 *   /{slug}/sw.js                  → service worker (scope /{slug}/)
 *   /.well-known/assetlinks.json   → Android TWA-verificatie (alleen als ingesteld)
 */
class GND_PWA {

    private static $instance = null;
    const QV = 'gnd_app';

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'rewrite_rules']);
        add_filter('query_vars', function ($vars) { $vars[] = self::QV; return $vars; });
        add_action('template_redirect', [$this, 'dispatch'], 0);
        add_filter('redirect_canonical', [$this, 'no_canonical_redirect'], 10, 2);
        add_action('wp_login_failed', [$this, 'login_failed']);
    }

    public function rewrite_rules() {
        $slug = preg_quote(GlassNext_Dealer_Suite::opt('gnd_app_slug'), '#');
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?' . self::QV . '=app', 'top');
        add_rewrite_rule('^' . $slug . '/manifest\.webmanifest$', 'index.php?' . self::QV . '=manifest', 'top');
        add_rewrite_rule('^' . $slug . '/sw\.js$', 'index.php?' . self::QV . '=sw', 'top');
        add_rewrite_rule('^\.well-known/assetlinks\.json$', 'index.php?' . self::QV . '=assetlinks', 'top');

        // Eenmalig flushen na activatie, update of gewijzigde slug.
        $hash = md5($slug . '|' . GND_VERSION);
        if (get_option('gnd_rules_hash') !== $hash) {
            flush_rewrite_rules(false);
            update_option('gnd_rules_hash', $hash, true);
        }
    }

    public function no_canonical_redirect($redirect, $requested) {
        return get_query_var(self::QV) ? false : $redirect;
    }

    /** Mislukte login vanuit de app → terug naar de app met melding i.p.v. het WordPress-loginscherm. */
    public function login_failed() {
        $ref = wp_get_referer();
        if (!$ref) return;
        $app = GlassNext_Dealer_Suite::app_url();
        if (strpos($ref, $app) === 0) {
            wp_safe_redirect(add_query_arg('login', 'failed', $app));
            exit;
        }
    }

    public function dispatch() {
        $what = get_query_var(self::QV);
        if (!$what) return;
        switch ($what) {
            case 'app':
                GlassNext_Dealer_Suite::instance()->render_app_page();
                break;
            case 'manifest':
                $this->manifest();
                break;
            case 'sw':
                $this->service_worker();
                break;
            case 'assetlinks':
                $this->assetlinks();
                break;
        }
    }

    public static function manifest_url() {
        return GlassNext_Dealer_Suite::app_url() . 'manifest.webmanifest';
    }

    public static function sw_url() {
        return GlassNext_Dealer_Suite::app_url() . 'sw.js?v=' . rawurlencode(GND_VERSION);
    }

    /**
     * Iconen: eigen icoon uit de mediabibliotheek, anders het meegeleverde "GN"-icoon.
     * @return array{apple:string, list:array<int,array{src:string,sizes:string,type:string,purpose:string}>}
     */
    public static function icons() {
        $id = (int) GlassNext_Dealer_Suite::opt('gnd_app_icon');
        if ($id && wp_attachment_is_image($id)) {
            $src  = wp_get_attachment_image_src($id, 'full');
            $mime = get_post_mime_type($id) ?: 'image/png';
            if ($src) {
                $size = (int) $src[1] . 'x' . (int) $src[2];
                return [
                    'apple' => $src[0],
                    'list'  => [
                        ['src' => $src[0], 'sizes' => $size, 'type' => $mime, 'purpose' => 'any'],
                    ],
                ];
            }
        }
        $base = GND_PLUGIN_URL . 'assets/icons/';
        $v = '?ver=' . GND_VERSION;
        return [
            'apple' => $base . 'apple-touch-icon.png' . $v,
            'list'  => [
                ['src' => $base . 'icon-192.png' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $base . 'icon-512.png' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $base . 'icon-maskable-512.png' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    private function manifest() {
        $path = GlassNext_Dealer_Suite::app_path();
        $manifest = [
            'id'               => $path,
            'name'             => 'GlassNext Dealer',
            'short_name'       => 'GN Dealer',
            'description'      => 'Ruitmaten invoeren, snijplan berekenen en offertes naar GlassNext sturen.',
            'lang'             => 'nl-NL',
            'start_url'        => $path . '?source=pwa',
            'scope'            => $path,
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#0b1e2d',
            'theme_color'      => '#0b1e2d',
            'icons'            => self::icons()['list'],
        ];
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function service_worker() {
        $assets = array_values(GlassNext_Dealer_Suite::asset_urls());
        foreach (self::icons()['list'] as $icon) $assets[] = $icon['src'];
        $assets = array_values(array_unique($assets));
        $cache  = 'gnd-' . GND_VERSION . '-' . substr(md5(implode('|', $assets)), 0, 8);
        $shell  = GlassNext_Dealer_Suite::app_url();

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Service-Worker-Allowed: ' . GlassNext_Dealer_Suite::app_path());
        $js_cache  = wp_json_encode($cache);
        $js_assets = wp_json_encode($assets, JSON_UNESCAPED_SLASHES);
        $js_shell  = wp_json_encode($shell, JSON_UNESCAPED_SLASHES);
        echo <<<JS
/* GlassNext Dealer — service worker */
const CACHE={$js_cache};
const ASSETS={$js_assets};
const SHELL={$js_shell};
const OFFLINE='<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="font-family:system-ui,sans-serif;background:#0b1e2d;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px;text-align:center"><div><h1 style="font-size:20px">Geen verbinding</h1><p>Open de app opnieuw zodra je weer online bent.</p></div></body></html>';

self.addEventListener('install',e=>{
  e.waitUntil(caches.open(CACHE).then(c=>Promise.all(ASSETS.map(u=>c.add(u).catch(()=>null)))).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',e=>{
  e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('gnd-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener('message',e=>{
  if(e.data==='gnd-clear')e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('gnd-')).map(k=>caches.delete(k)))));
});
self.addEventListener('fetch',e=>{
  const req=e.request;
  if(req.method!=='GET')return;
  const url=new URL(req.url);
  if(/admin-ajax\.php|wp-login\.php|\/wp-admin\//.test(url.pathname))return;
  if(req.mode==='navigate'){
    // Network-first: altijd de actuele app (met verse nonce); offline de laatst geladen versie.
    e.respondWith(fetch(req).then(resp=>{
      if(resp.ok&&resp.type==='basic'&&url.origin===location.origin){const copy=resp.clone();caches.open(CACHE).then(c=>c.put(SHELL,copy))}
      return resp;
    }).catch(()=>caches.match(SHELL).then(m=>m||new Response(OFFLINE,{headers:{'Content-Type':'text/html; charset=utf-8'}}))));
    return;
  }
  if(ASSETS.includes(url.href)){
    e.respondWith(caches.match(req).then(m=>m||fetch(req).then(resp=>{if(resp.ok){const copy=resp.clone();caches.open(CACHE).then(c=>c.put(req,copy))}return resp})));
  }
});
JS;
        exit;
    }

    private function assetlinks() {
        $package = GlassNext_Dealer_Suite::opt('gnd_android_package');
        $prints  = array_filter(array_map('trim', preg_split('/[,\s]+/', (string) GlassNext_Dealer_Suite::opt('gnd_android_fingerprint'))));
        if ($package === '' || !$prints) {
            status_header(404);
            nocache_headers();
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target'   => [
                'namespace'                => 'android_app',
                'package_name'             => $package,
                'sha256_cert_fingerprints' => array_values($prints),
            ],
        ]], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
