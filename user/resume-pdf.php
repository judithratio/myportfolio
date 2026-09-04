<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RESUME / CV PDF GENERATOR
|--------------------------------------------------------------------------
| Harvard-inspired resume/CV layout
|
| Features:
| - A4 portrait
| - Reverse chronological ordering
| - Dates on the RIGHT
| - Location/address on the RIGHT
| - Responsibilities/details as bullet points
| - Compact spacing
| - Individual item selection through selected[]
| - Custom section order through section_order
| - Resume / CV document type
| - Same renderer for preview and download
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_role('user');

$uid = current_user_id();
$pdo = db();

date_default_timezone_set('Asia/Manila');


/* =========================================================
   BASIC HELPERS
========================================================= */

function pdf_escape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function pdf_clean_text($value): string
{
    if ($value === null) {
        return '';
    }

    return trim(
        strip_tags((string)$value)
    );
}

function pdf_first_value(
    array $row,
    array $keys
): string {

    foreach ($keys as $key) {

        if (
            array_key_exists($key, $row) &&
            $row[$key] !== null &&
            trim((string)$row[$key]) !== ''
        ) {
            return pdf_clean_text($row[$key]);
        }
    }

    return '';
}


/* =========================================================
   URL HELPER
========================================================= */

function pdf_url_href(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (
        !preg_match(
            '#^https?://#i',
            $url
        )
    ) {
        $url = 'https://' . $url;
    }

    return $url;
}

function pdf_link(
    string $url,
    string $label = ''
): string {

    $url = pdf_url_href($url);

    if ($url === '') {
        return '';
    }

    $label = $label !== ''
        ? $label
        : $url;

    return '<a href="' .
        pdf_escape($url) .
        '">' .
        pdf_escape($label) .
        '</a>';
}


/* =========================================================
   DATE HELPERS
========================================================= */

function pdf_format_date($value): string
{
    if (!$value) {
        return '';
    }

    $value = trim((string)$value);

    if (
        $value === '0000-00-00' ||
        $value === '0000-00-00 00:00:00'
    ) {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('M Y', $timestamp);
}

function pdf_date_range(
    array $row,
    array $startKeys,
    array $endKeys
): string {

    $start = pdf_first_value(
        $row,
        $startKeys
    );

    $end = pdf_first_value(
        $row,
        $endKeys
    );

    $startFormatted =
        pdf_format_date($start);

    $endFormatted =
        pdf_format_date($end);

    if (
        $startFormatted !== '' &&
        $endFormatted !== ''
    ) {
        return $startFormatted .
            ' – ' .
            $endFormatted;
    }

    if ($startFormatted !== '') {
        return $startFormatted .
            ' – Present';
    }

    if ($endFormatted !== '') {
        return $endFormatted;
    }

    return '';
}

function pdf_date_timestamp(
    array $row,
    array $keys
): int {

    foreach ($keys as $key) {

        if (
            array_key_exists($key, $row) &&
            trim((string)$row[$key]) !== ''
        ) {

            $value =
                trim((string)$row[$key]);

            if (
                $value === '0000-00-00' ||
                $value === '0000-00-00 00:00:00'
            ) {
                continue;
            }

            $timestamp =
                strtotime($value);

            if ($timestamp !== false) {
                return $timestamp;
            }
        }
    }

    return 0;
}

function pdf_sort_desc(
    array &$rows,
    array $dateKeys
): void {

    usort(
        $rows,
        static function (
            array $a,
            array $b
        ) use ($dateKeys): int {

            $dateA =
                pdf_date_timestamp(
                    $a,
                    $dateKeys
                );

            $dateB =
                pdf_date_timestamp(
                    $b,
                    $dateKeys
                );

            if ($dateA !== $dateB) {
                return $dateB <=> $dateA;
            }

            $createdA =
                isset($a['created_at'])
                ? (strtotime(
                    (string)$a['created_at']
                ) ?: 0)
                : 0;

            $createdB =
                isset($b['created_at'])
                ? (strtotime(
                    (string)$b['created_at']
                ) ?: 0)
                : 0;

            if ($createdA !== $createdB) {
                return $createdB <=> $createdA;
            }

            return ((int)($b['id'] ?? 0))
                <=>
                ((int)($a['id'] ?? 0));
        }
    );
}


/* =========================================================
   SELECTION HELPERS
========================================================= */

function pdf_is_selected(
    array $selectedItems,
    string $category,
    $id
): bool {

    if (
        !isset(
            $selectedItems[$category]
        )
    ) {
        return false;
    }

    $id = (string)$id;

    foreach (
        (array)$selectedItems[$category]
        as $selectedId
    ) {

        if (
            (string)$selectedId === $id
        ) {
            return true;
        }
    }

    return false;
}


/*
 * IMPORTANT:
 *
 * The resume builder can send either:
 *
 * profile_github
 * profile_github_url
 *
 * but the renderer works with:
 *
 * github
 *
 * Same principle applies to all profile fields.
 */
function pdf_selected_profile_key(
    array $selected,
    string $canonical
): bool {

    $aliases = [

        'full_name' => [
            'full_name',
            'name',
            'profile_full_name',
            'profile_name'
        ],

        'professional_title' => [
            'professional_title',
            'title',
            'job_title',
            'position',
            'headline',
            'profile_professional_title',
            'profile_title'
        ],

        'email' => [
            'email',
            'email_address',
            'profile_email',
            'profile_email_address'
        ],

        'phone' => [
            'phone',
            'phone_number',
            'mobile',
            'contact_number',
            'profile_phone',
            'profile_phone_number'
        ],

        'address' => [
            'address',
            'current_address',
            'location',
            'city',
            'profile_address',
            'profile_current_address'
        ],

        'website' => [
            'website',
            'website_url',
            'portfolio_url',
            'personal_website',
            'profile_website',
            'profile_website_url',
            'profile_portfolio_url'
        ],

        'github' => [
            'github',
            'github_url',
            'github_link',
            'profile_github',
            'profile_github_url',
            'profile_github_link'
        ],

        'linkedin' => [
            'linkedin',
            'linkedin_url',
            'linkedin_link',
            'profile_linkedin',
            'profile_linkedin_url',
            'profile_linkedin_link'
        ],

        'facebook' => [
            'facebook',
            'facebook_url',
            'facebook_link',
            'profile_facebook',
            'profile_facebook_url',
            'profile_facebook_link'
        ],

        'profile_picture' => [
            'profile_picture',
            'profile_image',
            'profile_profile_picture',
            'profile_profile_image'
        ]
    ];

    foreach (
        $aliases[$canonical]
            ?? [$canonical]
        as $key
    ) {

        if (
            in_array(
                $key,
                $selected,
                true
            )
        ) {
            return true;
        }
    }

    return false;
}

function pdf_section_enabled(
    array $show,
    string $section
): bool {

    return !empty($show[$section]);
}


/* =========================================================
   BULLET HELPER
========================================================= */

function pdf_bullets(
    string $text
): string {

    /*
     * Preserve line breaks before stripping HTML.
     * This prevents multiple responsibilities/contributions
     * from being merged into one paragraph.
     */
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/li\s*>/i', "\n", $text);
    $text = preg_replace('/<li\b[^>]*>/i', '', $text);
    $text = preg_replace('/<\/(?:p|div|h[1-6])\s*>/i', "\n", $text);
    $text = preg_replace('/<(?:p|div|h[1-6])\b[^>]*>/i', '', $text);

    $text = trim(
        html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )
    );

    if ($text === '') {
        return '';
    }

    /*
     * Split responsibilities/contributions into separate lines.
     */
    $lines = preg_split('/\R+/', $text);

    $items = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        /*
         * Remove existing bullets or numbering so the PDF
         * can apply its own bullet formatting.
         */
        $line = preg_replace(
            '/^(?:[\x{2022}\x{2023}\x{25E6}\x{2043}\x{00B7}\-\*]+|\d+[.)])\s*/u',
            '',
            $line
        );

        $line = trim($line);

        if ($line !== '') {
            $items[] = $line;
        }
    }

    if (!$items) {
        return '';
    }

    $html = '<ul class="description-list">';

    foreach ($items as $item) {
        $html .= '<li>' . pdf_escape($item) . '</li>';
    }

    $html .= '</ul>';

    return $html;
}


