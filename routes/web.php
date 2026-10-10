<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CataloguePathRedirect;
use App\Http\Controllers\ExoplanetExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GalaxyDataController;
use App\Http\Controllers\HigherEducationHandoutController;
use App\Http\Controllers\NightPlannerController;
use App\Http\Controllers\NightWeatherController;
use App\Http\Controllers\ObservingShortlistController;
use App\Http\Controllers\ObservingSiteLocationController;
use App\Http\Controllers\ObservingSyncController;
use App\Http\Controllers\ObservingSyncPageController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\RandomObjectController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReleaseFeedController;
use App\Http\Controllers\ReleaseHealthController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StarterCatalogueController;
use App\Http\Controllers\SystemsDirectoryController;
use App\Http\Controllers\TodayRedirectController;
use App\Http\Controllers\VisibilityAlertController;
use App\Livewire\AboutPage;
use App\Livewire\ApiPage;
use App\Livewire\Category;
use App\Livewire\CloseApproaches;
use App\Livewire\EducatorsPage;
use App\Livewire\ExoplanetDetail;
use App\Livewire\Exoplanets;
use App\Livewire\ExoplanetSystem;
use App\Livewire\ExplorePage;
use App\Livewire\Galaxy;
use App\Livewire\HigherEducationPage;
use App\Livewire\Home;
use App\Livewire\LearnPage;
use App\Livewire\MeteorShowerDetail;
use App\Livewire\MeteorShowers;
use App\Livewire\Objects\Index as ObjectsIndex;
use App\Livewire\Objects\Show as ObjectsShow;
use App\Livewire\ObservePage;
use App\Livewire\ObservingJournal;
use App\Livewire\ObservingWorkspace;
use App\Livewire\Orrery;
use App\Livewire\Planets\Index as PlanetsIndex;
use App\Livewire\PluginPage;
use App\Livewire\PrivacyPage;
use App\Livewire\SearchPage;
use App\Livewire\SettingsPage;
use App\Livewire\Today;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');
Route::get('/up/release', ReleaseHealthController::class)->name('releases.health');
Route::get('/releases/feed.atom', [ReleaseFeedController::class, 'atom'])->name('releases.feed');
Route::get('/releases/feed.rss', [ReleaseFeedController::class, 'rss'])->name('releases.feed.rss');
Route::get('/releases', ReleaseController::class)->name('releases.index');
Route::get('/whats-new', ReleaseController::class);
Route::get('/releases/{version}', ReleaseController::class)->name('releases.show');
Route::get('/whats-new/{version}', ReleaseController::class);
Route::get('/explore', ExplorePage::class)->name('explore');
Route::get('/observe', ObservePage::class)->name('observe');
Route::get('/observatory', ObservingWorkspace::class)->name('observatory');
Route::post('/observatory/what3words', [ObservingSiteLocationController::class, 'locate'])
    ->middleware('throttle:20,1')
    ->name('observatory.what3words');
Route::post('/observatory/what3words/coordinates', [ObservingSiteLocationController::class, 'coordinates'])
    ->middleware('throttle:30,1')
    ->name('observatory.what3words.coordinates');
Route::get('/observing-journal', ObservingJournal::class)->name('observing.journal');
Route::get('/observe/shortlist', ObservingShortlistController::class)->name('observe.shortlist');
Route::post('/observe/shortlist', ObservingShortlistController::class)->middleware('throttle:6,1')->name('observe.shortlist.calculate');
Route::get('/observe/night', NightPlannerController::class)->name('observe.night');
Route::post('/observe/night', NightPlannerController::class)->middleware('throttle:6,1')->name('observe.night.calculate');
Route::post('/observe/night/weather', NightWeatherController::class)->middleware('throttle:12,1')->name('observe.night.weather');
Route::get('/learn', LearnPage::class)->name('learn');

// Provisional satellite ids keep the designation slash (S/2019 S 1 → moon-s/2019-s-1).
// `{slug}` otherwise stops at the first slash, so the sitemap's /objects/moon-s/…
// URLs never reached the detail lookup. The class excludes "." so the OG route's
// ".png" suffix stays a literal.
$catalogueSlug = '[A-Za-z0-9][A-Za-z0-9\-/]*';

Route::get('/objects', ObjectsIndex::class)->name('objects.index');
Route::get('/objects/{slug}', ObjectsShow::class)
    ->where('slug', $catalogueSlug)
    ->name('objects.show');

