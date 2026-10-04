<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\SiteFeedback;
use App\Services\Feedback\FeedbackDelivery;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class FeedbackController extends Controller
{
    public function create(FeedbackDelivery $delivery): View
    {
        app(Seo::class)->title(__('Feedback & contact'))
            ->description(__('Suggest a feature, report a bug or contact the Public Universe team privately.'));

        return view('feedback.create', ['canSend' => $delivery->isConfigured()]);
    }

    public function store(Request $request, FeedbackDelivery $delivery): RedirectResponse
    {
        $input = $request->only(['category', 'email', 'message', 'website']);
        $validator = Validator::make($input, [
            'category' => ['required', 'string', Rule::in(array_keys(config('feedback.categories')))],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
        ], ['website.max' => __('Please leave the website field empty.')]);

        if ($validator->fails()) {
            // Do not flash arbitrary request fields or unbounded message bodies.
            return to_route('feedback')->withErrors($validator)->withInput($this->safeInput($input));
        }

        if (! $delivery->isConfigured()) {
            return to_route('feedback')->withErrors(['delivery' => __('The form is temporarily unavailable. Please email hello@publicuniverse.net instead.')])
                ->withInput($this->safeInput($input));
        }

        try {
            $data = $validator->validated();
            $delivery->send(new SiteFeedback(config('feedback.categories.'.$data['category']), $data['message'], $data['email'] ?? null));
        } catch (Throwable) {
            // Transport errors can contain message contents and addresses; do not log them.
            return to_route('feedback')->withErrors(['delivery' => __('Your message could not be sent. Please try again later or email hello@publicuniverse.net.')])
                ->withInput($this->safeInput($input));
        }

        return to_route('feedback')->with('feedback_sent', true);
    }

    /** @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function safeInput(array $input): array
    {
        $safe = [];
        foreach (['category' => 20, 'email' => 254, 'message' => 5000] as $field => $length) {
            $safe[$field] = is_string($input[$field] ?? null) ? mb_substr($input[$field], 0, $length) : '';
        }

        return $safe;
    }
}