/* =========================================================
   ENTRY HELPERS
========================================================= */

function pdf_right_meta(
    string $date = '',
    string $location = ''
): string {

    if (
        $date === '' &&
        $location === ''
    ) {
        return '';
    }

    $html =
        '<div class="entry-meta">';

    if ($date !== '') {

        $html .=
            '<div class="meta-date">' .
            pdf_escape($date) .
            '</div>';
    }

    if ($location !== '') {

        $html .=
            '<div class="meta-location">' .
            pdf_escape($location) .
            '</div>';
    }

    $html .=
        '</div>';

    return $html;
}

function pdf_entry_open(
    string $mainHtml,
    string $date = '',
    string $location = '',
    string $responsibilitiesHtml = ''
): string {

    return '
        <div class="entry">

            <table class="entry-table">
                <tr>

                    <td class="entry-main">
                        ' . $mainHtml . '
                    </td>

                    <td class="entry-meta-cell">
                        ' .
        pdf_right_meta(
            $date,
            $location
        ) .
        '
                    </td>

                </tr>
            </table>

            ' .
        ($responsibilitiesHtml !== ''
            ? '
                <div class="entry-responsibilities">
                    ' . $responsibilitiesHtml . '
                </div>
            '
            : '') .
        '
    ';
}

function pdf_entry_close(): string
{
    return '</div>';
}


/* =========================================================
   LOAD USER / PROFILE
========================================================= */

ensure_profile($uid);

$profile = get_profile($uid);
$user = get_user($uid);

if (!is_array($profile)) {
    $profile = [];
}

if (!is_array($user)) {
    $user = [];
}


/* =========================================================
   LOAD RESUME SETTINGS
========================================================= */

$stmt = $pdo->prepare(
    "
    SELECT *
    FROM resume_settings
    WHERE user_id = ?
    LIMIT 1
    "
);

$stmt->execute([
    $uid
]);

$settings =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   DEFAULT SECTION ORDER
========================================================= */

$defaultSectionOrder = [
    'summary',
    'experience',
    'education',
    'projects',
    'skills',
    'certifications',
    'resume_references'
];

if (!$settings) {

    $settings = [

        'section_order' =>
        json_encode(
            $defaultSectionOrder
        ),

        'show_summary' => 1,
        'show_experience' => 1,
        'show_education' => 1,
        'show_projects' => 1,
        'show_skills' => 1,
        'show_certifications' => 1,
        'show_references' => 0,
        'references_on_request' => 1,
        'document_type' => 'resume',
        'selected_items' => null
    ];
}


/* =========================================================
   SECTION ORDER
========================================================= */

$sectionOrder =
    $defaultSectionOrder;

if (
    isset($settings['section_order']) &&
    trim(
        (string)$settings['section_order']
    ) !== ''
) {

    $decodedOrder =
        json_decode(
            (string)$settings['section_order'],
            true
        );

    if (is_array($decodedOrder)) {

        $decodedOrder =
            array_values(
                array_intersect(
                    $decodedOrder,
                    $defaultSectionOrder
                )
            );

        foreach (
            $defaultSectionOrder
            as $section
        ) {

            if (
                !in_array(
                    $section,
                    $decodedOrder,
                    true
                )
            ) {
                $decodedOrder[] =
                    $section;
            }
        }

        $sectionOrder =
            $decodedOrder;
    }
}


/* =========================================================
   LIVE SECTION ORDER
========================================================= */

if (
    isset($_GET['section_order'])
) {

    $requestedOrder =
        json_decode(
            (string)$_GET['section_order'],
            true
        );

    if (is_array($requestedOrder)) {

        $requestedOrder =
            array_values(
                array_intersect(
                    $requestedOrder,
                    $defaultSectionOrder
                )
            );

        foreach (
            $defaultSectionOrder
            as $section
        ) {

            if (
                !in_array(
                    $section,
                    $requestedOrder,
                    true
                )
            ) {
                $requestedOrder[] =
                    $section;
            }
        }

        $sectionOrder =
            $requestedOrder;
    }
}


/* =========================================================
   SECTION VISIBILITY
========================================================= */

$show = [

    'summary' =>
    !empty($settings['show_summary']),

    'experience' =>
    !empty($settings['show_experience']),

    'education' =>
    !empty($settings['show_education']),

    'projects' =>
    !empty($settings['show_projects']),

    'skills' =>
    !empty($settings['show_skills']),

    'certifications' =>
    !empty($settings['show_certifications']),

    'resume_references' =>
    array_key_exists(
        'show_resume_references',
        $settings
    )
        ? !empty($settings['show_resume_references'])
        : !empty($settings['show_references'])
];


/* =========================================================
   LIVE VISIBILITY
========================================================= */

$liveVisibilityKeys = [

    'summary' =>
    'show_summary',

    'experience' =>
    'show_experience',

    'education' =>
    'show_education',

    'projects' =>
    'show_projects',

    'skills' =>
    'show_skills',

    'certifications' =>
    'show_certifications',

    'resume_references' =>
    'show_resume_references'
];

