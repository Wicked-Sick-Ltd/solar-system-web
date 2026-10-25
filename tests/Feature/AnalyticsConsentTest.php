<?php

declare(strict_types=1);

use App\Support\Analytics;
use App\Support\SettingsPayload;

beforeEach(fn () => fakeSolar());

it('ships no analytics and no consent banner when neither GA4 nor Tag Manager is configured', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => null,
    ]);

    // Only essential cookies are set, which need no consent under PECR.
    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', escape: false)
        ->assertDontSee('<meta name="ga-measurement-id"', escape: false)
        ->assertDontSee('<meta name="gtm-container-id"', escape: false)
        ->assertDontSee('id="cookie-banner"', escape: false);
});

it('exposes the measurement id and loads gtag on every page before any consent choice', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-TEST1234',
        'site.analytics.gtm_container_id' => null,
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('name="ga-measurement-id" content="G-TEST1234"')
        ->toContain("var STORAGE = 'pu_cookie_consent'")
        ->toContain("return read() !== 'essential'")
        ->toContain("analytics_storage: granted ? 'granted' : 'denied'")
        ->toContain("window.gtag('config', id")
        ->toContain('window.__puGtagScriptTag = true;')
        ->toContain('<script data-cfasync="false" async src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"></script>')
        ->not->toContain('gtm.js?id=')
        ->not->toContain('ns.html?id=');

    // The config call is declared above the boot sequence. What matters is that
    // the boot sequence sets the consent default and then configures the tag,
    // and that the gtag.js element follows that script.
    $consent = strpos($html, "gtag('consent', 'default'");
    $boot = strpos($html, "window.__gaLoaded = true;\n        configureMeasurement();\n        sendPageView();");
    $tag = strpos($html, '<script data-cfasync="false" async src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"></script>');

    expect($consent)->toBeInt()
        ->and($boot)->toBeGreaterThan($consent)
        ->and($tag)->toBeGreaterThan($boot);
});

it('tells gtag where it is instead of letting it read the address bar', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/')
        ->assertOk()
        ->assertSee('name="ga-page-location" content="'.url('/').'"', escape: false)
        ->assertSee('meta[name="ga-page-location"]', escape: false)
        ->assertSee('page_location: pageLocation()', escape: false);
});

it('redacts a settings share token from the page location it reports', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    // Previewing a share link must not hand the observing location inside it to Google,
    // whether or not the visitor ever clicks "Apply these settings".
    $token = SettingsPayload::encode(['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58]]);

    $html = $this->get('/settings?s='.$token)->assertOk()->getContent();

    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $html, $m);

    expect($m[1] ?? '')->toBe(route('settings').'?s=redacted')
        ->and($m[1] ?? '')->not->toContain($token);

    // And the banner reports that, never the address bar gtag would read by default.
    expect($html)->not->toContain('page_location: location.href')
        ->and($html)->not->toContain('page_location: location');

    // Opening a leftover query share must not echo the coordinates in the HTML either.
    expect($html)->not->toContain('50.97');
});

it('redacts bracketed coordinates and share tokens from the published page location', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $html = $this->get('/privacy?lat[]=51.5&p[lon][]=1.23456&s[]=PRIVATE_SETTINGS&targets=moon')
        ->assertOk()
        ->getContent();

    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $html, $meta);
    $location = html_entity_decode($meta[1] ?? '', ENT_QUOTES);

    expect($location)->toContain('lat=redacted')
        ->and($location)->toContain('s=redacted')
        ->and($location)->toContain('targets=moon')
        ->and($location)->not->toContain('51.5')
        ->and($location)->not->toContain('1.23456')
        ->and($location)->not->toContain('PRIVATE_SETTINGS');

    $nested = $this->get('/privacy?p[][lat]=51.5&p[][lon]=1.23456&p[][date]=2026-10-10')
        ->assertOk()
        ->getContent();

    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $nested, $nestedMeta);
    $nestedLocation = html_entity_decode($nestedMeta[1] ?? '', ENT_QUOTES);

    expect($nestedLocation)->toContain('2026-10-10')
        ->and($nestedLocation)->toContain('redacted')
        ->and($nestedLocation)->not->toContain('51.5')
        ->and($nestedLocation)->not->toContain('1.23456');
});

