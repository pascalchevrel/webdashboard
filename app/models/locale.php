<?php
/*
 * Model for individual locale view
 */
namespace Webdashboard;

use Bugzilla\Bugzilla;
use Cache\Cache;

$json = isset($_GET['json']);
$locale = $_GET['locale'];

// Include all data about our locales
include __DIR__ . '/../data/locales.php';

// Check that this is a valid locale code called via GET
if (!isset($_GET['locale']) || !in_array($_GET['locale'], $locales)) {
    $content = "<h2 class='errortitle'>Wrong locale code: {$locale}</h2>\n" .
               '<p>The requested language is not available on the dashboard.</p>';
    include __DIR__ . '/../views/error.php';

    return;
} else {
    $locale = $_GET['locale'];
}

// Get lang files status from langchecker
$json_data = new Json;
$lang_files = $json_data
    ->setURI(LANG_CHECKER . "?locale={$locale}&json")
    ->fetchContent();

// Check if the locale is working on locamotion
$cache_id = 'locamotion_locales';
if (! $locamotion = Cache::getKey($cache_id)) {
    $locamotion = $json_data
        ->setURI(LANG_CHECKER . '?action=listlocales&project=locamotion&json')
        ->fetchContent();
    Cache::setKey($cache_id, $locamotion);
}

$locamotion = in_array($locale, $locamotion);

// All open bugs for a locale in the mozilla.org/l10n component
$bugzilla_query_mozillaorg = 'https://bugzilla.mozilla.org/buglist.cgi?'
                           . 'f1=cf_locale'
                           . '&o1=equals'
                           . '&query_format=advanced'
                           . '&v1=' . urlencode(Bugzilla::getBugzillaLocaleField($locale))
                           . '&o2=equals'
                           . '&f2=component'
                           . '&v2=L10N'
                           . '&bug_status=UNCONFIRMED'
                           . '&bug_status=NEW'
                           . '&bug_status=ASSIGNED'
                           . '&bug_status=REOPENED'
                           . '&classification=Other'
                           . '&product=www.mozilla.org';

// All open bugs for a locale in the Mozilla Localization/locale component, with "webdashboard" in the whiteboard
$bugzilla_query_l10ncomponent = 'https://bugzilla.mozilla.org/buglist.cgi?'
                              . '&query_format=advanced'
                              . '&status_whiteboard_type=allwordssubstr'
                              . '&status_whiteboard=webdashboard'
                              . '&bug_status=UNCONFIRMED'
                              . '&bug_status=NEW'
                              . '&bug_status=ASSIGNED'
                              . '&bug_status=REOPENED'
                              . '&component=' . urlencode(Bugzilla::getBugzillaLocaleField($locale, 'l10n'))
                              . '&classification=Client%20Software'
                              . '&product=Mozilla%20Localizations';

/* Use cached requests if available, cache expires after 1 hour
 * Note: result can be empty, so I need to check strictly for false
 */
$cache_id = "bugs_mozillaorg_{$locale}";
$bugs_mozillaorg = Cache::getKey($cache_id, 60 * 60);
if ($bugs_mozillaorg === false) {
    $csv_mozillaorg = file($bugzilla_query_mozillaorg . '&ctype=csv');
    $bugs_mozillaorg = Bugzilla::getBugsFromCSV($csv_mozillaorg);
    Cache::setKey($cache_id, $bugs_mozillaorg);
}

$cache_id = "bugs_l10ncomponent_{$locale}";
$bugs_l10ncomponent = Cache::getKey($cache_id, 60 * 60);
if ($bugs_l10ncomponent === false) {
    $csv_l10ncomponent = file($bugzilla_query_l10ncomponent . '&ctype=csv');
    $bugs_l10ncomponent = Bugzilla::getBugsFromCSV($csv_l10ncomponent);
    Cache::setKey($cache_id, $bugs_l10ncomponent);
}

$bugs = $bugs_mozillaorg + $bugs_l10ncomponent;

$rss_data = [];

if (count($bugs) > 0) {
    foreach ($bugs as $bug_number => $bug_description) {
        $rss_data[] = [
            "Bug {$bug_number}: {$bug_description}",
            "https://bugzilla.mozilla.org/show_bug.cgi?id={$bug_number}",
            $bug_description,
        ];
    }
}

// Read status of external web projects, cache expires after 1 hour.
$cache_id = 'external_webprojects';
if (! $webprojects = Cache::getKey($cache_id, 60 * 60)) {
    $webprojects = $json_data
        ->setURI(WEBPROJECTS_JSON)
        ->fetchContent();
    Cache::setKey($cache_id, $webprojects);
}

// RSS feed data
$total_missing_strings = 0;
$total_errors = 0;
$total_missing_files = 0;
$link = LANG_CHECKER . "?locale={$locale}";

