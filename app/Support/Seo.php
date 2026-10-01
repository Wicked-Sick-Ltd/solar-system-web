<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Per-request, fluent holder for page metadata. A request-scoped singleton: any
 * page (Livewire component or plain controller) sets what it knows, and the
 * layout's <head> reads it. Sensible site-wide defaults mean a page that sets
 * nothing still emits valid, complete tags.
 */
class Seo
{
    private ?string $title = null;

    private string $description;

    private ?string $canonical = null;

    private ?string $image = null;

    private string $type = 'website';

    private bool $noindex = false;

    /** @var list<array<string,mixed>> */
    private array $jsonLd = [];

    public function __construct()
    {
        $this->description = (string) config('site.description');
    }

    public function title(?string $title): static
    {
        $this->title = $title ? trim($title) : null;

        return $this;
    }

    public function description(?string $description): static
    {
        if ($description !== null && trim($description) !== '') {
            $this->description = Str::limit(trim(preg_replace('/\s+/', ' ', $description)), 200);
        }

        return $this;
    }

    public function canonical(?string $url): static
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url): static
    {
        $this->image = $url;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(bool $noindex = true): static
    {
        $this->noindex = $noindex;

        return $this;
    }

    /** @param array<string,mixed> $schema */
    public function jsonLd(array $schema): static
    {
        $this->jsonLd[] = $schema;

        return $this;
    }

    // -- Accessors used by the layout --------------------------------------

    public function fullTitle(): string
    {
        $name = (string) config('site.name');

        return $this->title !== null && $this->title !== $name
            ? "{$this->title} · {$name}"
            : "{$name} · ".config('site.tagline');
    }

    public function metaTitle(): string
    {
        return $this->title ?? config('site.name').' · '.config('site.tagline');
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCanonical(): string
    {
        return $this->canonical ?? url()->current();
    }

    public function getImage(): ?string
    {
        // Fall back to the site's branded share card when a page sets none.
        return $this->image ?? ShareImage::defaultUrl();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    /** @return list<array<string,mixed>> */
    public function getJsonLd(): array
    {
        return $this->jsonLd;
    }
}
