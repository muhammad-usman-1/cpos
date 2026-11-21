<x-layouts.app :title="__('SMS and WhatsApp')">
    <x-slot:actions>
        <a href="{{ route('notifications.index') }}" class="btn btn-secondary">{{ __('Message log') }}</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('messaging.settings.update') }}" class="card card-body space-y-4" data-provider-form>
            @csrf @method('PUT')
            <h2 class="font-semibold">{{ __('Provider') }}</h2>
            <x-select name="messaging_provider" :label="__('Send messages through')" :options="collect($providers)->mapWithKeys(fn ($p) => [$p->value => $p->label()])" :selected="$settings['messaging_provider']" required data-provider-select />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select name="messaging_channel" :label="__('Channel')" :options="['sms' => 'SMS', 'whatsapp' => __('WhatsApp')]" :selected="$settings['messaging_channel']" required />
                <x-select name="message_language" :label="__('Default language')" :options="$languages" :selected="$settings['message_language']" required />
                <x-field name="shop_phone" :label="__('Shop phone (in messages)')" :value="$settings['shop_phone']" />
            </div>

            <fieldset class="space-y-3 rounded-md border border-slate-200 p-4" data-provider-fields="http">
                <legend class="px-1 text-sm font-medium">{{ __('SMS gateway') }}</legend>
                <x-field name="http_gateway_url" :label="__('API URL with placeholders')" :value="$settings['http_gateway_url']" placeholder="https://provider.pk/api/send?key={key}&sender={sender}&to={phone}&msg={message}" hint="Use {phone}, {message}, {key} and {sender}. Copy the URL from your SMS provider's API page." />
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-select name="http_gateway_method" :label="__('Method')" :options="['GET' => 'GET', 'POST' => __('POST (form)')]" :selected="$settings['http_gateway_method']" />
                    <x-field name="http_gateway_sender" :label="__('Sender / mask')" :value="$settings['http_gateway_sender']" />
                    <x-field name="http_gateway_key" :label="__('API key')" type="password" :placeholder="$secrets['http_gateway_key'] ? __('Saved, leave blank to keep') : ''" autocomplete="off" />
                </div>
            </fieldset>

            <fieldset class="space-y-3 rounded-md border border-slate-200 p-4" data-provider-fields="twilio">
                <legend class="px-1 text-sm font-medium">{{ __('Twilio') }}</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-field name="twilio_sid" :label="__('Account SID')" :value="$settings['twilio_sid']" />
                    <x-field name="twilio_token" :label="__('Auth token')" type="password" :placeholder="$secrets['twilio_token'] ? __('Saved, leave blank to keep') : ''" autocomplete="off" />
                    <x-field name="twilio_from" :label="__('From number')" :value="$settings['twilio_from']" placeholder="+14155238886" />
                </div>
            </fieldset>

            <fieldset class="space-y-3 rounded-md border border-slate-200 p-4" data-provider-fields="whatsapp_cloud">
                <legend class="px-1 text-sm font-medium">{{ __('WhatsApp Business Cloud API') }}</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="whatsapp_phone_number_id" :label="__('Phone number ID')" :value="$settings['whatsapp_phone_number_id']" />
                    <x-field name="whatsapp_token" :label="__('Permanent access token')" type="password" :placeholder="$secrets['whatsapp_token'] ? __('Saved, leave blank to keep') : ''" autocomplete="off" />
                </div>
                <p class="text-xs text-slate-500">{{ __('Meta only delivers free text to customers who messaged the shop in the last 24 hours. For other customers Meta needs approved templates; until those are set up, use SMS for automatic messages.') }}</p>
            </fieldset>

            <h2 class="pt-2 font-semibold">{{ __('Automatic messages') }}</h2>
            <div class="space-y-2">
                @foreach ($events as $event)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="hidden" name="{{ $event->settingKey() }}" value="0">
                        <input type="checkbox" name="{{ $event->settingKey() }}" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300" @checked($settings[$event->settingKey()] === '1')>
                        <span><span class="font-medium">{{ $event->label() }}</span> <span class="text-slate-500">· {{ $event->description() }}</span></span>
                    </label>
                @endforeach
            </div>
            <x-field name="reminder_after_days" :label="__('Remind to collect after (days)')" type="number" min="1" max="60" :value="$settings['reminder_after_days']" required class="max-w-xs" />
            <button class="btn btn-primary">{{ __('Save settings') }}</button>
        </form>

        <div class="space-y-6">
            <form method="POST" action="{{ route('messaging.test') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="font-semibold">{{ __('Send a test message') }}</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-field name="phone" :label="__('Phone')" placeholder="03001234567" required />
                    <x-field name="message" :label="__('Message')" value="Test message from {{ $settings['shop_name'] }}" required class="sm:col-span-2" />
                </div>
                <button class="btn btn-secondary">{{ __('Send test now') }}</button>
                <p class="text-xs text-slate-500">{{ __('Uses the saved settings. Save first if you changed anything.') }}</p>
            </form>

            <form method="POST" action="{{ route('messaging.templates.update') }}" class="card card-body space-y-5">
                @csrf @method('PUT')
                <h2 class="font-semibold">{{ __('Message texts') }}</h2>
                @foreach ($events as $event)
                    <div>
                        <div class="mb-1 font-medium">{{ $event->label() }}</div>
                        <p class="mb-2 text-xs text-slate-500">{{ __('Placeholders:') }} @foreach ($event->placeholders() as $placeholder)<code class="me-1 rounded bg-slate-100 px-1">{{ '{'.$placeholder.'}' }}</code>@endforeach</p>
                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach ($languages as $code => $languageLabel)
                                <div>
                                    <label class="label text-xs">{{ $languageLabel }}</label>
                                    <textarea name="templates[{{ $event->value }}][{{ $code }}]" rows="3" class="input" dir="{{ $code === 'ur' ? 'rtl' : 'ltr' }}">{{ $templates->get($event->value)?->get($code)?->body }}</textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <p class="text-xs text-slate-500">{{ __('Each customer gets the language set on their profile, or the default language above.') }}</p>
                <button class="btn btn-primary">{{ __('Save texts') }}</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const select = document.querySelector('[data-provider-select]');
            const sync = () => document.querySelectorAll('[data-provider-fields]').forEach((fieldset) => fieldset.classList.toggle('hidden', fieldset.dataset.providerFields !== select.value));
            select.addEventListener('change', sync);
            sync();
        });
    </script>
</x-layouts.app>
