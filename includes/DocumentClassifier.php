<?php
/**
 * DocumentClassifier
 * Classifies raw OCR text into one of the supported document types using
 * weighted keyword matching. No external AI service required — this is a
 * deterministic, explainable heuristic classifier that's easy to tune.
 *
 * Each document type has a list of [keyword => weight]. We score every
 * type by summing the weights of keywords found in the text, then convert
 * the winning score into a 0-99% confidence figure. If nothing scores
 * above MIN_SCORE, the document falls back to 'other'.
 */
class DocumentClassifier
{
    private const MIN_SCORE = 15;
    private const SCORE_CEILING = 65; // score that maps to ~99% confidence

    private const TYPES = [
        'national_id' => [
            'label' => 'Philippine National ID',
            'keywords' => [
                'philsys' => 35, 'philippine identification card' => 35,
                'pambansang pagkakakilanlan' => 30, 'republika ng pilipinas' => 15,
                'psn' => 10, 'pcn' => 10,
            ],
        ],
        'drivers_license' => [
            'label' => "Driver's License",
            'keywords' => [
                'land transportation office' => 30, 'lto' => 20,
                'driver\'s license' => 30, 'license no' => 20,
                'restrictions' => 12, 'expiration date' => 8,
            ],
        ],
        'passport' => [
            'label' => 'Passport',
            'keywords' => [
                'passport' => 30, 'passport no' => 25, 'nationality' => 10,
                'department of foreign affairs' => 25, 'place of birth' => 8,
            ],
        ],
        'birth_certificate' => [
            'label' => 'Birth Certificate',
            'keywords' => [
                'certificate of live birth' => 35, 'birth certificate' => 30,
                'registry no' => 15, 'psa' => 15, 'nso' => 15,
                'father' => 8, 'mother' => 8,
            ],
        ],
        'student_id' => [
            'label' => 'Student ID',
            'keywords' => [
                'student id' => 30, 'school year' => 20, 'year level' => 20,
                'course' => 12, 'enrolled' => 10, 'university' => 10, 'college' => 8,
            ],
        ],
        'business_card' => [
            'label' => 'Business Card',
            'keywords' => [
                'www.' => 12, '@' => 12, 'inc.' => 10, 'corp' => 10,
                'ceo' => 15, 'manager' => 12, 'director' => 12, 'founder' => 12,
                'sales' => 8, 'marketing' => 8,
            ],
        ],
        'receipt' => [
            'label' => 'Receipt',
            'keywords' => [
                'official receipt' => 35, 'receipt' => 20, 'vat' => 15,
                'change' => 10, 'cash tendered' => 15, 'qty' => 8, 'total due' => 12,
            ],
        ],
        'invoice' => [
            'label' => 'Invoice',
            'keywords' => [
                'invoice' => 30, 'invoice no' => 25, 'bill to' => 20,
                'due date' => 18, 'amount due' => 15, 'terms' => 8,
            ],
        ],
    ];

    /**
     * @return array{type:string,label:string,confidence:float,scores:array}
     */
    public static function classify(string $rawText): array
    {
        $normalized = strtolower($rawText);
        $scores = [];

        foreach (self::TYPES as $key => $def) {
            $score = 0;
            foreach ($def['keywords'] as $keyword => $weight) {
                if (str_contains($normalized, $keyword)) {
                    $score += $weight;
                }
            }
            $scores[$key] = $score;
        }

        arsort($scores);
        $bestKey = array_key_first($scores);
        $bestScore = $scores[$bestKey];

        if ($bestScore < self::MIN_SCORE) {
            return [
                'type' => 'other',
                'label' => 'Other Document',
                'confidence' => min(40.0, round(($bestScore / self::SCORE_CEILING) * 100, 1)),
                'scores' => $scores,
            ];
        }

        $confidence = min(99.0, round(($bestScore / self::SCORE_CEILING) * 100, 1));

        return [
            'type' => $bestKey,
            'label' => self::TYPES[$bestKey]['label'],
            'confidence' => $confidence,
            'scores' => $scores,
        ];
    }

    /** All supported types, for populating the "Change Document Type" dropdown */
    public static function allTypes(): array
    {
        $out = ['other' => 'Other Document'];
        foreach (self::TYPES as $key => $def) {
            $out[$key] = $def['label'];
        }
        return $out;
    }

    /** Which types are person-identity documents that live in the `clients` table */
    public static function isPersonType(string $type): bool
    {
        return in_array($type, ['national_id', 'drivers_license', 'passport', 'birth_certificate', 'student_id'], true);
    }

    /**
     * Which client-form fields are typically found on each person-identity
     * document type. Used by clients/add.php to show only the relevant
     * fields by default (with a "Show All Fields" toggle for the rest).
     * An empty array means "show every field" (e.g. manual add, no scan).
     */
    public static function primaryFieldsForClientForm(string $type): array
    {
        return match ($type) {
            'national_id' => [
                'first_name', 'middle_name', 'last_name', 'suffix',
                'region', 'province', 'city', 'barangay', 'residence', 'street', 'address',
                'birth_date', 'sex', 'civil_status', 'nationality', 'id_type', 'id_number',
            ],
            'drivers_license' => [
                'first_name', 'middle_name', 'last_name', 'suffix',
                'address', 'birth_date', 'sex', 'id_type', 'id_number',
            ],
            'passport' => [
                'first_name', 'middle_name', 'last_name', 'suffix',
                'birth_date', 'birth_place', 'sex', 'nationality', 'id_type', 'id_number',
            ],
            'birth_certificate' => [
                'first_name', 'middle_name', 'last_name', 'suffix',
                'birth_date', 'birth_place', 'sex', 'id_type', 'id_number',
            ],
            'student_id' => [
                'first_name', 'middle_name', 'last_name', 'suffix', 'id_type', 'id_number',
            ],
            default => [],
        };
    }
}