<label class="block">{{ __('Catalogue') }}
    <select name="catalogue" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)">
        <option value="solar">{{ __('Solar-system catalogue') }}</option><option value="starter">{{ __('Star and deep-sky starter catalogue') }}</option><option value="exoplanet">{{ __('Exoplanet catalogue') }}</option>
    </select>
</label>
<label class="block">{{ __('Exact target ID') }}<input name="targetId" type="text" maxlength="160" required placeholder="planet-saturn" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></label>
<label class="block">{{ __('Target label') }}<input name="targetLabel" type="text" maxlength="200" required placeholder="Saturn" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></label>