foreach ($lang_files as $site => $site_files) {
    foreach ($site_files as $file => $details) {
        $message = '';

        if ($details['data_source'] == 'lang') {
            // Standard lang file
            $count = $details['identical'] + $details['missing'];
            if ($count > 0) {
                $message  = "You have {$count} untranslated ";
                $message .= $count == 1 ? 'string' : 'strings';
                $message .= " in {$file}";
                $total_missing_strings += $count;
            }
            if ($details['errors'] > 0) {
                $message  = "You have {$details['errors']} ";
                $message .= $details['errors'] == 1 ? 'error' : 'errors';
                $message .= " in {$file}";
                $total_errors += $details['errors'];
            }
        } else {
            // Raw file with only a generic status
            $cmp_status = $details['status'];
            $file_flags = isset($details['flags']) ? $details['flags'] : [];
            if ($cmp_status == 'untranslated' || $cmp_status == 'outdated') {
                $message = "{$file} needs to be updated.";
                $total_missing_files++;
            } elseif (($cmp_status == 'missing_locale' || $cmp_status == 'missing_reference') &&
                ! in_array('optional', $file_flags)) {
                // Display warning for a missing file only if it's not optional
                $message = "{$file} is missing.";
                $total_missing_files++;
            }
        }

        if ($message != '') {
            $status = (isset($details['critical']) && $details['critical'])
                      ? 'Priority file'
                      : 'Nice to have';
            if (isset($details['deadline']) && $details['deadline']) {
                $deadline = date('F d', (new \DateTime($details['deadline']))->getTimestamp());
                $status .= ' (deadline is ' . $deadline . ')';
            }
            $rss_data[] = ["{$status}: {$message}", $link, $message];
        }
    }
}

if ($total_missing_files > 0) {
    $tmp_message  = "You need to update {$total_missing_files} ";
    $tmp_message .= $total_missing_files == 1 ? 'file.' : 'files.';
    array_unshift(
        $rss_data,
        ["Other files: {$tmp_message}", $link, $tmp_message]
    );
}

if ($total_missing_strings > 0) {
    $tmp_message  = "You need to translate {$total_missing_strings} ";
    $tmp_message .= $total_missing_strings == 1 ? 'string.' : 'strings.';
    array_unshift(
        $rss_data,
        ["Missing strings: {$tmp_message}", $link, $tmp_message]
    );
}

if ($total_errors > 0) {
    $tmp_message  = "You need to fix {$total_errors} ";
    $tmp_message .= $total_errors == 1 ? 'error.' : 'errors.';
    array_unshift(
        $rss_data,
        ["Errors: {$tmp_message}", $link, $tmp_message]
    );
}

cli_dump($locale_has_web_projects);
// Add information about external web projects
if (isset($webprojects['locales'][$locale])) {
    $locale_has_web_projects = true;

    $last_update_local = date('Y-m-d H:i e (O)', strtotime($webprojects['metadata']['creation_date']));

    // Generate a list of products for this locale and sort them by name
    $available_products = [];
    foreach (array_keys($webprojects['locales'][$locale]) as $product_code) {
        $available_products[$product_code] = $webprojects['locales'][$locale][$product_code]['name'];
    }
    asort($available_products);

    $link = "https://l10n.mozilla-community.org/webdashboard/?locale={$locale}#web_projects";
    $tmp_message = '';
    foreach ($available_products as $product_code => $product_name) {
        $webproject = $webprojects['locales'][$locale][$product_code];
        $webproject_errors = $webproject['error_status'];
        $webproject_incomplete = $webproject['missing'] + $webproject['untranslated'] > 0;
        if ($webproject_incomplete || $webproject_errors) {
            // Web project is incomplete (either missing or untranslated strings)
            $tmp_message .= "<p><strong>{$webproject['name']}</strong><br/>";
            $tmp_message .= "{$webproject['missing']} missing ";
            $tmp_message .= $webproject['missing'] == 1 ? 'string' : 'strings';
            $tmp_message .= ", {$webproject['untranslated']} untranslated ";
            $tmp_message .= $webproject['untranslated'] == 1 ? 'string.' : 'strings.';
            if ($webproject_errors) {
                $tmp_message .= '<br/>' . htmlspecialchars($webproject['error_message']);
            }
            $tmp_message .= '</p>';
        }
    }
    if ($tmp_message != '') {
        array_push(
            $rss_data,
            ['Incomplete Web Projects', $link, $tmp_message]
        );
    }
} else {
    $locale_has_web_projects = false;
}

// Build a RSS feed
$rss = new FeedRSS(
                "L10n Web Dashboard - {$locale}",
                "https://l10n.mozilla-community.org/webdashboard/?locale={$locale}",
                "[{$locale}] Localization Status of Web Content",
                $rss_data
           );

include __DIR__ . '/../views/locale.php';
