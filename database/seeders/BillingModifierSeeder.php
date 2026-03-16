<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingModifierSeeder extends Seeder
{
    public function run(): void
    {
        $modifiers = [
            // Telehealth
            ['code' => '95',  'name' => 'Synchronous Telemedicine',              'category' => 'telehealth',        'description' => 'Synchronous telemedicine service via real-time interactive audio and video'],
            ['code' => '93',  'name' => 'Audio-Only Telehealth',                 'category' => 'telehealth',        'description' => 'Synchronous telemedicine service via audio-only telephone'],
            ['code' => 'GT',  'name' => 'Interactive Telecommunications',        'category' => 'telehealth',        'description' => 'Via interactive audio and video telecommunication systems (legacy, replaced by 95)'],
            ['code' => 'GQ',  'name' => 'Asynchronous Telehealth (Store & Forward)', 'category' => 'telehealth',   'description' => 'Via asynchronous telecommunications (store-and-forward)'],
            ['code' => 'FR',  'name' => 'Supervising Practitioner at Distant Site', 'category' => 'telehealth',    'description' => 'Supervising practitioner present through telehealth at distant site'],
            ['code' => 'FQ',  'name' => 'Furnished via Telehealth in Federally Designated Area', 'category' => 'telehealth', 'description' => 'Telehealth service furnished using audio-only in a federally designated area'],

            // Supervision & Provider Level
            ['code' => 'HO',  'name' => 'Masters Degree Level',                 'category' => 'supervision',       'description' => 'Services provided by a masters-level clinician'],
            ['code' => 'HN',  'name' => 'Bachelors Degree Level',               'category' => 'supervision',       'description' => 'Services provided by a bachelors-level clinician'],
            ['code' => 'HQ',  'name' => 'Group Setting',                        'category' => 'supervision',       'description' => 'Services provided in a group setting'],
            ['code' => 'AH',  'name' => 'Clinical Psychologist',                'category' => 'supervision',       'description' => 'Services provided by a clinical psychologist'],
            ['code' => 'AJ',  'name' => 'Clinical Social Worker',               'category' => 'supervision',       'description' => 'Services provided by a clinical social worker'],
            ['code' => 'AS',  'name' => 'Physician Assistant',                  'category' => 'supervision',       'description' => 'Services provided by a physician assistant'],
            ['code' => 'SA',  'name' => 'Nurse Practitioner with Physician',    'category' => 'supervision',       'description' => 'Nurse practitioner rendering service with physician supervision'],
            ['code' => 'GC',  'name' => 'Resident Under Teaching Physician',    'category' => 'supervision',       'description' => 'Service performed by resident under direction of teaching physician'],
            ['code' => 'GE',  'name' => 'Resident Not Under Teaching Physician','category' => 'supervision',       'description' => 'Service performed by a resident without the presence of a teaching physician'],

            // Level of Service / Visit
            ['code' => '25',  'name' => 'Significant, Separately Identifiable E/M', 'category' => 'level_of_service', 'description' => 'Significant, separately identifiable E&M service by same physician on same day of a procedure'],
            ['code' => '26',  'name' => 'Professional Component',               'category' => 'level_of_service', 'description' => 'Professional (physician interpretation) component only'],
            ['code' => 'TC',  'name' => 'Technical Component',                  'category' => 'level_of_service', 'description' => 'Technical (equipment, supplies, technician) component only'],
            ['code' => '59',  'name' => 'Distinct Procedural Service',          'category' => 'level_of_service', 'description' => 'Distinct procedural service — different procedure, site, incision, or organ system'],
            ['code' => '76',  'name' => 'Repeat Procedure by Same Physician',   'category' => 'level_of_service', 'description' => 'Repeat procedure or service by same physician or other QHP'],
            ['code' => '77',  'name' => 'Repeat Procedure by Different Physician', 'category' => 'level_of_service', 'description' => 'Repeat procedure by another physician or other QHP'],
            ['code' => 'XE',  'name' => 'Separate Encounter',                   'category' => 'level_of_service', 'description' => 'Service that is distinct because it occurred during a separate encounter'],
            ['code' => 'XS',  'name' => 'Separate Structure',                   'category' => 'level_of_service', 'description' => 'Service that is distinct because performed on a separate organ/structure'],
            ['code' => 'XP',  'name' => 'Separate Practitioner',                'category' => 'level_of_service', 'description' => 'Service that is distinct because performed by a different practitioner'],
            ['code' => 'XU',  'name' => 'Unusual Non-Overlapping Service',      'category' => 'level_of_service', 'description' => 'Service that does not overlap usual components of the main service'],

            // Facility / Location
            ['code' => 'PO',  'name' => 'Services in Outpatient Hospital',      'category' => 'facility',          'description' => 'Excepted service provided in off-campus outpatient provider-based department'],
            ['code' => 'PN',  'name' => 'Non-Excepted Service in Off-Campus PBD', 'category' => 'facility',       'description' => 'Non-excepted service in off-campus outpatient provider-based department'],

            // Procedure Modifiers
            ['code' => '50',  'name' => 'Bilateral Procedure',                  'category' => 'procedure',         'description' => 'Bilateral procedure performed on both sides of the body at same session'],
            ['code' => 'LT',  'name' => 'Left Side',                            'category' => 'procedure',         'description' => 'Procedure performed on left side of body'],
            ['code' => 'RT',  'name' => 'Right Side',                           'category' => 'procedure',         'description' => 'Procedure performed on right side of body'],
            ['code' => '22',  'name' => 'Increased Procedural Services',        'category' => 'procedure',         'description' => 'Service greater than usually required (requires documentation)'],
            ['code' => '52',  'name' => 'Reduced Services',                     'category' => 'procedure',         'description' => 'Service partially reduced or eliminated at physician discretion'],
            ['code' => '53',  'name' => 'Discontinued Procedure',               'category' => 'procedure',         'description' => 'Procedure discontinued due to threat to patient well-being'],
            ['code' => '58',  'name' => 'Staged/Related Procedure During Postop', 'category' => 'procedure',      'description' => 'Staged or related procedure during the postoperative period'],
            ['code' => '62',  'name' => 'Two Surgeons',                         'category' => 'procedure',         'description' => 'Two surgeons performing distinct portions of a single procedure'],
            ['code' => '66',  'name' => 'Surgical Team',                        'category' => 'procedure',         'description' => 'Surgical team required for complex procedure'],
            ['code' => '80',  'name' => 'Assistant Surgeon',                    'category' => 'procedure',         'description' => 'Surgical assistant services'],

            // Anesthesia
            ['code' => 'AA',  'name' => 'Anesthesia by Anesthesiologist',       'category' => 'anesthesia',        'description' => 'Anesthesia services performed personally by anesthesiologist'],
            ['code' => 'AD',  'name' => 'Supervised by Anesthesiologist',       'category' => 'anesthesia',        'description' => 'Medical supervision by anesthesiologist, more than 4 concurrent procedures'],
            ['code' => 'QK',  'name' => 'Medical Direction 2-4 Concurrent',    'category' => 'anesthesia',        'description' => 'Medical direction of 2-4 concurrent anesthesia procedures by physician'],
            ['code' => 'QX',  'name' => 'CRNA with Medical Direction',          'category' => 'anesthesia',        'description' => 'CRNA service with medical direction by a physician'],
            ['code' => 'QY',  'name' => 'Medical Direction of One CRNA',        'category' => 'anesthesia',        'description' => 'Medical direction of one CRNA by an anesthesiologist'],
            ['code' => 'QZ',  'name' => 'CRNA Without Medical Direction',       'category' => 'anesthesia',        'description' => 'CRNA service without medical direction by a physician'],

            // Other Common
            ['code' => 'CR',  'name' => 'Catastrophe / Disaster',              'category' => 'other',             'description' => 'Services provided in catastrophe/disaster-related circumstances'],
            ['code' => 'ET',  'name' => 'Emergency Services',                   'category' => 'other',             'description' => 'Emergency services provided'],
            ['code' => 'SC',  'name' => 'Medically Necessary',                  'category' => 'other',             'description' => 'Medically necessary service or supply'],
        ];

        foreach ($modifiers as $mod) {
            DB::table('billing_modifiers')->updateOrInsert(
                ['code' => $mod['code']],
                array_merge($mod, ['is_active' => true])
            );
        }
    }
}
