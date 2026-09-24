<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\GalaxyDataController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\RandomObjectController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VisibilityAlertController;
use App\Livewire\AboutPage;
use App\Livewire\ApiPage;
use App\Livewire\Category;
use App\Livewire\ExoplanetDetail;
use App\Livewire\Exoplanets;
use App\Livewire\ExoplanetSystem;
use App\Livewire\Galaxy;
use App\Livewire\Home;
use App\Livewire\Objects\Index as ObjectsIndex;
use App\Livewire\Objects\Show as ObjectsShow;
use App\Livewire\Orrery;
use App\Livewire\Planets\Index as PlanetsIndex;
use App\Livewire\PrivacyPage;
use App\Livewire\SearchPage;
use App\Livewire\SettingsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/objects', ObjectsIndex::class)->name('objects.index');
Route::get('/objects/{slug}', ObjectsShow::class)->name('objects.show');

// Planets get a dedicated, more editorial landing; detail reuses the object
// template (with moons promoted to a sortable table + a rings section).
Route::get('/planets', PlanetsIndex::class)->name('planets.index');
Route::get('/planets/{slug}', ObjectsShow::class)->name('planets.show');

// Category landing pages — filtered views over the catalogue.
Route::get('/dwarf-planets', Category::class)->defaults('kind', 'dwarf_planet')->name('dwarf-planets');
Route::get('/asteroids', Category::class)->defaults('kind', 'asteroid')->name('asteroids');
Route::get('/comets', Category::class)->defaults('kind', 'comet')->name('comets');
Route::get('/tnos', Category::class)->defaults('kind', 'tno')->name('tnos');

Route::get('/search', SearchPage::class)->name('search');

// Interactive orrery (2D solar-system map at a chosen date)
Route::get('/orrery', Orrery::class)->name('orrery');

Route::get('/exoplanets', Exoplanets::class)->name('exoplanets.index');
Route::get('/exoplanets/{id}', ExoplanetDetail::class)->name('exoplanets.show');
Route::get('/systems/{id}', ExoplanetSystem::class)->name('systems.show');
Route::get('/galaxy/data', GalaxyDataController::class)->name('galaxy.data');
Route::get('/galaxy', Galaxy::class)->name('galaxy');

Route::get('/random', RandomObjectController::class)->name('random');

// Per-object Open Graph share card (rendered + cached on object detail pages)
Route::get('/og/objects/{slug}.png', OgImageController::class)
    ->where('slug', '[A-Za-z0-9\-]+')   // keep the .png suffix literal
    ->name('og.object');

Route::get('/about', AboutPage::class)->name('about');
Route::get('/api', ApiPage::class)->name('api');
Route::get('/privacy', PrivacyPage::class)->name('privacy');
Route::get('/settings', SettingsPage::class)->name('settings');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/alerts', [VisibilityAlertController::class, 'index'])->name('alerts.index');
    Route::delete('/alerts/{alert}', [VisibilityAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