// Planets get a dedicated, more editorial landing; detail reuses the object
// template (with moons promoted to a sortable table + a rings section).
Route::get('/planets', PlanetsIndex::class)->name('planets.index');
Route::get('/planets/{slug}', ObjectsShow::class)
    ->where('slug', $catalogueSlug)
    ->name('planets.show');

// Category landing pages — filtered views over the catalogue.
Route::get('/dwarf-planets', Category::class)->defaults('kind', 'dwarf_planet')->name('dwarf-planets');
Route::get('/asteroids', Category::class)->defaults('kind', 'asteroid')->name('asteroids');
Route::get('/comets', Category::class)->defaults('kind', 'comet')->name('comets');
Route::get('/tnos', Category::class)->defaults('kind', 'tno')->name('tnos');

Route::get('/search', SearchPage::class)->name('search');

// Interactive orrery (2D solar-system map at a chosen date)
Route::get('/orrery', Orrery::class)->name('orrery');

Route::get('/exoplanets', Exoplanets::class)->name('exoplanets.index');
Route::get('/exoplanets/export/{format}', ExoplanetExportController::class)->whereIn('format', ['csv', 'json'])->middleware('throttle:30,1')->name('exoplanets.export');
Route::get('/exoplanets/{id}', ExoplanetDetail::class)->name('exoplanets.show');
Route::get('/observing-targets', [StarterCatalogueController::class, 'index'])->name('observing-targets.index');
Route::get('/observing-targets/{id}', [StarterCatalogueController::class, 'show'])->name('observing-targets.show');
Route::get('/systems', SystemsDirectoryController::class)->name('systems.index');
Route::get('/systems/{id}', ExoplanetSystem::class)->name('systems.show');
Route::get('/galaxy/data', GalaxyDataController::class)->name('galaxy.data');
Route::get('/galaxy', Galaxy::class)->name('galaxy');
Route::get('/close-approaches', CloseApproaches::class)->name('close-approaches');

Route::get('/meteor-showers', MeteorShowers::class)->name('meteor-showers.index');
Route::get('/meteor-showers/{code}', MeteorShowerDetail::class)->name('meteor-showers.show');

Route::get('/random', RandomObjectController::class)->name('random');

// Object of the day: /today always points at the current UTC day's dated permalink.
Route::get('/today', TodayRedirectController::class)->name('today');
Route::get('/today/{date}', Today::class)->where('date', '\d{4}-\d{2}-\d{2}')->name('today.show');

// Open Graph share cards, rendered once and cached (see OgImageController).
Route::get('/og/site.png', [OgImageController::class, 'site'])->name('og.site');
Route::get('/og/objects/{slug}.png', [OgImageController::class, 'object'])
    ->where('slug', $catalogueSlug)
    ->name('og.object');
Route::get('/og/today/{date}.png', [OgImageController::class, 'today'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('og.today');

Route::get('/about', AboutPage::class)->name('about');
// Classroom handouts for teachers (static PDFs under public/handouts/).
Route::get('/educators', EducatorsPage::class)->name('educators');
Route::get('/plugin', PluginPage::class)->name('plugin');
Route::get('/higher-education', HigherEducationPage::class)->name('higher-education');
Route::get('/higher-education/{activity}/handout', HigherEducationHandoutController::class)->where('activity', '[a-z-]+')->name('higher-education.handout');
Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:3,10,feedback')->name('feedback.store');
Route::get('/api', ApiPage::class)->name('api');
// Catalogue paths are not pages on this host. GET follows them to the API.
Route::get('/api/v1/{path}', CataloguePathRedirect::class)
    ->where('path', '.*')
    ->name('api.catalogue');
Route::get('/privacy', PrivacyPage::class)->name('privacy');
Route::get('/settings', SettingsPage::class)->name('settings');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/{name}.xml', [SitemapController::class, 'child'])
    ->where('name', '[a-z]+(?:-[0-9]+)?')
    ->name('sitemap.child');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/account/observing-workspace', [ObservingSyncController::class, 'show'])->middleware('throttle:30,1')->name('observing.sync.show');
    Route::put('/account/observing-workspace', [ObservingSyncController::class, 'update'])->middleware('throttle:10,1')->name('observing.sync.update');
    Route::delete('/account/observing-workspace', [ObservingSyncController::class, 'destroy'])->middleware('throttle:10,1')->name('observing.sync.destroy');
    Route::get('/account/observing-backup', ObservingSyncPageController::class)->name('observing.sync.page');
    Route::get('/alerts', [VisibilityAlertController::class, 'index'])->name('alerts.index');
    Route::delete('/alerts/{alert}', [VisibilityAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