it('shows a cookie banner that links to the privacy policy when analytics is enabled', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="cookie-banner"', escape: false)
        ->assertSee(route('privacy'), escape: false)
        ->assertSee('Essential only')
        ->assertSee('Accept analytics');
});

it('ignores a measurement id that is not a GA4 id', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-NO<script>',
        'site.analytics.gtm_container_id' => null,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', escape: false)
        ->assertDontSee('id="cookie-banner"', escape: false)
        ->assertDontSee('ga-measurement-id', escape: false);
});

it('ignores a container id that is not a GTM id', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => 'GTM-NO<script>',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', escape: false)
        ->assertDontSee('id="cookie-banner"', escape: false)
        ->assertDontSee('gtm-container-id', escape: false);
});

it('defaults analytics storage to granted unless the visitor chose essential and sends page views after Livewire navigation', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain("gtag('consent', 'default'")
        ->toContain("var STORAGE = 'pu_cookie_consent'")
        ->toContain("return read() !== 'essential'")
        ->toContain("analytics_storage: granted ? 'granted' : 'denied'")
        ->toContain("analytics_storage: 'granted'")
        ->toContain("analytics_storage: 'denied'")
        ->toContain("ad_storage: 'denied'")
        ->toContain("ad_user_data: 'denied'")
        ->toContain("ad_personalization: 'denied'")
        ->toContain('wait_for_update: 500')
        ->toContain('send_page_view: false')
        ->toContain("addEventListener('livewire:navigated'")
        ->toContain("gtag('event', 'page_view'")
        ->toContain("window.gtag('set', 'url_passthrough', true)")
        ->toContain("window.gtag('set', 'ads_data_redaction', true)")
        ->toContain('<script data-cfasync="false" async src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"></script>')
        ->not->toContain('page_location: location.href');

    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $gtag = false;
    foreach ($document->getElementsByTagName('script') as $script) {
        if (str_contains($script->getAttribute('src'), 'googletagmanager.com/gtag/js')) {
            $gtag = true;
            expect($script->getAttribute('data-cfasync'))->toBe('false')
                ->and($script->hasAttribute('async'))->toBeTrue();
        }
    }
    expect($gtag)->toBeTrue();
});

it('explains Google Analytics 4 and Consent Mode, with choices only when it is switched on', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => null,
    ]);

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Google Analytics 4')
        ->assertSee('Google Tag Manager')
        ->assertSee('Consent Mode v2')
        ->assertSee('analytics_storage')
        ->assertSee('not currently switched on')
        ->assertDontSee('publicUniverseAnalytics.choose', false);

    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Google Analytics 4')
        ->assertSee('Consent Mode v2')
        ->assertSee('analytics storage')
        ->assertSee('Essential only')
        ->assertSee('Accept analytics')
        ->assertSee('Analytics helps us make the site better.')
        ->assertSee("publicUniverseAnalytics.choose('essential')", false)
        ->assertSee("publicUniverseAnalytics.choose('analytics')", false)
        ->assertDontSee('not currently switched on');
});

it('renders the default Google Tag Manager container in the head and at the start of the body', function () {
    expect(config('site.analytics.gtm_container_id'))->toBe('GTM-M8THNDPH')
        ->and(Analytics::gtmContainerId())->toBe('GTM-M8THNDPH');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain("})(window,document,'script','dataLayer','GTM-M8THNDPH');")
        ->toContain("j.src=\n'https://www.googletagmanager.com/gtm.js?id='+i+dl;")
        ->toContain("j.setAttribute('data-cfasync','false');")
        ->toContain('<script data-cfasync="false">(function(w,d,s,l,i){')
        ->toContain('name="gtm-container-id" content="GTM-M8THNDPH"')
        ->toContain('name="ga-page-location"')
        ->toContain('id="cookie-banner"')
        ->not->toContain('ns.html?id=');

    $consent = strpos($html, "gtag('consent', 'default'");
    $pageLocation = strpos($html, 'name="ga-page-location"');
    $published = strpos($html, 'publishPageLocation();');
    $gtm = strpos($html, "})(window,document,'script','dataLayer','GTM-M8THNDPH');");
    $body = strpos($html, '<body');

    expect($consent)->toBeInt()
        ->and($pageLocation)->toBeInt()
        ->and($published)->toBeInt()
        ->and($pageLocation)->toBeLessThan($consent)
        ->and($published)->toBeGreaterThan($consent)
        ->and($gtm)->toBeGreaterThan($published)
        ->and($body)->toBeGreaterThan($gtm)
        ->and($html)->toContain("analytics_storage: 'denied'")
        ->and($html)->toContain("ad_storage: 'denied'")
        ->and($html)->toContain("ad_user_data: 'denied'")
        ->and($html)->toContain("ad_personalization: 'denied'");
});

