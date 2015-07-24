<?php
namespace Webdashboard;

$lang_files_status = '';

cli_dump($lang_files);
foreach ($lang_files as $site => $site_files) {
    $rows = '';
    $display_errors = false;

    foreach ($site_files as $file => $details) {
        // Determine critical status
        $critical = (isset($details['critical']) && $details['critical']) ? '<strong>Yes</strong>' : 'No';
        $lang[]['critical'] = $critical;

        // Determine deadline status
        $deadline_class = '';
        if (isset($details['deadline'])) {
            $deadline_timestamp = (new \DateTime($details['deadline']))->getTimestamp();
            $deadline = date('F d', $deadline_timestamp);
            $last_week = $deadline_timestamp - 604800; // 7 days (60 * 60 * 24 * 7)
            $current_time = time();
            if ($deadline_timestamp < $current_time) {
                $deadline = date('F d Y', $deadline_timestamp);
                $deadline_class = 'deadline_overdue';
            } elseif ($last_week < $current_time) {
                $deadline_class = 'deadline_closing';
            }
        } else {
            $deadline = '-';
        }
        $lang[]['deadline'] = $deadline;

        if ($details['data_source'] == 'lang') {
            // Standard .lang file
            $file_missing = $details['identical'] + $details['missing'];
            $file_errors = $details['errors'];
            if ($file_missing + $file_errors > 0) {
                // File has missing strings (identical or actually missing)
                $display_errors = true;
                $url = LANG_CHECKER . "?locale={$locale}#{$file}";
                if ($file_errors > 0) {
                    $error_display = "<a href='{$url}'>{$file_errors}</a>";
                } else {
                    $error_display = '-';
                }
                if ($file_missing > 0) {
                    $missing_display = "<a href='{$url}'>{$file_missing}</a>";
                } else {
                    $missing_display = '-';
                }
                $rows .= "  <tr>\n" .
                         "    <th class='main_column'><a href='{$url}'>{$file}</a></th>\n" .
                         "    <td>{$missing_display}</td>\n" .
                         "    <td>{$error_display}</td>\n" .
                         "    <td class='{$deadline_class}'>{$deadline}</td>\n" .
                         "    <td>{$critical}</td>\n" .
                         "  </tr>\n";
            }
        } else {
            // Raw file with only a generic status
            $url = LANG_CHECKER . "?locale={$locale}#{$site}";
            $cmp_status = $details['status'];
            $file_flags = isset($details['flags']) ? $details['flags'] : [];

            // We display a file only if it's untranslated or outdated, other cases
            // are displayed only if file is not optional
            $hide_file = true;

            if ($cmp_status == 'untranslated' || $cmp_status == 'outdated') {
                $hide_file = false;
            } elseif (($cmp_status == 'missing_locale' || $cmp_status == 'missing_reference') &&
                ! in_array('optional', $file_flags)) {
                // File is missing and it's not optional
                $hide_file = false;
            }

            if (! $hide_file) {
                // Display warnings only if the file is not optional
                $rows .= "  <tr>\n" .
                         "    <th class='main_column'><a href='{$url}'>{$file}</a></th>\n" .
                         "    <td><span class='raw_status raw_{$cmp_status}'>" . str_replace('_', ' ', $cmp_status) . "</span></td>" .
                         "    <td class='{$deadline_class}'>{$deadline}</td>\n" .
                         "    <td>{$critical}</td>\n" .
                         "  </tr>\n";
                $display_errors = true;
            }
        }
    }

    if ($display_errors) {
        /* The type of data source is identical for all files in a website.
         * Since I have errors, there's at least one file that I can use to
         * determine the data source type, no need to check if it exists.
         */
        $data_source_type = array_shift($site_files)['data_source'];

        if ($data_source_type == 'lang') {
            // Standard .lang file
            $lang_files_status .= "\n<table class='file_detail'>\n" .
                                  "  <tr>\n" .
                                  "    <th class='main_column'>{$site}</th>\n" .
                                  "    <th>Missing</th>\n" .
                                  "    <th>Errors</th>\n" .
                                  "    <th>Deadline</th>\n" .
                                  "    <th>Critical</th>\n" .
                                  "  </tr>\n" .
                                  $rows .
                                  "</table>\n";
        } else {
            // Raw file with only a generic status
            $lang_files_status .= "\n<table class='file_detail'>\n" .
                                  "  <tr>\n" .
                                  "    <th class='main_column'>{$site}</th>\n" .
                                  "    <th>Status</th>\n" .
                                  "    <th>Deadline</th>\n" .
                                  "    <th>Critical</th>\n" .
                                  "  </tr>\n" .
                                  $rows .
                                  "</table>\n";
        }
    } else {
        $lang_files_status .= "\n<table>\n" .
                              "  <tr>\n" .
                              "    <th class='main_column'>{$site}</th>\n" .
                              "    <th><span style='color:gray'>All Files translated</span></th>\n" .
                              "  </tr>\n" .
                              "</table>\n";
    }
}


