<?php

namespace App\Http\Requests;

use App\Models\Dealer;
use App\Models\DealerDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DealerRequest extends FormRequest
{
    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';
    public const PAN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';
    public const MOBILE = '/^[6-9][0-9]{9}$/';
    public const PINCODE = '/^[1-9][0-9]{5}$/';
    public const IFSC = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    public function authorize(): bool
    {
        return true; // controller authorises against the policy
    }

    protected function prepareForValidation(): void
    {
        $upper = fn ($v) => $v === null ? null : strtoupper(preg_replace('/\s+/', '', (string) $v));
        $digits = fn ($v) => $v === null ? null : substr(preg_replace('/\D/', '', (string) $v), -10);
        $numeric = fn ($v) => $v === null ? null : preg_replace('/\D/', '', (string) $v);

        $this->merge([
            'gst_no' => $upper($this->gst_no),
            'pan_no' => $upper($this->pan_no),
            'bank_ifsc' => $upper($this->bank_ifsc),
            'employee_code' => $upper($this->employee_code),
            'appointing_manager_code' => $upper($this->appointing_manager_code),
            'bank_account_no' => $numeric($this->bank_account_no),
            'cheque_1_no' => $numeric($this->cheque_1_no),
            'cheque_2_no' => $numeric($this->cheque_2_no),
            'security_deposit_amount' => $numeric($this->security_deposit_amount),
            'contact_phone' => $digits($this->contact_phone),
            'appointing_manager_mobile' => $digits($this->appointing_manager_mobile),
            'aadhaar_last4' => $numeric($this->aadhaar_last4),
            'pincode' => $numeric($this->pincode),
        ]);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $distributor = $this->input('partner_type') === 'distributor';
        $dealer = $this->input('partner_type') === 'dealer';
        $text = fn (int $max = 120) => ['nullable', 'string', "max:{$max}"];

        return [
            // Cover of the registration form
            'partner_type' => ['required', Rule::in(array_keys(Dealer::TYPES))],
            'application_type' => ['required', Rule::in(array_keys(Dealer::APPLICATION_TYPES))],
            'financial_year' => ['required', 'regex:/^20[0-9]{2}-[0-9]{2}$/'],
            'business_name' => ['required', 'string', 'max:200'],
            'entity_type' => ['required', Rule::in(Dealer::ENTITY_TYPES)],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'regex:'.self::MOBILE],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'state_head' => $text(),

            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => $text(200),
            'city' => ['required', 'string', 'max:80'],
            'tehsil' => $text(80),
            'district' => ['required', 'string', 'max:80'],
            'state' => ['required', Rule::in(Dealer::STATES)],
            'pincode' => ['required', 'regex:'.self::PINCODE],

            // Documents listed in the marketing policy
            'gst_registered' => ['required', 'boolean'],
            'gst_no' => ['nullable', 'required_if:gst_registered,1', 'regex:'.self::GSTIN],
            'pan_no' => ['required', 'regex:'.self::PAN],
            'aadhaar_last4' => ['nullable', 'digits:4'],
            'has_pesticide_licence' => ['required', 'boolean'],
            'pesticide_licence_no' => ['nullable', 'required_if:has_pesticide_licence,1', 'string', 'max:60'],
            'pesticide_licence_expiry' => ['nullable', 'required_if:has_pesticide_licence,1', 'date'],
            'has_fertilizer_licence' => ['required', 'boolean'],
            'fertilizer_licence_no' => ['nullable', 'required_if:has_fertilizer_licence,1', 'string', 'max:60'],
            'fertilizer_licence_expiry' => ['nullable', 'required_if:has_fertilizer_licence,1', 'date'],

            // Security deposit (Rs 21,000 for a distributor) and 2 security cheques
            'security_deposit_amount' => [$distributor ? 'required' : 'nullable', 'integer', $distributor ? 'min:'.Dealer::DISTRIBUTOR_SECURITY_DEPOSIT : 'min:0', 'max:100000000'],
            'security_deposit_mode' => [$distributor ? 'required' : 'nullable', Rule::in(Dealer::DEPOSIT_MODES)],
            'security_deposit_ref' => [$distributor ? 'required' : 'nullable', 'string', 'max:60'],
            'security_deposit_date' => [$distributor ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'bank_account_name' => [$distributor ? 'required' : 'nullable', 'string', 'max:120'],
            'bank_name' => [$distributor ? 'required' : 'nullable', $distributor ? Rule::in(Dealer::NATIONALISED_BANKS) : 'string', 'max:120'],
            'bank_branch' => $text(),
            'bank_account_no' => [$distributor && $creating ? 'required' : 'nullable', 'digits_between:6,20'],
            'bank_ifsc' => [$distributor ? 'required' : 'nullable', 'regex:'.self::IFSC],
            'cheque_1_no' => [$distributor ? 'required' : 'nullable', 'digits:6'],
            'cheque_2_no' => [$distributor ? 'required' : 'nullable', 'digits:6', 'different:cheque_1_no'],
            'current_account' => $distributor && $creating ? ['accepted'] : ['nullable'],

            // Marketing policy (distributor) or dealer agreement (dealer)
            'cash_discount_slab' => [$distributor ? 'required' : 'nullable', Rule::in(array_keys(Dealer::CASH_DISCOUNT_SLABS))],
            'agreement_date' => [$dealer ? 'required' : 'nullable', 'date'],
            'signatory_name' => ['required', 'string', 'max:120'],
            'signatory_designation' => ['required', Rule::in(Dealer::SIGNATORY_DESIGNATIONS)],
            'witness1_name' => [$dealer ? 'required' : 'nullable', 'string', 'max:120'],
            'witness1_address' => $text(200),
            'witness2_name' => [$dealer ? 'required' : 'nullable', 'string', 'max:120'],
            'witness2_address' => $text(200),

            // "TM & Above" block
            'employee_code' => ['required', 'string', 'max:30'],
            'manager_name' => $text(),
            'appointing_manager_name' => ['required', 'string', 'max:120'],
            'appointing_manager_code' => ['required', 'string', 'max:30'],
            'appointing_manager_designation' => $text(),
            'appointing_manager_mobile' => ['nullable', 'regex:'.self::MOBILE],
            'appointing_manager_area' => $text(),
            'appointing_manager_state' => ['nullable', Rule::in(Dealer::STATES)],
            'reporting_head_name' => $text(),
            'reporting_head_designation' => $text(),

            // Location captured on the employee's device at the premises
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'geo_captured_at' => ['nullable', 'date'],

            'documents' => ['nullable', 'array', 'max:30'],
            'documents.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
            'documents.*.category' => ['required', Rule::in(array_keys(DealerDocument::CATEGORIES))],
            'documents.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'documents.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'documents.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'documents.*.captured_at' => ['nullable', 'date'],

            'visit_notes' => ['nullable', 'string', 'max:2000'],
            'terms_accepted' => $creating ? ['accepted'] : ['nullable'],
            'consent' => $creating ? ['accepted'] : ['nullable'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $gst = $this->input('gst_no');
            $pan = $this->input('pan_no');
            if ($this->boolean('gst_registered') && $gst && $pan
                && preg_match(self::GSTIN, $gst) && preg_match(self::PAN, $pan) && substr($gst, 2, 10) !== $pan) {
                $validator->errors()->add('gst_no', 'GSTIN does not match the PAN. Characters 3 to 12 of a GSTIN are the PAN.');
            }

            if ((float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', 'Capture the GPS location at the premises.');
            }

            if ($this->isMethod('post')) {
                $categories = collect($this->input('documents', []))->pluck('category');
                if (! $categories->contains('shop_front')) {
                    $validator->errors()->add('documents', 'Take at least one photo of the shop / office front.');
                }
                if (! $categories->contains('signature')) {
                    $validator->errors()->add('signature', 'The dealer / distributor must sign.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a valid 10-digit mobile number.',
            'appointing_manager_mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'financial_year.regex' => 'Enter the financial year as 2026-27.',
            'pincode.regex' => 'Enter a valid 6-digit PIN code.',
            'gst_no.regex' => 'Enter a valid 15-character GSTIN.',
            'gst_no.required_if' => 'GSTIN is required for a GST-registered business.',
            'pan_no.regex' => 'Enter a valid 10-character PAN (e.g. ABCDE1234F).',
            'bank_ifsc.regex' => 'Enter a valid 11-character IFSC.',
            'bank_name.in' => 'Security cheques must be from a nationalised bank.',
            'cheque_1_no.digits' => 'A cheque number has 6 digits.',
            'cheque_2_no.digits' => 'A cheque number has 6 digits.',
            'cheque_2_no.different' => 'The two security cheques must have different numbers.',
            'current_account.accepted' => 'Security cheques must be from a current account.',
            'security_deposit_amount.min' => 'The security deposit for a distributor is ₹'.number_format(Dealer::DISTRIBUTOR_SECURITY_DEPOSIT).'.',
            'aadhaar_last4.digits' => 'Enter only the last 4 digits of the Aadhaar number.',
            'latitude.required' => 'Capture the GPS location at the premises.',
            'longitude.required' => 'Capture the GPS location at the premises.',
            'terms_accepted.accepted' => 'The dealer / distributor must accept the terms.',
            'consent.accepted' => 'The dealer must agree to verification of these details.',
            '*.required_if' => 'This field is required.',
            'documents.*.file.max' => 'Each file must be 10 MB or smaller.',
            'documents.*.file.mimes' => 'Upload a photo (JPG, PNG, HEIC) or a PDF.',
        ];
    }

    /** Dealer attributes ready to save (excludes documents and the acceptance checkboxes). */
    public function dealerAttributes(): array
    {
        $data = $this->safe()->except(['documents', 'consent', 'terms_accepted', 'current_account']);
        $data['gst_no'] = $this->boolean('gst_registered') ? $data['gst_no'] : null;
        foreach (['pesticide', 'fertilizer'] as $kind) {
            if (! $this->boolean("has_{$kind}_licence")) {
                $data["{$kind}_licence_no"] = null;
                $data["{$kind}_licence_expiry"] = null;
            }
        }

        // Fields that belong only to the other form type are cleared.
        if ($data['partner_type'] === 'dealer') {
            $data['cash_discount_slab'] = null;
        } else {
            foreach (['agreement_date', 'witness1_name', 'witness1_address', 'witness2_name', 'witness2_address'] as $key) {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
