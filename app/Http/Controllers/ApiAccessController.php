<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApiAccessRequest;
use App\Services\ApiAccess\ApiAccessNotifier;
use App\Support\AcademicEmail;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ApiAccessController extends Controller
{
    public function create(): View
    {
        app(Seo::class)->title(__('Request API access'))
            ->description(__('Ask for a Public Universe API key for bulk or automated catalogue use. Academic addresses can be flagged for the free institution tier.'));

        return view('api-access.create', [
            'volumes' => config('api_access.volumes'),
        ]);
    }

    public function store(Request $request, ApiAccessNotifier $notifier): RedirectResponse
    {
        $input = $request->only([
            'organisation', 'contact_name', 'email', 'use_case', 'expected_volume', 'website',
        ]);
        $volumes = array_keys(is_array(config('api_access.volumes')) ? config('api_access.volumes') : []);
        $validator = Validator::make($input, [
            'organisation' => ['required', 'string', 'min:2', 'max:200', 'not_regex:/[\r\n]/'],
            'contact_name' => ['required', 'string', 'min:2', 'max:200', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'use_case' => ['required', 'string', 'min:20', 'max:5000'],
            'expected_volume' => ['required', 'string', Rule::in($volumes)],
            'website' => ['nullable', 'string', 'max:0'],
        ], ['website.max' => __('Please leave the website field empty.')]);

        if ($validator->fails()) {
            return to_route('api-access')->withErrors($validator)->withInput($this->safeInput($input));
        }

        $data = $validator->validated();
        $record = ApiAccessRequest::query()->create([
            'organisation' => $data['organisation'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'use_case' => $data['use_case'],
            'expected_volume' => $data['expected_volume'],
            'institution_eligible' => AcademicEmail::isEligible($data['email']),
        ]);
        $notifier->notify($record);

        return to_route('api-access')->with('api_access_sent', true)->with(
            'api_access_institution',
            $record->institution_eligible,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function safeInput(array $input): array
    {
        $safe = [];
        foreach ([
            'organisation' => 200,
            'contact_name' => 200,
            'email' => 254,
            'use_case' => 5000,
            'expected_volume' => 40,
        ] as $field => $length) {
            $safe[$field] = is_string($input[$field] ?? null) ? mb_substr($input[$field], 0, $length) : '';
        }

        return $safe;
    }
}