foreach (
    $liveVisibilityKeys
    as $section => $queryKey
) {

    if (
        array_key_exists(
            $queryKey,
            $_GET
        )
    ) {

        $show[$section] =
            (
                (string)$_GET[$queryKey]
                === '1'
            );
    }

    if (
        $section ===
        'resume_references' &&
        array_key_exists(
            'show_references',
            $_GET
        )
    ) {

        $show[$section] =
            (
                (string)$_GET['show_references'] === '1'
            );
    }
}


/* =========================================================
   REFERENCES ON REQUEST
========================================================= */

$referencesOnRequest =
    array_key_exists(
        'references_on_request',
        $_GET
    )
    ? (
        (string)$_GET['references_on_request'] === '1'
    )
    : !empty($settings['references_on_request']);


/* =========================================================
   FETCH USER ROWS
========================================================= */

function pdf_fetch_user_rows(
    PDO $pdo,
    string $table,
    int $uid
): array {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT *
            FROM `{$table}`
            WHERE user_id = ?
            ORDER BY id DESC
            "
        );

        $stmt->execute([
            $uid
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    } catch (Throwable $e) {

        try {

            $stmt = $pdo->prepare(
                "
                SELECT *
                FROM `{$table}`
                WHERE user_id = ?
                ORDER BY created_at DESC
                "
            );

            $stmt->execute([
                $uid
            ]);

            return $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
        } catch (Throwable $e2) {

            return [];
        }
    }
}

$experienceRows =
    pdf_fetch_user_rows(
        $pdo,
        'experience',
        $uid
    );

$educationRows =
    pdf_fetch_user_rows(
        $pdo,
        'education',
        $uid
    );

$projectRows =
    pdf_fetch_user_rows(
        $pdo,
        'projects',
        $uid
    );

$skillRows =
    pdf_fetch_user_rows(
        $pdo,
        'skills',
        $uid
    );

$certificationRows =
    pdf_fetch_user_rows(
        $pdo,
        'certifications',
        $uid
    );

$referenceRows =
    pdf_fetch_user_rows(
        $pdo,
        'resume_references',
        $uid
    );


/* =========================================================
   SELECTED ITEMS
========================================================= */

$selectedItems = [];

$selectionWasProvidedByBuilder =
    false;

$summaryWasSelectedByBuilder =
    false;

if (
    isset($_GET['selected'])
) {

    $selectionWasProvidedByBuilder =
        true;

    $selectedItems = [

        'profile' => [],
        'experience' => [],
        'education' => [],
        'projects' => [],
        'skills' => [],
        'certifications' => [],
        'resume_references' => []
    ];

    $requestedItems =
        $_GET['selected'];

    if (
        !is_array($requestedItems)
    ) {

        $requestedItems = [
            $requestedItems
        ];
    }

    foreach (
        $requestedItems
        as $item
    ) {

        $item =
            trim((string)$item);

        if ($item === '') {
            continue;
        }


        /* ---------------------------------------------
           PROFESSIONAL SUMMARY
        --------------------------------------------- */

        if (
            in_array(
                $item,
                [
                    'summary',
                    'professional_summary',
                    'profile_professional_summary'
                ],
                true
            )
        ) {

            $summaryWasSelectedByBuilder =
                true;

            continue;
        }


        /* ---------------------------------------------
           PROFILE
        --------------------------------------------- */

        if (
            str_starts_with(
                $item,
                'profile_'
            )
        ) {

            $key =
                substr(
                    $item,
                    8
                );

            $profileKeyMap = [

                'name' =>
                'full_name',

                'full_name' =>
                'full_name',

                'professional_title' =>
                'professional_title',

                'title' =>
                'professional_title',

                'job_title' =>
                'professional_title',

                'position' =>
                'professional_title',

                'headline' =>
                'professional_title',

                'email' =>
                'email',

                'email_address' =>
                'email',

                'phone' =>
                'phone',

                'phone_number' =>
                'phone',

                'mobile' =>
                'phone',

                'contact_number' =>
                'phone',

                'address' =>
                'address',

                'current_address' =>
                'address',

                'location' =>
                'address',

                'city' =>
                'address',

                'website' =>
                'website',

                'website_url' =>
                'website',

                'portfolio_url' =>
                'website',

                'personal_website' =>
                'website',

                'github' =>
                'github',

                'github_url' =>
                'github',

                'github_link' =>
                'github',

                'linkedin' =>
                'linkedin',

                'linkedin_url' =>
                'linkedin',

                'linkedin_link' =>
                'linkedin',

                'facebook' =>
                'facebook',

                'facebook_url' =>
                'facebook',

                'facebook_link' =>
                'facebook',

                'profile_picture' =>
                'profile_picture',

                'profile_image' =>
                'profile_picture'
            ];

            $key =
                $profileKeyMap[$key]
                ?? $key;

            if ($key !== '') {

                $selectedItems['profile'][] = $key;
            }

            continue;
        }


        /* ---------------------------------------------
           DATABASE RECORDS
        --------------------------------------------- */

        $prefixMap = [

            'experience_' =>
            'experience',

            'education_' =>
            'education',

            'project_' =>
            'projects',

            'skill_' =>
            'skills',

            'certification_' =>
            'certifications',

            'reference_' =>
            'resume_references'
        ];

        foreach (
            $prefixMap
            as $prefix => $category
        ) {

            if (
                str_starts_with(
                    $item,
                    $prefix
                )
            ) {

                $id =
                    substr(
                        $item,
                        strlen($prefix)
                    );

                if (
                    $id !== '' &&
                    ctype_digit($id)
                ) {

                    $selectedItems[$category][] = $id;
                }

                break;
            }
        }
    }
}


/* =========================================================
   SAVED SELECTIONS
========================================================= */

if (
    !$selectionWasProvidedByBuilder &&
    isset(
        $settings['selected_items']
    ) &&
    $settings['selected_items'] !== null &&
    trim(
        (string)$settings['selected_items']
    ) !== ''
) {

    $decodedSelection =
        json_decode(
            (string)$settings['selected_items'],
            true
        );

    if (
        is_array($decodedSelection)
    ) {

        $selectedItems =
            $decodedSelection;
    }
}


/* =========================================================
   SESSION FALLBACK
========================================================= */

if (
    !$selectionWasProvidedByBuilder &&
    empty($selectedItems) &&
    isset(
        $_SESSION['resume_selected_items']
    ) &&
    is_array(
        $_SESSION['resume_selected_items']
    )
) {

    $selectedItems =
        $_SESSION['resume_selected_items'];
}


/* =========================================================
   DEFAULT SELECTION
========================================================= */

