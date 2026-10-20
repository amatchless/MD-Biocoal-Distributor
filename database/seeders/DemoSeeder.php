<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo team and sample onboardings for trying the app out: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $make = function (string $name, string $email, string $role, ?User $manager = null, ?string $territory = null) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'Password@123', 'manager_id' => $manager?->id, 'territory' => $territory, 'is_active' => true,
            ]);
            $user->syncRoles([$role]);

            return $user;
        };

        $east = $make('Rakesh Mondal', 'manager.east@mdbiocoals.com', User::MANAGER, null, 'West Bengal');
        $north = $make('Anita Sharma', 'manager.north@mdbiocoals.com', User::MANAGER, null, 'Uttar Pradesh');
        $e1 = $make('Sourav Das', 'sourav@mdbiocoals.com', User::EMPLOYEE, $east, 'Nadia');
        $e2 = $make('Priya Ghosh', 'priya@mdbiocoals.com', User::EMPLOYEE, $east, 'Hooghly');
        $e3 = $make('Vikas Yadav', 'vikas@mdbiocoals.com', User::EMPLOYEE, $north, 'Lucknow');

        if (Dealer::exists()) {
            return;
        }

        $samples = [
            [$e1, 'dealer', 'Krishi Seva Kendra', 'Proprietorship', 'Krishnanagar', 'Nadia', 'West Bengal', '741101', 23.4058, 88.4907, '19AAKPD1234F1Z3', 'AAKPD1234F', 'approved'],
            [$e1, 'distributor', 'Bengal Agro Distributors', 'Partnership', 'Ranaghat', 'Nadia', 'West Bengal', '741201', 23.1765, 88.5674, '19AAGFB5678K1Z2', 'AAGFB5678K', 'submitted'],
            [$e2, 'dealer', 'Annapurna Fertilizers', 'Proprietorship', 'Chinsurah', 'Hooghly', 'West Bengal', '712101', 22.9010, 88.3899, '19BBNPA4321Q1Z9', 'BBNPA4321Q', 'needs_info'],
            [$e3, 'dealer', 'Kisan Agri Mart', 'Proprietorship', 'Malihabad', 'Lucknow', 'Uttar Pradesh', '226102', 26.9206, 80.7140, '09CCQPK8765L1Z1', 'CCQPK8765L', 'under_review'],
        ];

        foreach ($samples as [$by, $type, $name, $entity, $city, $district, $state, $pin, $lat, $lng, $gst, $pan, $status]) {
            $dealer = new Dealer([
                'partner_type' => $type, 'business_name' => $name, 'entity_type' => $entity,
                'application_type' => 'new', 'financial_year' => Dealer::currentFinancialYear(),
                'contact_name' => 'Owner of '.$name, 'contact_phone' => '98300'.rand(10000, 99999),
                'address_line1' => 'Main Road', 'city' => $city, 'district' => $district, 'state' => $state, 'pincode' => $pin,
                'security_deposit_amount' => $type === 'distributor' ? Dealer::DISTRIBUTOR_SECURITY_DEPOSIT : null,
                'security_deposit_mode' => $type === 'distributor' ? 'RTGS' : null,
                'bank_name' => 'State Bank of India', 'bank_account_name' => $name, 'bank_ifsc' => 'SBIN0001234',
                'cheque_1_no' => $type === 'distributor' ? '000101' : null, 'cheque_2_no' => $type === 'distributor' ? '000102' : null,
                'cash_discount_slab' => $type === 'distributor' ? 'days_1_15' : null,
                'agreement_date' => $type === 'dealer' ? now()->subDays(rand(1, 20)) : null,
                'witness1_name' => $type === 'dealer' ? 'Witness One' : null, 'witness2_name' => $type === 'dealer' ? 'Witness Two' : null,
                'signatory_name' => 'Owner of '.$name, 'signatory_designation' => $entity === 'Partnership' ? 'Partner' : 'Proprietor',
                'employee_code' => 'MDB'.str_pad((string) $by->id, 4, '0', STR_PAD_LEFT),
                'manager_name' => $by->manager?->name, 'appointing_manager_name' => $by->manager?->name, 'appointing_manager_code' => 'MDB-M'.$by->manager_id,
                'appointing_manager_area' => $by->manager?->territory, 'appointing_manager_state' => $state,
                'gst_registered' => true, 'gst_no' => $gst, 'pan_no' => $pan, 'aadhaar_last4' => (string) rand(1000, 9999),
                'has_pesticide_licence' => true, 'pesticide_licence_no' => 'PL/'.rand(1000, 9999), 'pesticide_licence_expiry' => now()->addDays(rand(10, 400)),
                'has_fertilizer_licence' => true, 'fertilizer_licence_no' => 'FL/'.rand(1000, 9999), 'fertilizer_licence_expiry' => now()->addDays(rand(30, 700)),
                'latitude' => $lat, 'longitude' => $lng, 'geo_accuracy' => rand(5, 40), 'geo_captured_at' => now()->subDays(rand(1, 20)),
                'consent_given' => true, 'consent_at' => now(),
                'visit_notes' => 'Sample record created by the demo seeder.',
            ]);
            $dealer->created_by = $by->id;
            $dealer->status = $status;
            $dealer->terms_version = Dealer::TERMS_VERSIONS[$type];
            $dealer->terms_accepted_at = now();
            if ($status === 'needs_info') {
                $dealer->review_remarks = 'Please upload a clear copy of the fertilizer licence.';
                $dealer->reviewed_by = $by->manager_id;
                $dealer->reviewed_at = now();
            }
            $dealer->save();

            $dealer->activities()->create(['user_id' => $by->id, 'action' => 'submitted']);
        }
    }
}