it('shows the cookie banner and privacy choices when only Tag Manager is configured', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => 'GTM-M8THNDPH',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('id="cookie-banner"')
        ->toContain("})(window,document,'script','dataLayer','GTM-M8THNDPH');")
        ->not->toContain('ns.html?id=')
        ->not->toContain('<meta name="ga-measurement-id"')
        ->not->toContain('<script async src="https://www.googletagmanager.com');

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Essential only')
        ->assertSee('Accept analytics')
        ->assertSee("publicUniverseAnalytics.choose('essential')", false)
        ->assertSee("publicUniverseAnalytics.choose('analytics')", false)
        ->assertDontSee('not currently switched on');
});

it('keeps Consent Mode ahead of Tag Manager when GA4 is configured as well', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-TEST1234',
        'site.analytics.gtm_container_id' => 'GTM-M8THNDPH',
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    $consent = strpos($html, "gtag('consent', 'default'");
    $boot = strpos($html, "window.__gaLoaded = true;\n        configureMeasurement();\n        sendPageView();");
    $gtag = strpos($html, '<script data-cfasync="false" async src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234"></script>');
    $gtm = strpos($html, 'https://www.googletagmanager.com/gtm.js');

    expect($html)
        ->toContain('name="ga-measurement-id" content="G-TEST1234"')
        ->toContain('name="gtm-container-id" content="GTM-M8THNDPH"')
        ->toContain('name="ga-page-location"')
        ->toContain('id="cookie-banner"')
        ->toContain("analytics_storage: 'denied'")
        ->toContain("j.setAttribute('data-cfasync','false');")
        ->and($consent)->toBeInt()
        ->and($boot)->toBeGreaterThan($consent)
        ->and($gtag)->toBeGreaterThan($boot)
        ->and($gtm)->toBeGreaterThan($gtag);

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Essential only')
        ->assertSee('Accept analytics')
        ->assertDontSee('not currently switched on');
});

it('gives container tags the redacted page location before Tag Manager starts', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => 'GTM-M8THNDPH',
    ]);

    $token = SettingsPayload::encode(['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58]]);
    $html = $this->get('/settings?s='.$token.'&lat=12.34567&lon=1.23456')->assertOk()->getContent();

    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $html, $meta);

    expect($meta[1] ?? '')->toContain('s=redacted')
        ->and($meta[1] ?? '')->toContain('lat=redacted')
        ->and($meta[1] ?? '')->toContain('lon=redacted')
        ->and($meta[1] ?? '')->not->toContain($token)
        ->and($meta[1] ?? '')->not->toContain('12.34567')
        ->and($meta[1] ?? '')->not->toContain('1.23456');

    $consent = strpos($html, "gtag('consent', 'default'");
    $defined = strpos($html, 'function publishPageLocation()');
    $published = strpos($html, 'publishPageLocation();');
    $gtm = strpos($html, "})(window,document,'script','dataLayer','GTM-M8THNDPH');");
    $beforeContainer = $defined === false || $gtm === false ? '' : substr($html, $defined, $gtm - $defined);

    expect($consent)->toBeInt()
        ->and($defined)->toBeInt()
        ->and($published)->toBeInt()
        ->and($gtm)->toBeGreaterThan($published)
        ->and($published)->toBeGreaterThan($consent)
        ->and($beforeContainer)->toContain('window.dataLayer.push({')
        ->and($beforeContainer)->toContain("window.gtag('set', {")
        ->and($beforeContainer)->toContain('page_location: page')
        ->and($beforeContainer)->toContain('page_referrer: referrer')
        ->and($beforeContainer)->toContain('sanitizeAnalyticsUrl(document.referrer)')
        ->and($beforeContainer)->toContain('send_page_view: false')
        ->and($html)->not->toContain('page_location: location.href')
        ->and($html)->not->toContain('page_location: location')
        ->and($html)->not->toContain('12.34567')
        ->and($html)->not->toContain('1.23456')
        ->and($html)->not->toContain('50.97');
});