$lang_files_status = '';

foreach ($lang_files as $site => $site_files) {
    $display_errors = false;

    foreach ($site_files as $file => $details) {
        // Determine critical status, boolean
        $lang[]['critical'] = isset($details['critical']) && $details['critical'];

        // Determine deadline status
        $deadline_class = '';
        $deadline = '-';

        if (isset($details['deadline'])) {
            $deadline_timestamp = (new \DateTime($details['deadline']))->getTimestamp();
            if ($deadline_timestamp < time()) {
                $deadline = date('F d Y', $deadline_timestamp);
                $deadline_class = 'deadline_overdue';
            } elseif (($deadline_timestamp - 604800) < time()) { // 7 days (60 * 60 * 24 * 7)
                $deadline = date('F d', $deadline_timestamp);
                $deadline_class = 'deadline_closing';
            }
        }

        $lang[]['deadline'] = $deadline;

        if ($details['data_source'] == 'lang') {
            // Standard .lang file
            $file_missing = $details['identical'] + $details['missing'];
            $file_errors = $details['errors'];
            if ($file_missing + $file_errors > 0) {
                // File has missing strings (identical or actually missing)
                $display_errors = true;

                $link = function ($errors) use ($locale, $file) {
                    $url = LANG_CHECKER . "?locale={$locale}#{$file}";
                    return $errors > 0
                    ? "<a href='{$url}'>{$errors}</a>"
                    : '-';
                };

                $error_display   = $link($file_errors);
                $missing_display = $link($file_missing);

                $rows[$site][$file] = [
                    'url'            => $url,
                    'file'           => $file,
                    'missing'        => $missing_display,
                    'error'          => $error_display,
                    'deadline_class' => $deadline_class,
                    'deadline'       => $deadline,
                    'critical'       => $critical,

                ];
            }
        } else {
            // Raw file with only a generic status
            $url = LANG_CHECKER . "?locale={$locale}#{$site}";
            $cmp_status = $details['status'];
            $file_flags = isset($details['flags']) ? $details['flags'] : [];

            // We display a file only if it's untranslated or outdated, other cases
            // are displayed only if file is not optional
            $hide_file = true;

            if ($cmp_status == 'untranslated' || $cmp_status == 'outdated') {
                $hide_file = false;
            } elseif (($cmp_status == 'missing_locale' || $cmp_status == 'missing_reference') &&
                ! in_array('optional', $file_flags)) {
                // File is missing and it's not optional
                $hide_file = false;
            }

            if (! $hide_file) {
                // Display warnings only if the file is not optional
                $rows .= "  <tr>\n" .
                         "    <th class='main_column'><a href='{$url}'>{$file}</a></th>\n" .
                         "    <td><span class='raw_status raw_{$cmp_status}'>" . str_replace('_', ' ', $cmp_status) . "</span></td>" .
                         "    <td class='{$deadline_class}'>{$deadline}</td>\n" .
                         "    <td>{$critical}</td>\n" .
                         "  </tr>\n";
                $display_errors = true;
            }
        }
    }

    if ($display_errors) {
        /* The type of data source is identical for all files in a website.
         * Since I have errors, there's at least one file that I can use to
         * determine the data source type, no need to check if it exists.
         */
        $data_source_type = array_shift($site_files)['data_source'];

        if ($data_source_type == 'lang') {
            // Standard .lang file
            $lang_files_status .= "\n<table class='file_detail'>\n" .
                                  "  <tr>\n" .
                                  "    <th class='main_column'>{$site}</th>\n" .
                                  "    <th>Missing</th>\n" .
                                  "    <th>Errors</th>\n" .
                                  "    <th>Deadline</th>\n" .
                                  "    <th>Critical</th>\n" .
                                  "  </tr>\n" .
                                  $rows .
                                  "</table>\n";
        } else {
            // Raw file with only a generic status
            $lang_files_status .= "\n<table class='file_detail'>\n" .
                                  "  <tr>\n" .
                                  "    <th class='main_column'>{$site}</th>\n" .
                                  "    <th>Status</th>\n" .
                                  "    <th>Deadline</th>\n" .
                                  "    <th>Critical</th>\n" .
                                  "  </tr>\n" .
                                  $rows .
                                  "</table>\n";
        }
    } else {
        $lang_files_status .= "\n<table>\n" .
                              "  <tr>\n" .
                              "    <th class='main_column'>{$site}</th>\n" .
                              "    <th><span style='color:gray'>All Files translated</span></th>\n" .
                              "  </tr>\n" .
                              "</table>\n";
    }
}

