<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Og\CatalogueFigures;
use App\Services\Og\OgImageRenderer;
use App\Support\ShareImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/** Regenerate the committed fallback after changing public branding or card design. */
final class GenerateDefaultShareImage extends Command
{
    protected $signature = 'og:generate-default';

    protected $description = 'Render the default share image using the configured public name, tagline and current catalogue counts';

    public function handle(OgImageRenderer $renderer, CatalogueFigures $catalogue): int
    {
        try {
            $figures = $catalogue->all();
            if ($figures === []) {
                $this->warn('Catalogue counts are unavailable; rendering the card without them.');
            }

            $png = $renderer->renderSite(
                name: (string) config('site.name'),
                tagline: (string) config('site.tagline'),
                figures: $figures,
                domain: ShareImage::domain(),
            );
            $path = public_path(ShareImage::DEFAULT_PATH);
            File::ensureDirectoryExists(dirname($path));
            if (File::put($path, $png) === false) {
                $this->error('Could not write the default share image.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error('Could not render the default share image: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Rendered '.$path.'. Inspect the image before committing or publishing it.');

        return self::SUCCESS;
    }
}
