<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Models\CommunityRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class ReleaseCatalog
{
    public static function validVersion(string $version): bool
    {
        return strlen($version) <= 40 && preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version) === 1;
    }

    public static function validCommit(string $commit): bool
    {
        return preg_match('/^[a-f0-9]{40}$/D', $commit) === 1;
    }

    /** @return array{title:string,summary:string,sections:array<string,list<string>>} */
    public function notes(string $version): array
    {
        if (! self::validVersion($version) || $version === '0.0.0') {
            throw new RuntimeException('Choose a valid community release version.');
        }
        $path = config('releases.notes_path').'/'.$version.'.json';
        if (! is_file($path) || filesize($path) > 32768) {
            throw new RuntimeException('Reviewed community notes are missing or too large.');
        }
        $data = json_decode(file_get_contents($path) ?: '', true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || array_diff(array_keys($data), ['title', 'summary', 'sections']) !== []) {
            throw new RuntimeException('Release notes must contain only title, summary and sections.');
        }
        $notes = Validator::make($data, [
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
            'sections' => ['required', 'array', 'min:1', 'max:6'],
            'sections.*' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'sections.*.*' => ['required', 'string', 'max:500'],
        ])->validate();
        $this->plainText($notes['title']);
        $this->plainText($notes['summary']);
        foreach ($notes['sections'] as $heading => $items) {
            if (! is_string($heading) || preg_match('/^-?[0-9]+$/D', $heading) || mb_strlen($heading) > 80) {
                throw new RuntimeException('Section headings must be short text.');
            }
            $this->plainText($heading);
            foreach ($items as $item) {
                $this->plainText($item);
            }
        }

        return $notes;
    }

    private function plainText(string $text): void
    {
        if (trim($text) === '' || preg_match('/[<>\x00-\x1f\x7f]/u', $text)) {
            throw new RuntimeException('Release notes must be plain, single-line text.');
        }
    }

    /** @return Collection<int, CommunityRelease> */
    public function published(): Collection
    {
        $current = (string) config('releases.version');
        if (! self::validVersion($current) || $current === '0.0.0') {
            return collect();
        }

        return CommunityRelease::query()->whereNotNull('published_at')->get()
            ->filter(fn (CommunityRelease $release) => self::validVersion($release->version) && $release->version !== '0.0.0' && version_compare($release->version, $current, '<='))
            ->sort(fn (CommunityRelease $a, CommunityRelease $b) => version_compare($b->version, $a->version))->values();
    }
}