$webprojects_data = [];
if ($locale_has_web_projects) {
    foreach ($available_products as $product_code => $product_name) {
        $webproject = $webprojects['locales'][$locale][$product_code];

        // Initialize values
        $untrans_width = 0;
        $fuzzy_width   = 0;
        $errors_width  = 0;
        $trans_width   = 0;

        if ($webproject['error_status']) {
            // File has errors
            $errors_width = 100;
            $message = str_replace('\'', '"', htmlspecialchars($webproject['error_message']));
        } elseif ($webproject['total'] === 0) {
            // File is empty
            $message = "File is empty (no strings)";
        } else {
            if ($webproject['source_type'] == 'properties') {
                // Web project based on .properties
                $fuzzy_width = 0;
                $identical_width = floor($webproject['identical'] / $webproject['total'] * 100);
                $untrans_width = floor($webproject['missing'] / $webproject['total'] * 100);
                $message = "{$webproject['translated']} translated, {$webproject['missing']} missing, {$webproject['identical']} identical";
            } elseif ($webproject['source_type'] == 'xliff') {
                // Web project based on .xliff files
                $fuzzy_width = 0;
                $total = $webproject['total'] + $webproject['missing'];
                $missing = $webproject['untranslated'] + $webproject['missing'];
                $identical_width = floor($webproject['identical'] / $total * 100);
                $untrans_width = floor($missing / $total * 100);
                $message = "{$webproject['translated']} translated, {$webproject['missing']} missing, {$webproject['untranslated']} untranslated, {$webproject['identical']} identical";
            } else {
                // Web project based on .po files (default)
                $fuzzy_width = floor($webproject['fuzzy'] / $webproject['total'] * 100);
                $identical_width = 0;
                $untrans_width = floor($webproject['untranslated'] / $webproject['total'] * 100);
                $message = "{$webproject['translated']} translated, {$webproject['untranslated']} untranslated, {$webproject['fuzzy']} fuzzy";
            }
        }

        if ($errors_width === 0) {
            $trans_width = 100 - $fuzzy_width - $identical_width - $untrans_width;
        }

        $webprojects_data[] = [
            'name'         => $webproject['name'],
            'percent'      => $webproject['percentage'],
            'message'      => $message,
            'errors'       => $errors_width,
            'translated'   => $trans_width,
            'untranslated' => $untrans_width,
            'fuzzy_width'  => $fuzzy_width,
            'identical'    => $identical_width
        ];
    }
}

/*
if we ask for an rss page, we just pass the $rss object created
in the model that contains the data we want to the object renderer
 */
if (! isset($_GET['rss'])) {
    print $twig->render(
        'locale.twig',
        [
            // 'main_content' => '',
            'body_class'              => '',
            'locale'                  => $locale,
            'locamotion'              => $locamotion,
            'lang_files'              => $lang_files,
            'lang_files_status'       => $lang_files_status,
            'last_update_local'       => $last_update_local,
            'has_lang_files'          => count($lang_files) > 0,
            'has_bugs'                => count($bugs) > 0,
            'bugs'                    => $bugs,
            'locale_has_web_projects' => $locale_has_web_projects,
            'webprojects_data'        => $webprojects_data,
            'display_errors'          => $display_errors,
            'data_source_type'        => $data_source_type,
        ]
    );
} else {
    print $rss->buildRSS();
}