if (
    !$selectionWasProvidedByBuilder &&
    empty($selectedItems)
) {

    $selectedItems = [

        'profile' => [],
        'experience' => [],
        'education' => [],
        'projects' => [],
        'skills' => [],
        'certifications' => [],
        'resume_references' => []
    ];


    /*
     * Select useful profile fields only.
     */
    $defaultProfileFields = [

        'full_name',
        'professional_title',
        'email',
        'phone',
        'address',
        'website_url',
        'github_url',
        'linkedin_url',
        'facebook_url',
        'profile_picture',
        'profile_image'
    ];

    foreach (
        $defaultProfileFields
        as $field
    ) {

        if (
            isset($profile[$field]) &&
            trim(
                (string)$profile[$field]
            ) !== ''
        ) {

            $canonical = $field;

            if (
                in_array(
                    $field,
                    [
                        'website_url'
                    ],
                    true
                )
            ) {
                $canonical = 'website';
            }

            if (
                in_array(
                    $field,
                    [
                        'github_url'
                    ],
                    true
                )
            ) {
                $canonical = 'github';
            }

            if (
                in_array(
                    $field,
                    [
                        'linkedin_url'
                    ],
                    true
                )
            ) {
                $canonical = 'linkedin';
            }

            if (
                in_array(
                    $field,
                    [
                        'facebook_url'
                    ],
                    true
                )
            ) {
                $canonical = 'facebook';
            }

            if (
                in_array(
                    $field,
                    [
                        'profile_image'
                    ],
                    true
                )
            ) {
                $canonical =
                    'profile_picture';
            }

            $selectedItems['profile'][] = $canonical;
        }
    }


    foreach (
        $experienceRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['experience'][] =
                (string)$row['id'];
        }
    }


    foreach (
        $educationRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['education'][] =
                (string)$row['id'];
        }
    }


    foreach (
        $projectRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['projects'][] =
                (string)$row['id'];
        }
    }


    foreach (
        $skillRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['skills'][] =
                (string)$row['id'];
        }
    }


    foreach (
        $certificationRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['certifications'][] =
                (string)$row['id'];
        }
    }


    foreach (
        $referenceRows
        as $row
    ) {

        if (
            isset($row['id'])
        ) {

            $selectedItems['resume_references'][] =
                (string)$row['id'];
        }
    }
}


/* =========================================================
   NORMALIZE PROFILE SELECTION
========================================================= */

$normalizedProfileSelection =
    [];

foreach (
    $selectedItems['profile']
        ?? []
    as $key
) {

    $key =
        trim((string)$key);

    $map = [

        'name' =>
        'full_name',

        'profile_full_name' =>
        'full_name',

        'professional_title' =>
        'professional_title',

        'profile_professional_title' =>
        'professional_title',

        'title' =>
        'professional_title',

        'job_title' =>
        'professional_title',

        'email_address' =>
        'email',

        'profile_email' =>
        'email',

        'phone_number' =>
        'phone',

        'profile_phone' =>
        'phone',

        'current_address' =>
        'address',

        'profile_address' =>
        'address',

        'website_url' =>
        'website',

        'profile_website' =>
        'website',

        'profile_website_url' =>
        'website',

        'portfolio_url' =>
        'website',

        'github_url' =>
        'github',

        'profile_github' =>
        'github',

        'profile_github_url' =>
        'github',

        'github_link' =>
        'github',

        'linkedin_url' =>
        'linkedin',

        'profile_linkedin' =>
        'linkedin',

        'profile_linkedin_url' =>
        'linkedin',

        'linkedin_link' =>
        'linkedin',

        'facebook_url' =>
        'facebook',

        'profile_facebook' =>
        'facebook',

        'profile_facebook_url' =>
        'facebook',

        'facebook_link' =>
        'facebook',

        'profile_image' =>
        'profile_picture',

        'profile_picture' =>
        'profile_picture'
    ];

    $normalizedProfileSelection[] =
        $map[$key] ?? $key;
}

$selectedItems['profile'] =
    array_values(
        array_unique(
            $normalizedProfileSelection
        )
    );


/* =========================================================
   SANITIZE DATABASE IDS
========================================================= */

function pdf_valid_ids(
    array $rows
): array {

    $ids = [];

    foreach ($rows as $row) {

        if (
            isset($row['id']) &&
            $row['id'] !== ''
        ) {

            $ids[] =
                (string)$row['id'];
        }
    }

    return $ids;
}

$validSelection = [

    'experience' =>
    pdf_valid_ids(
        $experienceRows
    ),

    'education' =>
    pdf_valid_ids(
        $educationRows
    ),

    'projects' =>
    pdf_valid_ids(
        $projectRows
    ),

    'skills' =>
    pdf_valid_ids(
        $skillRows
    ),

    'certifications' =>
    pdf_valid_ids(
        $certificationRows
    ),

    'resume_references' =>
    pdf_valid_ids(
        $referenceRows
    )
];

foreach (
    $validSelection
    as $category => $validIds
) {

    $requested =
        $selectedItems[$category] ?? [];

    if (
        !is_array($requested)
    ) {
        $requested = [];
    }

    $requested =
        array_map(
            'strval',
            $requested
        );

    $selectedItems[$category] =
        array_values(
            array_intersect(
                $requested,
                $validIds
            )
        );
}


/* =========================================================
   FILTER SELECTED RECORDS
========================================================= */

$selectedExperience = [];

foreach (
    $experienceRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'experience',
            $row['id']
        )
    ) {

        $selectedExperience[] =
            $row;
    }
}

$selectedEducation = [];

foreach (
    $educationRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'education',
            $row['id']
        )
    ) {

        $selectedEducation[] =
            $row;
    }
}

$selectedProjects = [];

foreach (
    $projectRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'projects',
            $row['id']
        )
    ) {

        $selectedProjects[] =
            $row;
    }
}

$selectedSkills = [];

foreach (
    $skillRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'skills',
            $row['id']
        )
    ) {

        $selectedSkills[] =
            $row;
    }
}

$selectedCertifications = [];

foreach (
    $certificationRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'certifications',
            $row['id']
        )
    ) {

        $selectedCertifications[] =
            $row;
    }
}

$selectedReferences = [];

foreach (
    $referenceRows
    as $row
) {

    if (
        isset($row['id']) &&
        pdf_is_selected(
            $selectedItems,
            'resume_references',
            $row['id']
        )
    ) {

        $selectedReferences[] =
            $row;
    }
}


/* =========================================================
   SORT
========================================================= */

pdf_sort_desc(
    $selectedExperience,
    [
        'start_date',
        'date_started',
        'from_date',
        'start',
        'started_at',
        'end_date',
        'date_ended',
        'to_date'
    ]
);

pdf_sort_desc(
    $selectedEducation,
    [
        'end_date',
        'graduation_date',
        'date_ended',
        'to_date',
        'start_date',
        'date_started',
        'from_date'
    ]
);

pdf_sort_desc(
    $selectedProjects,
    [
        'start_date',
        'date_started',
        'project_date',
        'date',
        'end_date',
        'date_ended',
        'created_at'
    ]
);

