@props(['planet'])
<article class="surface space-y-3 p-5">
    <h2 class="text-xl"><a class="link-quiet" href="{{ route('exoplanets.show', $planet->id) }}">{{ $planet->name }}</a></h2>
    <p class="text-sm" style="color: var(--muted)">{{ \App\Support\Format::lightYears($planet->distancePc) }}</p>
    <p>{{ $planet->discoveryMethod ?? __('Discovery method unknown') }} @if($planet->discoveryYear) · {{ $planet->discoveryYear }} @endif</p>
    <a class="text-sm underline" href="{{ route('systems.show', $planet->hostId) }}">{{ __('System: :name', ['name' => $planet->hostName]) }} →</a>
    @if($planet->controversial)<p class="text-sm" style="color: var(--accent)">{{ __('Confirmation questioned in the literature') }}</p>@endif
</article>
