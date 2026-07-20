<?php
/**
 * NEW FILE — save this as: includes/DocumentFieldConfig.php
 * ---------------------------------------------------------------
 * Single source of truth for which fields are shown, in what order,
 * with what label/type/validation, for each person-identity document
 * type. The dynamic form (dynamic_form_helpers.php) reads this and
 * generates the UI — nothing about the form is hardcoded in HTML.
 *
 * To add a new document type later:
 *   1. Add a DocumentClassifier keyword block for it (classification)
 *   2. Add a case to DocumentFieldExtractor::extract() (OCR mapping)
 *   3. Add one entry below (display/validation)
 * That's it — add.php / edit.php / view.php need no changes.
 *
 * field types supported by the renderer: text, date, select, textarea
 * 'db_key' is the column name in the `clients` table.
 * ---------------------------------------------------------------
 */
class DocumentFieldConfig
{
    private const CONFIG = [

        'student_id' => [
            'label' => 'Student ID',
            'fields' => [
                ['db_key' => 'id_number',   'label' => 'Student ID Number', 'type' => 'text', 'required' => true],
                ['db_key' => 'last_name',   'label' => 'Last Name',         'type' => 'text', 'required' => true],
                ['db_key' => 'first_name',  'label' => 'First Name',        'type' => 'text', 'required' => true],
                ['db_key' => 'middle_name', 'label' => 'Middle Name',       'type' => 'text'],
                ['db_key' => 'school',      'label' => 'School Name',       'type' => 'text'],
                ['db_key' => 'course',      'label' => 'Course',            'type' => 'text'],
                ['db_key' => 'year_level',  'label' => 'Year Level',        'type' => 'text'],
                ['db_key' => 'birth_date',  'label' => 'Date of Birth',     'type' => 'date'],
                ['db_key' => 'address',     'label' => 'Address',           'type' => 'textarea'],
            ],
        ],

        'national_id' => [
            'label' => 'Philippine National ID (PhilSys)',
            'fields' => [
                ['db_key' => 'id_number',   'label' => 'National ID Number (PCN)', 'type' => 'text', 'required' => true,
                 'validate' => '/^[A-Z0-9\- ]{4,20}$/i'],
                ['db_key' => 'last_name',   'label' => 'Last Name',   'type' => 'text', 'required' => true],
                ['db_key' => 'first_name',  'label' => 'First Name',  'type' => 'text', 'required' => true],
                ['db_key' => 'middle_name', 'label' => 'Middle Name', 'type' => 'text'],
                ['db_key' => 'birth_date',  'label' => 'Date of Birth', 'type' => 'date', 'required' => true],
                ['db_key' => 'sex',         'label' => 'Sex', 'type' => 'select', 'required' => true,
                 'options' => ['Male', 'Female', 'Other']],
                ['db_key' => 'address',     'label' => 'Address', 'type' => 'textarea', 'required' => true],
                ['db_key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
            ],
        ],

        'drivers_license' => [
            'label' => "Driver's License",
            'fields' => [
                ['db_key' => 'id_number',   'label' => 'License Number', 'type' => 'text', 'required' => true],
                ['db_key' => 'last_name',   'label' => 'Last Name',   'type' => 'text', 'required' => true],
                ['db_key' => 'first_name',  'label' => 'First Name',  'type' => 'text', 'required' => true],
                ['db_key' => 'middle_name', 'label' => 'Middle Name', 'type' => 'text'],
                ['db_key' => 'birth_date',  'label' => 'Date of Birth', 'type' => 'date'],
                ['db_key' => 'address',     'label' => 'Address', 'type' => 'textarea'],
                ['db_key' => 'sex',         'label' => 'Sex', 'type' => 'select',
                 'options' => ['Male', 'Female', 'Other']],
                ['db_key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
                ['db_key' => 'blood_type',  'label' => 'Blood Type', 'type' => 'text'],
                ['db_key' => 'id_expiration_date', 'label' => 'Expiration Date', 'type' => 'date'],
            ],
        ],

        'passport' => [
            'label' => 'Philippine Passport',
            'fields' => [
                ['db_key' => 'id_number',   'label' => 'Passport Number', 'type' => 'text', 'required' => true,
                 'validate' => '/^[A-Z0-9]{6,10}$/i'],
                ['db_key' => 'last_name',   'label' => 'Last Name',   'type' => 'text', 'required' => true],
                ['db_key' => 'first_name',  'label' => 'First Name',  'type' => 'text', 'required' => true],
                ['db_key' => 'middle_name', 'label' => 'Middle Name', 'type' => 'text'],
                ['db_key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'],
                ['db_key' => 'birth_date',  'label' => 'Date of Birth', 'type' => 'date'],
                ['db_key' => 'sex',         'label' => 'Sex', 'type' => 'select',
                 'options' => ['Male', 'Female', 'Other']],
                ['db_key' => 'birth_place', 'label' => 'Place of Birth', 'type' => 'text'],
                ['db_key' => 'id_issue_date', 'label' => 'Issue Date', 'type' => 'date'],
                ['db_key' => 'id_expiration_date', 'label' => 'Expiration Date', 'type' => 'date'],
            ],
        ],

        'birth_certificate' => [
            'label' => 'PSA Birth Certificate',
            'fields' => [
                ['db_key' => 'last_name',   'label' => "Child's Last Name",  'type' => 'text', 'required' => true],
                ['db_key' => 'first_name',  'label' => "Child's First Name", 'type' => 'text', 'required' => true],
                ['db_key' => 'middle_name', 'label' => "Child's Middle Name", 'type' => 'text'],
                ['db_key' => 'birth_date',  'label' => 'Date of Birth', 'type' => 'date', 'required' => true],
                ['db_key' => 'birth_place', 'label' => 'Place of Birth', 'type' => 'text'],
                ['db_key' => 'sex',         'label' => 'Sex', 'type' => 'select',
                 'options' => ['Male', 'Female', 'Other']],
                ['db_key' => 'father_name', 'label' => "Father's Name", 'type' => 'text'],
                ['db_key' => 'mother_name', 'label' => "Mother's Maiden Name", 'type' => 'text'],
            ],
        ],
    ];

    /** Full config array for one document type, or null if not a configured person-doc type */
    public static function get(string $type): ?array
    {
        return self::CONFIG[$type] ?? null;
    }

    /** True if this type has a dynamic-field configuration (i.e. it belongs on the clients form) */
    public static function has(string $type): bool
    {
        return array_key_exists($type, self::CONFIG);
    }

    /** Just the db_key list, in display order — used for INSERT/UPDATE binding */
    public static function fieldKeys(string $type): array
    {
        $cfg = self::get($type);
        return $cfg ? array_column($cfg['fields'], 'db_key') : [];
    }

    /**
     * A short, curated list of columns (beyond Name, which the list table
     * always shows separately) to display in clients/list.php when the
     * person filters by this document type. Deliberately smaller than the
     * full form — 3 columns max keeps the table readable.
     */
    private const LIST_COLUMNS = [
        'national_id'       => ['birth_date', 'sex', 'id_number'],
        'drivers_license'   => ['id_number', 'sex', 'id_expiration_date'],
        'passport'          => ['id_number', 'nationality', 'id_expiration_date'],
        'birth_certificate' => ['birth_date', 'birth_place', 'father_name'],
        'student_id'        => ['id_number', 'school', 'course'],
    ];

    /** Returns [['db_key'=>..,'label'=>..,'type'=>..], ...] for the list-table header/cells */
    public static function listColumns(string $type): array
    {
        if (!isset(self::LIST_COLUMNS[$type])) {
            return [];
        }
        $config = self::get($type);
        $labelsByKey = array_column($config['fields'], 'label', 'db_key');
        $typesByKey  = array_column($config['fields'], 'type', 'db_key');

        $out = [];
        foreach (self::LIST_COLUMNS[$type] as $key) {
            $out[] = [
                'db_key' => $key,
                'label'  => $labelsByKey[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'type'   => $typesByKey[$key] ?? 'text',
            ];
        }
        return $out;
    }
}