pdf_sort_desc(
    $selectedCertifications,
    [
        'date',
        'issue_date',
        'date_issued',
        'issued_date',
        'created_at'
    ]
);


/* =========================================================
   PROFILE VALUES
========================================================= */

$fullName =
    pdf_first_value(
        $profile,
        [
            'full_name',
            'name',
            'display_name'
        ]
    );

if ($fullName === '') {

    $fullName =
        trim(
            implode(
                ' ',
                array_filter(
                    [
                        $profile['first_name'] ?? '',
                        $profile['middle_name'] ?? '',
                        $profile['last_name'] ?? ''
                    ]
                )
            )
        );
}

if ($fullName === '') {

    $fullName =
        pdf_first_value(
            $user,
            [
                'name',
                'full_name',
                'display_name'
            ]
        );
}

if ($fullName === '') {
    $fullName = 'Your Name';
}


$professionalTitle =
    pdf_first_value(
        $profile,
        [
            'professional_title',
            'job_title',
            'title',
            'position',
            'headline'
        ]
    );


/* =========================================================
   PROFESSIONAL SUMMARY
========================================================= */

$summaryText =
    pdf_first_value(
        $profile,
        [
            'professional_summary',
            'summary',
            'bio',
            'about',
            'about_me',
            'description'
        ]
    );

$summarySelected =
    $summaryWasSelectedByBuilder;

if (
    !$selectionWasProvidedByBuilder &&
    $summaryText !== ''
) {

    $summarySelected = true;
}


/* =========================================================
   CONTACT INFORMATION
========================================================= */

$email =
    pdf_first_value(
        $profile,
        [
            'email',
            'email_address'
        ]
    );

if ($email === '') {

    $email =
        pdf_first_value(
            $user,
            [
                'email',
                'email_address'
            ]
        );
}


$phone =
    pdf_first_value(
        $profile,
        [
            'phone',
            'phone_number',
            'mobile',
            'contact_number'
        ]
    );


$location =
    pdf_first_value(
        $profile,
        [
            'address',
            'location',
            'city',
            'current_address'
        ]
    );


$website =
    pdf_first_value(
        $profile,
        [
            'website_url',
            'website',
            'portfolio_url',
            'personal_website'
        ]
    );


$github =
    pdf_first_value(
        $profile,
        [
            'github_url',
            'github',
            'github_link'
        ]
    );


$linkedin =
    pdf_first_value(
        $profile,
        [
            'linkedin_url',
            'linkedin',
            'linkedin_link'
        ]
    );


$facebook =
    pdf_first_value(
        $profile,
        [
            'facebook_url',
            'facebook',
            'facebook_link'
        ]
    );


/* =========================================================
   PROFILE IMAGE
========================================================= */

$profileImage =
    pdf_first_value(
        $profile,
        [
            'profile_image',
            'profile_picture'
        ]
    );


/* =========================================================
   DOCUMENT TYPE
========================================================= */

$documentType =
    strtolower(
        trim(
            (string)(
                $_GET['type']
                ??
                $settings['document_type']
                ??
                'resume'
            )
        )
    );

if (
    !in_array(
        $documentType,
        [
            'resume',
            'cv'
        ],
        true
    )
) {

    $documentType =
        'resume';
}

$documentTitle =
    $documentType === 'cv'
    ? 'CURRICULUM VITAE'
    : 'RESUME';


/* =========================================================
   SECTION HEADING
========================================================= */

function pdf_section_heading(
    string $title
): string {

    return
        '<div class="section-title">' .
        pdf_escape($title) .
        '</div>';
}


/* =========================================================
   HTML
========================================================= */

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>

@page {
    margin: 34px 42px 34px 42px;
}

* {
    box-sizing: border-box;
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    color: #111;
    background: #fff;
    font-size: 9.2px;
    line-height: 1.3;
    margin: 0;
    padding: 0;
}

.header {
    text-align: center;
    margin-bottom: 7px;
}

.name {
    font-size: 21px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .35px;
    margin-bottom: 2px;
}

.professional-title {
    font-size: 9.8px;
    margin-bottom: 4px;
}

.contact-line {
    font-size: 8px;
    line-height: 1.3;
}

.contact-line span {
    margin: 0 3px;
}

.contact-line a {
    color: #111;
    text-decoration: none;
}

.divider {
    border-top: 1px solid #111;
    margin: 7px 0 10px 0;
}

.section {
    margin-bottom: 10px;
}

.section-title {
    font-size: 10.2px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .65px;
    border-bottom: 1px solid #111;
    padding-bottom: 2px;
    margin-bottom: 5px;
}

.summary {
    font-size: 8.9px;
    line-height: 1.35;
    text-align: justify;
}

/* =========================================================
   RESUME ENTRY / PROJECT / EXPERIENCE / EDUCATION / CERTIFICATION
   ========================================================= */

.entry {
    width: 100%;
    margin-bottom: 7px;
    page-break-inside: auto;
}

.entry-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin: 0;
    padding: 0;
}

.entry-table td {
    vertical-align: top;
}

