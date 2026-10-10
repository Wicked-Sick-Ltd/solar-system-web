<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One downloadable classroom handout, as declared in config/educators.php.
 *
 * All paths are relative to public/ and rendered as root-relative URLs, so
 * the page works unchanged on every hostname the site answers to.
 */
final readonly class Handout
{
    /**
     * @param  list<string>  $stages
     * @param  list<string>  $previews
     * @param  list<string>  $educationalLevel
     */
    public function __construct(
        public string $id,
        public string $audience,
        public string $keyStage,
        public string $keyStageAccessible,
        public array $stages,
        public string $title,
        public string $description,
        public string $pdf,
        public string $thumbnail,
        public array $previews,
        public int $pages,
        public string $paper,
        public array $educationalLevel,
        public string $typicalAgeRange,
        public ?int $bytes,
    ) {}

    /** @param  array<string, mixed>  $row */
    public static function fromConfig(array $row): self
    {
        $pdf = ltrim((string) $row['pdf'], '/');
        $file = public_path($pdf);
        $bytes = is_file($file) ? (int) filesize($file) : null;

        /** @var list<string> $previews */
        $previews = array_values(array_map(
            static fn (string $p): string => ltrim($p, '/'),
            (array) ($row['previews'] ?? []),
        ));

        /** @var list<string> $stages */
        $stages = array_values(array_map(
            static fn (mixed $stage): string => (string) $stage,
            (array) ($row['stages'] ?? []),
        ));

        return new self(
            id: (string) $row['id'],
            audience: (string) $row['audience'],
            keyStage: KeyStage::span($stages),
            keyStageAccessible: KeyStage::accessible($stages),
            stages: KeyStage::normalize($stages),
            title: (string) $row['title'],
            description: KeyStage::interpolate((string) $row['description']),
            pdf: $pdf,
            thumbnail: ltrim((string) $row['thumbnail'], '/'),
            previews: $previews,
            pages: (int) $row['pages'],
            paper: (string) $row['paper'],
            educationalLevel: KeyStage::educationalLevels($stages),
            typicalAgeRange: KeyStage::typicalAgeRange($stages),
            bytes: $bytes,
        );
    }

    /** @return list<self> */
    public static function all(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = (array) config('educators.handouts', []);

        return array_map(self::fromConfig(...), $rows);
    }

    /** Root-relative URL of the PDF, e.g. /handouts/foo.pdf. */
    public function url(): string
    {
        return '/'.$this->pdf;
    }

    /** Filename offered by the browser's save dialog. */
    public function filename(): string
    {
        return basename($this->pdf);
    }

    public function thumbnailUrl(): string
    {
        return '/'.$this->thumbnail;
    }

    /** @return list<string> */
    public function previewUrls(): array
    {
        return array_map(static fn (string $p): string => '/'.$p, $this->previews);
    }

    /** "A4, 4 pages, 1.3 MB" — the size is omitted when the file is missing. */
    public function downloadMeta(): string
    {
        $parts = [
            $this->paper,
            trans_choice(':count page|:count pages', $this->pages),
        ];

        if ($this->bytes !== null) {
            $parts[] = Format::fileSize($this->bytes);
        }

        return implode(', ', $parts);
    }
}
