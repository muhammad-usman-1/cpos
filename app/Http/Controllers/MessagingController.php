<?php

namespace App\Http\Controllers;

use App\Enums\MessagingProvider;
use App\Enums\NotificationEvent;
use App\Jobs\SendNotification;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Provider settings, message templates in English and Urdu, and the message log (NTF01, NTF02).
 */
class MessagingController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function log(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'event' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        return view('messaging.log', [
            'logs' => NotificationLog::query()
                ->with('customer', 'creator')
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('phone', 'like', "%$term%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%"))))
                ->latest('id')
                ->paginate(40)
                ->withQueryString(),
            'events' => NotificationEvent::cases(),
            'provider' => MessagingProvider::tryFrom((string) Setting::get('messaging_provider')),
        ]);
    }

    public function resend(NotificationLog $log): RedirectResponse
    {
        abort_if($log->status === NotificationLog::SENT, 422, __('This message was already sent.'));

        $log->update(['status' => NotificationLog::QUEUED, 'provider' => Setting::get('messaging_provider'), 'channel' => Setting::get('messaging_channel'), 'error' => null]);
        SendNotification::dispatch($log->id);

        return back()->with('success', __('Message queued again.'));
    }

    public function settings(): View
    {
        return view('messaging.settings', [
            'settings' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)]),
            'secrets' => collect(Setting::SECRET_KEYS)->mapWithKeys(fn ($key) => [$key => Setting::hasSecret($key)]),
            'providers' => MessagingProvider::cases(),
            'events' => NotificationEvent::cases(),
            'templates' => MessageTemplate::query()->get()->groupBy(fn ($template) => $template->event->value)->map(fn ($group) => $group->keyBy('language')),
            'languages' => MessageTemplate::LANGUAGES,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'messaging_provider' => ['required', Rule::enum(MessagingProvider::class)],
            'messaging_channel' => ['required', Rule::in(['sms', 'whatsapp'])],
            'message_language' => ['required', Rule::in(array_keys(MessageTemplate::LANGUAGES))],
            'shop_phone' => ['nullable', 'string', 'max:30'],
            'http_gateway_url' => ['nullable', 'url', 'max:500'],
            'http_gateway_method' => ['required', Rule::in(['GET', 'POST'])],
            'http_gateway_sender' => ['nullable', 'string', 'max:30'],
            'http_gateway_key' => ['nullable', 'string', 'max:500'],
            'twilio_sid' => ['nullable', 'string', 'max:64'],
            'twilio_token' => ['nullable', 'string', 'max:128'],
            'twilio_from' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:40'],
            'whatsapp_token' => ['nullable', 'string', 'max:1000'],
            'reminder_after_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $toggles = collect(NotificationEvent::cases())->mapWithKeys(fn (NotificationEvent $event) => [$event->settingKey() => $request->boolean($event->settingKey()) ? '1' : '0'])->all();

        Setting::put(array_map(fn ($value) => $value === null ? '' : (string) $value, $validated) + $toggles);

        return back()->with('success', __('Messaging settings saved.'));
    }

    public function updateTemplates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.*' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['templates'] as $event => $languages) {
            if (! NotificationEvent::tryFrom($event)) {
                continue;
            }

            foreach ($languages as $language => $body) {
                if (! array_key_exists($language, MessageTemplate::LANGUAGES) || trim((string) $body) === '') {
                    continue;
                }

                $template = MessageTemplate::query()->firstOrNew(['event' => $event, 'language' => $language]);

                if ($template->body !== $body) {
                    $template->fill(['body' => $body])->save();
                }
            }
        }

        return back()->with('success', __('Message templates saved.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        $log = $this->notifications->sendTest($validated['phone'], $validated['message']);

        return back()->with($log->status === NotificationLog::FAILED ? 'error' : 'success', match ($log->status) {
            NotificationLog::SENT => 'Test message sent to '.$log->phone.'.',
            NotificationLog::LOGGED => 'Provider is set to "log only", so the message was recorded but not sent.',
            default => 'Sending failed: '.$log->error,
        });
    }
}