.entry-main {
    width: 72%;
    vertical-align: top;
    padding: 0 10px 0 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.entry-meta-cell {
    width: 28%;
    vertical-align: top;
    text-align: right;
    padding: 0 0 0 8px;
}

.entry-title {
    font-size: 9.7px;
    font-weight: bold;
    line-height: 1.25;
    margin: 0 0 1px 0;
}

.entry-subtitle {
    font-size: 9px;
    font-style: normal;
    line-height: 1.3;
    margin: 0;
}

.entry-meta {
    text-align: right;
    font-size: 8px;
    line-height: 1.25;
    color: #333;
}

.meta-date {
    white-space: nowrap;
}

.meta-location {
    margin-top: 1px;
}

.entry-divider {
    width: 100%;
    border-bottom: 1px solid #111;
    margin: 3px 0 3px 0;
}

.entry-responsibilities {
    width: 100%;
    display: block;
    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.description-list {
    width: 100%;
    margin: 0;
    padding-left: 15px;
    padding-right: 0;

    font-size: 8.65px;
    line-height: 1.35;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.description-list li {
    width: auto;
    margin: 0 0 2px 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* =========================================================
   EXPERIENCE / COMPANY NAME
   ========================================================= */

.experience-title,
.company-name {
    font-weight: bold;
    font-size: 9.5px;
    line-height: 1.25;
    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   EDUCATION NAME
   ========================================================= */

.education-title,
.school-name,
.degree-name {
    font-weight: bold;
    font-size: 9.5px;
    line-height: 1.25;
    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   CERTIFICATION NAME
   ========================================================= */

.certification-title,
.certificate-name {
    font-weight: bold;
    font-size: 9.5px;
    line-height: 1.25;
    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   ROLE / POSITION / SECONDARY INFORMATION
   ========================================================= */

.entry-role {
    font-weight: normal;
    font-size: 8.8px;
    line-height: 1.3;

    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.entry-subtitle {
    font-weight: normal;
    font-size: 8.8px;
    line-height: 1.3;

    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   RIGHT-SIDE DATE / LOCATION
   ========================================================= */

.entry-meta {
    text-align: right;
    font-size: 8px;
    line-height: 1.25;
    color: #333;

    margin: 0;
    padding: 0;
}

.meta-date {
    white-space: nowrap;
    margin: 0;
    padding: 0;
}

.meta-location {
    margin-top: 1px;
    margin-bottom: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   DIVIDER
   ========================================================= */

.entry-divider {
    width: 100%;
    border-bottom: 1px solid #000;

    margin-top: 3px;
    margin-bottom: 3px;

    padding: 0;
}


/* =========================================================
   FULL-WIDTH RESPONSIBILITIES
   ========================================================= */

.entry-responsibilities {
    width: 100%;
    display: block;
    clear: both;

    margin: 0;
    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   RESPONSIBILITY BULLET LIST
   ========================================================= */

.description-list {
    display: block;

    width: 100%;

    margin-top: 0;
    margin-right: 0;
    margin-bottom: 0;
    margin-left: 0;

    padding-top: 0;
    padding-right: 0;
    padding-bottom: 0;
    padding-left: 15px;

    font-size: 8.65px;
    line-height: 1.35;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* =========================================================
   INDIVIDUAL RESPONSIBILITY
   ========================================================= */

.description-list li {
    display: list-item;

    width: auto;

    margin-top: 0;
    margin-right: 0;
    margin-bottom: 2px;
    margin-left: 0;

    padding: 0;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;

    page-break-inside: auto;
}


/* =========================================================
   GENERAL ENTRY TEXT
   ========================================================= */

.entry p {
    margin-top: 0;
    margin-bottom: 2px;
}

.entry strong {
    font-weight: bold;
}

.entry b {
    font-weight: bold;
}


/* =========================================================
   PREVENT TABLE / TEXT OVERFLOW
   ========================================================= */

.entry-table,
.entry-table td,
.entry-main,
.entry-meta-cell,
.entry-responsibilities,
.description-list,
.description-list li {
    max-width: 100%;
}


/* =========================================================
   PAGE BREAK HANDLING
   ========================================================= */

.entry {
    page-break-before: auto;
}

.entry-title,
.experience-title,
.company-name,
.education-title,
.school-name,
.degree-name,
.certification-title,
.certificate-name {
    page-break-after: avoid;
}

.description-list li {
    page-break-before: auto;
}

.skills-list {
    font-size: 8.7px;
    line-height: 1.35;
}

.skill-group {
    margin-bottom: 2px;
}

.certification-id {
    font-size: 8px;
    margin-top: 1px;
}

.references-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.references-grid td {
    width: 33.333%;
    vertical-align: top;
    padding: 0 10px 8px 0;
}

.references-grid td:last-child {
    padding-right: 0;
}

.reference {
    page-break-inside: avoid;
}

.reference-name {
    font-size: 9.4px;
    font-weight: bold;
    line-height: 1.25;
    margin-bottom: 2px;
}

.reference-details {
    font-size: 8.4px;
    line-height: 1.35;
    margin-bottom: 1px;
}
.footer {
    margin-top: 8px;
    text-align: center;
    font-size: 7px;
    color: #777;
}

.profile-photo {
    width: 82px;
    height: 82px;
    object-fit: cover;
    border-radius: 50%;
    margin-bottom: 4px;
}

</style>

</head>

<body>';


/* =========================================================
   HEADER
========================================================= */

$html .= '<div class="header">';


if (
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'full_name'
    )
) {

    $html .=
        '<div class="name">' .
        pdf_escape($fullName) .
        '</div>';
}


if (
    $professionalTitle !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'professional_title'
    )
) {

    $html .=
        '<div class="professional-title">' .
        pdf_escape(
            $professionalTitle
        ) .
        '</div>';
}


/* =========================================================
   CONTACT LINE
========================================================= */

$contactParts = [];


if (
    $email !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'email'
    )
) {

    $contactParts[] =
        pdf_escape($email);
}


if (
    $phone !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'phone'
    )
) {

    $contactParts[] =
        pdf_escape($phone);
}


if (
    $location !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'address'
    )
) {

    $contactParts[] =
        pdf_escape($location);
}


if (
    $website !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'website'
    )
) {

    $contactParts[] =
        pdf_link($website);
}


if (
    $github !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'github'
    )
) {

    $contactParts[] =
        pdf_link($github);
}


if (
    $linkedin !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'linkedin'
    )
) {

    $contactParts[] =
        pdf_link($linkedin);
}


if (
    $facebook !== '' &&
    pdf_selected_profile_key(
        $selectedItems['profile'] ?? [],
        'facebook'
    )
) {

    $contactParts[] =
        pdf_link($facebook);
}


if (!empty($contactParts)) {

    $html .=
        '<div class="contact-line">' .
        implode(
            ' <span>|</span> ',
            $contactParts
        ) .
        '</div>';
}


$html .= '
</div>

<div class="divider"></div>
';


/* =========================================================
   SECTIONS
========================================================= */

foreach (
    $sectionOrder
    as $section
) {

    if (
        !pdf_section_enabled(
            $show,
            $section
        )
    ) {
        continue;
    }


    /* =====================================================
       SUMMARY
    ===================================================== */

    if (
        $section === 'summary'
    ) {

        if (
            $summaryText !== '' &&
            $summarySelected
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Professional Summary'
                ) .

                '<div class="summary">' .
                nl2br(
                    pdf_escape(
                        $summaryText
                    )
                ) .
                '</div>

                </div>';
        }

        continue;
    }


    /* =====================================================
       EXPERIENCE
    ===================================================== */

    if (
        $section === 'experience'
    ) {

        if (
            !empty($selectedExperience)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Experience'
                );

            foreach (
                $selectedExperience
                as $row
            ) {

                $position =
                    pdf_first_value(
                        $row,
                        [
                            'job_title',
                            'position',
                            'role',
                            'title',
                            'job_position'
                        ]
                    );

                $company =
                    pdf_first_value(
                        $row,
                        [
                            'company',
                            'company_name',
                            'employer',
                            'organization',
                            'organization_name'
                        ]
                    );

                $locationValue =
                    pdf_first_value(
                        $row,
                        [
                            'location',
                            'city',
                            'address'
                        ]
                    );

                $dateRange =
                    pdf_date_range(
                        $row,
                        [
                            'start_date',
                            'date_started',
                            'from_date',
                            'start',
                            'started_at'
                        ],
                        [
                            'end_date',
                            'date_ended',
                            'to_date',
                            'end',
                            'ended_at'
                        ]
                    );

                $description =
                    pdf_first_value(
                        $row,
                        [
                            'description',
                            'details',
                            'responsibilities',
                            'job_description'
                        ]
                    );

                $main =
                    '<div class="entry-title">' .
                    pdf_escape(
                        $position
                            ?: 'Position'
                    ) .
                    '</div>';

                if (
                    $company !== ''
                ) {

                    $main .=
                        '<div class="entry-subtitle">' .
                        pdf_escape(
                            $company
                        ) .
                        '</div>';
                }

                $responsibilitiesHtml = '';

                if ($description !== '') {
                    $responsibilitiesHtml = pdf_bullets($description);
                }

                $html .=
                    pdf_entry_open(
                        $main,
                        $dateRange,
                        $locationValue,
                        $responsibilitiesHtml
                    );

                $html .=
                    pdf_entry_close();
            }

            $html .=
                '</div>';
        }

        continue;
    }


    /* =====================================================
       EDUCATION
    ===================================================== */

    if (
        $section === 'education'
    ) {

        if (
            !empty($selectedEducation)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Education'
                );

            foreach (
                $selectedEducation
                as $row
            ) {

                $degree =
                    pdf_first_value(
                        $row,
                        [
                            'degree',
                            'course',
                            'program',
                            'course_name',
                            'degree_name',
                            'title'
                        ]
                    );

                $school =
                    pdf_first_value(
                        $row,
                        [
                            'school',
                            'school_name',
                            'institution',
                            'institution_name',
                            'university',
                            'college'
                        ]
                    );

                $locationValue =
                    pdf_first_value(
                        $row,
                        [
                            'location',
                            'city',
                            'address'
                        ]
                    );

                $dateRange =
                    pdf_date_range(
                        $row,
                        [
                            'start_date',
                            'date_started',
                            'from_date',
                            'start'
                        ],
                        [
                            'end_date',
                            'date_ended',
                            'to_date',
                            'end',
                            'graduation_date'
                        ]
                    );

                $description =
                    pdf_first_value(
                        $row,
                        [
                            'description',
                            'details',
                            'honors',
                            'achievements'
                        ]
                    );

                $main =
                    '<div class="entry-title">' .
                    pdf_escape(
                        $degree
                            ?: 'Education'
                    ) .
                    '</div>';

                if (
                    $school !== ''
                ) {

                    $main .=
                        '<div class="entry-subtitle">' .
                        pdf_escape(
                            $school
                        ) .
                        '</div>';
                }

                $responsibilitiesHtml = '';

                if ($description !== '') {

                    $responsibilitiesHtml =
                        pdf_bullets(
                            $description
                        );
                }

                $html .=
                    pdf_entry_open(
                        $main,
                        $dateRange,
                        $locationValue,
                        $responsibilitiesHtml
                    );

                $html .=
                    pdf_entry_close();
            }

            $html .=
                '</div>';
        }

        continue;
    }


    /* =====================================================
       PROJECTS
    ===================================================== */

    if (
        $section === 'projects'
    ) {

        if (
            !empty($selectedProjects)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Projects'
                );

            foreach (
                $selectedProjects
                as $row
            ) {

                $title =
                    pdf_first_value(
                        $row,
                        [
                            'title',
                            'project_name',
                            'name',
                            'project_title'
                        ]
                    );

                $role =
                    pdf_first_value(
                        $row,
                        [
                            'role',
                            'position',
                            'project_role'
                        ]
                    );

                $technologies =
                    pdf_first_value(
                        $row,
                        [
                            'technologies',
                            'technology',
                            'tech_stack',
                            'tools'
                        ]
                    );

                $projectLocation =
                    pdf_first_value(
                        $row,
                        [
                            'location',
                            'city',
                            'address'
                        ]
                    );

                $projectDate =
                    pdf_date_range(
                        $row,
                        [
                            'start_date',
                            'date_started',
                            'from_date',
                            'start',
                            'project_date',
                            'date'
                        ],
                        [
                            'end_date',
                            'date_ended',
                            'to_date',
                            'end'
                        ]
                    );

                if (
                    $projectDate === ''
                ) {

                    $singleDate =
                        pdf_first_value(
                            $row,
                            [
                                'date',
                                'project_date',
                                'created_at'
                            ]
                        );

                    if (
                        $singleDate !== ''
                    ) {

                        $projectDate =
                            pdf_format_date(
                                $singleDate
                            );
                    }
                }

                $responsibilities =
                    pdf_first_value(
                        $row,
                        [
                            'responsibilities',
                            'contributions',
                            'contribution',
                            'responsibility'
                        ]
                    );

                $projectUrl =
                    pdf_first_value(
                        $row,
                        [
                            'website_url',
                            'url',
                            'project_url',
                            'link',
                            'project_link'
                        ]
                    );


                $main =
                    '<div class="entry-title">' .
                    pdf_escape(
                        $title
                            ?: 'Project'
                    ) .
                    '</div>';

                if (
                    $role !== ''
                ) {

                    $main .=
                        '<div class="entry-subtitle">' .
                        pdf_escape(
                            $role
                        ) .
                        '</div>';
                }

                if (
                    $technologies !== ''
                ) {

                    $main .=
                        '<div class="entry-subtitle">
                            Technologies:
                            ' .
                        pdf_escape(
                            $technologies
                        ) .
                        '</div>';
                }

                $responsibilitiesHtml = '';

                if (
                    $responsibilities !== ''
                ) {

                    $responsibilitiesHtml =
                        pdf_bullets(
                            $responsibilities
                        );
                }

                if (
                    $projectUrl !== ''
                ) {

                    $main .=
                        '<div class="certification-id">' .
                        pdf_link(
                            $projectUrl
                        ) .
                        '</div>';
                }

                $html .=
                    pdf_entry_open(
                        $main,
                        $projectDate,
                        $projectLocation,
                        $responsibilitiesHtml
                    );

                $html .=
                    pdf_entry_close();
            }

            $html .=
                '</div>';
        }

        continue;
    }


    /* =====================================================
       SKILLS
    ===================================================== */

    if (
        $section === 'skills'
    ) {

        if (
            !empty($selectedSkills)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Skills'
                ) .

                '<div class="skills-list">';

            $groupedSkills = [];

            foreach (
                $selectedSkills
                as $row
            ) {

                $skillName =
                    pdf_first_value(
                        $row,
                        [
                            'name',
                            'skill_name',
                            'title',
                            'skill'
                        ]
                    );

                if (
                    $skillName === ''
                ) {
                    continue;
                }

                $category =
                    pdf_first_value(
                        $row,
                        [
                            'category',
                            'skill_category',
                            'type'
                        ]
                    );

                if (
                    $category === ''
                ) {

                    $category =
                        'Skills';
                }

                $groupedSkills[$category][] =
                    $skillName;
            }

            foreach (
                $groupedSkills
                as $category =>
                $skillNames
            ) {

                $html .=
                    '<div class="skill-group">
                        <strong>' .
                    pdf_escape(
                        $category
                    ) .
                    ':</strong> ' .
                    pdf_escape(
                        implode(
                            ', ',
                            $skillNames
                        )
                    ) .
                    '</div>';
            }

            $html .=
                '</div>
                </div>';
        }

        continue;
    }


    /* =====================================================
       CERTIFICATIONS
    ===================================================== */

    if (
        $section === 'certifications'
    ) {

        if (
            !empty($selectedCertifications)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'Certifications'
                );

            foreach (
                $selectedCertifications
                as $row
            ) {

                $name =
                    pdf_first_value(
                        $row,
                        [
                            'name',
                            'certification_name',
                            'certificate_name',
                            'title',
                            'certification'
                        ]
                    );

                $issuer =
                    pdf_first_value(
                        $row,
                        [
                            'issuer',
                            'issuing_organization',
                            'organization',
                            'provider',
                            'issued_by'
                        ]
                    );

                $date =
                    pdf_first_value(
                        $row,
                        [
                            'date',
                            'issue_date',
                            'date_issued',
                            'issued_date'
                        ]
                    );

                $credentialId =
                    pdf_first_value(
                        $row,
                        [
                            'credential_id',
                            'certificate_id',
                            'certification_id'
                        ]
                    );

                $main =
                    '<div class="entry-title">' .
                    pdf_escape(
                        $name
                            ?: 'Certification'
                    ) .
                    '</div>';

                if (
                    $issuer !== ''
                ) {

                    $main .=
                        '<div class="entry-subtitle">' .
                        pdf_escape(
                            $issuer
                        ) .
                        '</div>';
                }

                if (
                    $credentialId !== ''
                ) {

                    $main .=
                        '<div class="certification-id">
                            Credential ID:
                            ' .
                        pdf_escape(
                            $credentialId
                        ) .
                        '</div>';
                }

                $html .=
                    pdf_entry_open(
                        $main,
                        $date !== ''
                            ? pdf_format_date(
                                $date
                            )
                            : '',
                        ''
                    );

                $html .=
                    pdf_entry_close();
            }

            $html .=
                '</div>';
        }

        continue;
    }


    /* =====================================================
   REFERENCES
===================================================== */

    if (
        $section === 'resume_references'
    ) {

        if (
            !empty($selectedReferences)
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'References'
                );

            /*
        |--------------------------------------------------------------------------
        | 3 REFERENCES PER ROW
        |--------------------------------------------------------------------------
        */

            $html .=
                '<table class="references-grid">
                <tr>';

            $referenceCount = 0;

            foreach (
                $selectedReferences
                as $row
            ) {

                $name =
                    pdf_first_value(
                        $row,
                        [
                            'name',
                            'full_name',
                            'reference_name',
                            'contact_name'
                        ]
                    );

                $position =
                    pdf_first_value(
                        $row,
                        [
                            'position',
                            'job_title',
                            'role',
                            'title'
                        ]
                    );

                $company =
                    pdf_first_value(
                        $row,
                        [
                            'company',
                            'company_name',
                            'organization',
                            'organization_name'
                        ]
                    );

                $emailValue =
                    pdf_first_value(
                        $row,
                        [
                            'email',
                            'email_address'
                        ]
                    );

                $phoneValue =
                    pdf_first_value(
                        $row,
                        [
                            'phone',
                            'phone_number',
                            'mobile',
                            'contact_number'
                        ]
                    );


                /*
            |--------------------------------------------------------------------------
            | START NEW ROW AFTER EVERY 3 REFERENCES
            |--------------------------------------------------------------------------
            */

                if (
                    $referenceCount > 0 &&
                    $referenceCount % 3 === 0
                ) {

                    $html .=
                        '</tr><tr>';
                }


                /*
            |--------------------------------------------------------------------------
            | REFERENCE COLUMN
            |--------------------------------------------------------------------------
            */

                $html .=
                    '<td>

                    <div class="reference">

                        <div class="reference-name">' .
                    pdf_escape(
                        $name ?: 'Reference'
                    ) .
                    '</div>';


                if (
                    $position !== ''
                ) {

                    $html .=
                        '<div class="reference-details">' .
                        pdf_escape(
                            $position
                        ) .
                        '</div>';
                }


                if (
                    $company !== ''
                ) {

                    $html .=
                        '<div class="reference-details">' .
                        pdf_escape(
                            $company
                        ) .
                        '</div>';
                }


                if (
                    $emailValue !== ''
                ) {

                    $html .=
                        '<div class="reference-details">' .
                        pdf_escape(
                            $emailValue
                        ) .
                        '</div>';
                }


                if (
                    $phoneValue !== ''
                ) {

                    $html .=
                        '<div class="reference-details">' .
                        pdf_escape(
                            $phoneValue
                        ) .
                        '</div>';
                }


                $html .=
                    '</div>

                </td>';


                $referenceCount++;
            }


            /*
        |--------------------------------------------------------------------------
        | CLOSE TABLE
        |--------------------------------------------------------------------------
        */

            $html .=
                '</tr>
            </table>

            </div>';
        } elseif (
            $referencesOnRequest
        ) {

            $html .=
                '<div class="section">' .

                pdf_section_heading(
                    'References'
                ) .

                '<div class="references-request">
                References available upon request.
            </div>

            </div>';
        }

        continue;
    }
}


