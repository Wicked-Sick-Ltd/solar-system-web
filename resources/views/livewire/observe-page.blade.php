<div class="mx-auto max-w-3xl">
    <x-page-header :title="__('Observe the sky')" :eyebrow="__('From your part of Earth')"
        :lead="__('Find a target, check its position and look at the conditions before heading outside. You can use the observing tools without an account.')" />
    <section class="surface mb-6 p-6" aria-labelledby="observe-workspace">
        <h2 id="observe-workspace" class="text-2xl">{{ __('Your equipment and observing sites') }}</h2>
        <p class="mt-3 leading-relaxed">{{ __('Keep your telescopes, binoculars, eyepieces and favourite sites in this browser. No account is needed.') }}</p>
        <a class="mt-4 inline-block underline" href="{{ route('observatory') }}">{{ __('Open your observatory') }} →</a>
        <p><a class="mt-3 inline-block underline" href="{{ route('observing.journal') }}">{{ __('Your observing lists and journal') }} →</a></p>
    </section>
    <ol class="space-y-5">
        <li class="surface p-6">
            <h2 class="text-2xl">{{ __('1. Choose a target') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('Start with a planet. Its object page has an “In the sky” section when a position is available.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('planets.index') }}">{{ __('Choose a planet') }} →</a>
        </li>
        <li class="surface p-6">
            <h2 class="text-2xl">{{ __('2. Set your observing location') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('Enter an approximate location in Your settings, or use the location controls on an object page. Altitude tells you how high to look above the horizon; azimuth gives the compass direction.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('settings') }}">{{ __('Your settings') }} →</a>
        </li>
        <li class="surface p-6">
            <h2 class="text-2xl">{{ __('3. Check the conditions') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('“Up after dark” means above the horizon while the Sun is below the twilight threshold used by the tool. It does not guarantee a clear view: clouds, buildings, brightness and your equipment all matter. Read the accuracy note beside each position.') }}</p>
            <p class="mt-3 leading-relaxed">{{ __('An optional account lets you save email alerts. Saved alerts store their observing location with your account; ordinary browsing does not require registration.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('privacy') }}">{{ __('How location and alerts are handled') }} →</a>
        </li>
    </ol>
    <section class="surface mt-6 p-6" aria-labelledby="observe-night-planner">
        <h2 id="observe-night-planner" class="text-2xl">{{ __('Plan a night with the Moon and planets') }}</h2>
        <p class="mt-3 leading-relaxed">{{ __('Choose a date and approximate location for altitude charts, darkness and observing windows, with the calculation limits explained.') }}</p>
        <a class="mt-4 inline-block underline" href="{{ route('observe.night') }}">{{ __('Plan a night') }} →</a>
    </section>
    <section class="mt-8" aria-labelledby="observe-events">
        <h2 id="observe-events" class="text-2xl">{{ __('Follow astronomical events') }}</h2>
        <p class="mt-3 leading-relaxed">{{ __('Earth close approaches describe how near objects pass. Proximity alone does not tell you whether an object is bright enough to see.') }}</p>
        <a class="mt-4 inline-block underline" href="{{ route('close-approaches') }}">{{ __('Upcoming close approaches') }} →</a>
        <p class="mt-5 leading-relaxed">{{ __('Meteor showers connect the streaks in our sky to streams of material and their parent bodies. Explore the catalogue and compare observation campaigns; its date filter is an approximate activity guide.') }}</p>
        <a class="mt-3 inline-block underline" href="{{ route('meteor-showers.index') }}">{{ __('Explore meteor showers') }} →</a>
    </section>
</div>
