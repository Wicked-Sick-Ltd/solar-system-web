<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Og\OgImageRenderer;
use App\Support\ShareImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/** Regenerate the committed fallback after changing public branding or card design. */
final class GenerateDefaultShareImage extends Command
{
    protected $signature = 'og:generate-default';

    protected $description = 'Render the default share image using the configured public name and tagline';

    public function handle(OgImageRenderer $renderer): int
    {
        try {
            $png = $renderer->render((string) config('site.name'), (string) config('site.tagline'));
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