/* =========================================================
   FOOTER
========================================================= */

$html .=
    '<div class="footer">' .
    // pdf_escape(
    //     $documentTitle
    // ) .
    '</div>

</body>
</html>';


/* =========================================================
   DOMPDF
========================================================= */

$options =
    new Options();

$options->set(
    'isHtml5ParserEnabled',
    true
);

$options->set(
    'isRemoteEnabled',
    true
);

$options->set(
    'defaultFont',
    'DejaVu Sans'
);

$options->set(
    'isPhpEnabled',
    false
);

$dompdf =
    new Dompdf(
        $options
    );

$dompdf->loadHtml(
    $html,
    'UTF-8'
);

$dompdf->setPaper(
    'A4',
    'portrait'
);

$dompdf->render();


/* =========================================================
   FILE NAME
========================================================= */

$filename =
    preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        $fullName
    ) .
    '_' .
    strtoupper(
        $documentType
    ) .
    '.pdf';


/* =========================================================
   PREVIEW / DOWNLOAD
========================================================= */

$preview =
    isset($_GET['preview']) &&
    (string)$_GET['preview'] === '1';

if ($preview) {

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: inline; filename="' .
            $filename .
            '"'
    );

    echo $dompdf->output();

    exit;
}

$dompdf->stream(
    $filename,
    [
        'Attachment' => true
    ]
);

exit;
