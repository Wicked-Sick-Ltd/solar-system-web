<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\KeyStage;
use App\Support\Links;
use App\Support\Schema;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class PluginPage extends Component
{
    public function render(): View
    {
        app(Seo::class)
            ->title(__('Astronomy MCP Plugin for Claude Code, Cursor, Codex, ChatGPT & Copilot'))
            ->description(__('Use Public Universe astronomy data in ChatGPT, Codex, Cursor, GitHub Copilot and Claude Code. Setup instructions, reusable skills and example questions.'))
            ->jsonLd(Schema::softwareApplication(
                __(':site astronomy plugin', ['site' => config('site.name')]),
                __('MCP server and reusable skills that give AI coding assistants sourced solar-system, exoplanet and night-sky data.'),
                route('plugin'),
                ['Claude Code', 'Cursor', 'Codex', 'ChatGPT', 'GitHub Copilot'],
            ));

        return view('livewire.plugin-page', [
            'mcpUrl' => Links::mcp(),
            'repository' => (string) config('plugin.repository'),
            'pluginPath' => (string) config('plugin.path'),
            'pluginUrl' => (string) config('plugin.url'),
            'docsUrl' => (string) config('plugin.docs_url'),
            'connectionReady' => (bool) config('plugin.connection_ready'),
            'skills' => [
                'tonight-sky' => __('Plan an evening of stargazing for a place and date.'),
                'lesson-builder' => __('Prepare a lesson or worksheet for a UK Key Stage, with the matching US grades (for example :example).', [
                    'example' => KeyStage::compact('KS3'),
                ]),
                'space-fact-check' => __('Check an astronomy claim against catalogue sources.'),
                'object-explainer' => __('Explore an object at your preferred reading level.'),
                'close-approach-watch' => __('Put upcoming asteroid and comet flybys in context.'),
                'sky-this-week' => __('Prepare a sourced guide to the week’s sky.'),
                'solar-data-audit' => __('Compare catalogue values with their original sources.'),
            ],
        ]);
    }
}