it('omits the no-JavaScript Tag Manager image unless the server can read an accept choice', function () {
    config([
        'site.analytics.ga_measurement_id' => null,
        'site.analytics.gtm_container_id' => 'GTM-M8THNDPH',
    ]);

    // The anonymous document is what a shared cache stores. It keeps the head
    // snippet, which can run Consent Mode, and leaves out the noscript image.
    $anonymous = $this->get('/')->assertOk();
    expect($anonymous->headers->get('Cache-Control'))->toContain('public')
        ->and($anonymous->getContent())->toContain("})(window,document,'script','dataLayer','GTM-M8THNDPH');")
        ->and($anonymous->getContent())->not->toContain('ns.html');

    // The browser script writes this cookie in the clear. The test client must
    // do the same, or the server would be reading a value the page never sends.
    $essential = $this->withUnencryptedCookie(Analytics::CONSENT_COOKIE, 'essential')->get('/')->assertOk();
    expect($essential->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($essential->headers->get('Vary'))->toContain('Cookie')
        ->and($essential->getContent())->not->toContain('ns.html');

    $accepted = $this->withUnencryptedCookie(Analytics::CONSENT_COOKIE, Analytics::CONSENT_ACCEPTED)->get('/')->assertOk();
    expect($accepted->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($accepted->headers->get('Vary'))->toContain('Cookie')
        ->and($accepted->getContent())->toContain('https://www.googletagmanager.com/ns.html?id=GTM-M8THNDPH')
        ->and($accepted->getContent())->toMatch('/<body[^>]*>\s*<!-- Google Tag Manager \(noscript\) -->\s*<noscript><iframe src="https:\/\/www\.googletagmanager\.com\/ns\.html\?id=GTM-M8THNDPH"/');

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('no-JavaScript Tag Manager image is left out')
        ->assertSee('Without JavaScript the banner cannot record a choice')
        ->assertSee('not stored in a shared cache');
});

it('trusts forwarded proxy headers so the analytics page location is https', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-4H1XSCFT9J',
        'site.analytics.gtm_container_id' => 'GTM-M8THNDPH',
    ]);

    $plain = $this->get('http://publicuniverse.net/privacy?lat=51.5')->assertOk()->getContent();
    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $plain, $http);
    $httpLocation = html_entity_decode($http[1] ?? '', ENT_QUOTES);

    expect($httpLocation)->toBe('http://publicuniverse.net/privacy?lat=redacted')
        ->and(request()->secure())->toBeFalse()
        ->and(url()->current())->toBe('http://publicuniverse.net/privacy');

    $forwarded = $this->get('http://publicuniverse.net/privacy?lat=51.5', [
        'X-Forwarded-For' => '203.0.113.5',
        'X-Forwarded-Host' => 'publicuniverse.net',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk()->getContent();
    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $forwarded, $https);
    $httpsLocation = html_entity_decode($https[1] ?? '', ENT_QUOTES);

    expect($httpsLocation)->toBe('https://publicuniverse.net/privacy?lat=redacted')
        ->and($httpsLocation)->not->toContain('51.5')
        ->and($httpsLocation)->not->toContain(':80')
        ->and(request()->secure())->toBeTrue()
        ->and(request()->getScheme())->toBe('https')
        ->and(url()->current())->toBe('https://publicuniverse.net/privacy');

    // Cloudflare sends the proto without a port. The canonical location must
    // still be https on the default port, not https://host:80.
    $protoOnly = $this->get('http://publicuniverse.net/', [
        'X-Forwarded-Proto' => 'https',
    ])->assertOk()->getContent();
    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $protoOnly, $proto);
    $protoLocation = html_entity_decode($proto[1] ?? '', ENT_QUOTES);

    expect($protoLocation)->toBe('https://publicuniverse.net')
        ->and(request()->secure())->toBeTrue()
        ->and(url()->current())->toBe('https://publicuniverse.net');
});

it('publishes a privacy policy page', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacy')
        ->assertSee('Google Analytics')
        ->assertSee('Mailchimp')
        ->assertSee(config('site.contact_email'))
        ->assertSee('UK GDPR');
});

it('links the privacy policy from the footer and lists it in the sitemap', function () {
    $this->get('/')->assertSee('href="'.route('privacy').'"', escape: false);
    $this->get('/sitemaps/pages.xml')->assertSee(route('privacy'), escape: false);
});
