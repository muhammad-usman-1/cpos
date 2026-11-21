<?php

namespace App\Http\Controllers;

use App\Enums\FamilyRelation;
use App\Enums\MeasurementTemplate;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Measurement;
use App\Models\Setting;
use App\Services\MeasurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeasurementController extends Controller
{
    public function __construct(private MeasurementService $measurements) {}

    /**
     * Measurement form, pre-filled from the current version or a chosen older one (MSR09).
     */
    public function create(Request $request, CustomerProfile $profile): View
    {
        $profile->load('customer', 'measurements.takenBy');

        $source = $request->query('from')
            ? $profile->measurements->firstWhere('id', (int) $request->query('from'))
            : $profile->measurements->firstWhere('is_current', true);

        $source?->load('values');

        return view('measurements.create', [
            'profile' => $profile,
            'relations' => array_filter(FamilyRelation::cases(), fn (FamilyRelation $relation) => $relation !== FamilyRelation::Self),
            'templates' => MeasurementTemplate::cases(),
            'source' => $source,
            'fields' => $this->measurements->fieldsFor($profile),
        ]);
    }

    public function store(Request $request, CustomerProfile $profile): RedirectResponse
    {
        // The person's details (name, relation, phone, form type) can be changed on the same page.
        $profileData = null;
        if ($request->has('profile') && $request->user()->can('customers.manage')) {
            $details = (array) $request->input('profile');
            if (array_key_exists('phone', $details)) {
                $details['phone'] = Customer::normalizePhone($details['phone']);
            }

            $profile->load('customer');
            $profileData = Validator::make($details, CustomerProfile::detailRules($profile->customer, $profile), CustomerProfile::detailMessages())
                ->setAttributeNames(['name' => __('Name'), 'phone' => __('Phone'), 'relation' => __('Relation'), 'template' => __('Measurement form')])
                ->validate();
        }

        $validated = $request->validate([
            'unit' => ['required', Rule::in([Measurement::INCHES, Measurement::CENTIMETRES])],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'remove_photo' => ['boolean'],
        ]);

        $before = $profile->currentMeasurement?->id;
        $measurement = $this->measurements->save(
            $profile,
            $validated['values'] ?? [],
            $validated['unit'],
            $validated['notes'] ?? null,
            $request->file('photo'),
            ! $request->boolean('remove_photo'),
        );

        // Details are saved after the measurements, so values typed in the old form are kept as a version.
        if ($profileData) {
            $templateChanged = $profileData['template'] !== $profile->template->value;
            $profile->update($profileData);

            if ($templateChanged) {
                return redirect()->route('measurements.create', $profile)
                    ->with('success', __('Saved. :name now uses the :form form; fill in the measurements below.', ['name' => $profile->name, 'form' => $profile->template->label()]));
            }
        }

        $message = $measurement->id === $before
            ? __('Details saved.')
            : __('Saved as version :version for :name.', ['version' => $measurement->version, 'name' => $profile->name]);

        return redirect()->route('customers.show', $profile->customer_id)->with('success', $message);
    }

    /**
     * One version with the full history list beside it (MSR05).
     */
    public function show(Measurement $measurement): View
    {
        $measurement->load('values.field', 'takenBy', 'profile.customer', 'profile.measurements.takenBy');

        return view('measurements.show', ['measurement' => $measurement]);
    }

    /**
     * Printable measurement slip without an order (MSR08). The order version adds order number and delivery date.
     */
    public function print(Measurement $measurement): View
    {
        $measurement->load('values.field', 'profile.customer');

        return view('measurements.slip', [
            'measurement' => $measurement,
            'order' => null,
            'shopName' => Setting::get('shop_name'),
        ]);
    }
}
