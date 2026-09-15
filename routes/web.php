<?php

use App\Http\Controllers\OgImageController;
use App\Http\Controllers\RandomObjectController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\AboutPage;
use App\Livewire\ApiPage;
use App\Livewire\Category;
use App\Livewire\Home;
use App\Livewire\Objects\Index as ObjectsIndex;
use App\Livewire\Objects\Show as ObjectsShow;
use App\Livewire\Orrery;
use App\Livewire\Planets\Index as PlanetsIndex;
use App\Livewire\PrivacyPage;
use App\Livewire\SearchPage;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

// Catalogue
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

// Search
Route::get('/search', SearchPage::class)->name('search');

// Interactive orrery (2D solar-system map at a chosen date)
Route::get('/orrery', Orrery::class)->name('orrery');

// Utility
Route::get('/random', RandomObjectController::class)->name('random');

// Per-object Open Graph share card (rendered + cached on object detail pages)
Route::get('/og/objects/{slug}.png', OgImageController::class)
    ->where('slug', '[A-Za-z0-9\-]+')   // keep the .png suffix literal
    ->name('og.object');

// Editorial / developer pages
Route::get('/about', AboutPage::class)->name('about');
Route::get('/api', ApiPage::class)->name('api');
Route::get('/privacy', PrivacyPage::class)->name('privacy');

// SEO infrastructure
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
