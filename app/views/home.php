<?php

$main_content = '<div id="locales"><ul>';

foreach ($locales as $locale) {
    $main_content .= "<li><a href=\"./?locale={$locale}\">{$locale}</a></li>";
}

$main_content .= "</ul></div>";

print $twig->render(
    'default.twig',
    [
        'main_content' => $main_content,
        'body_class' => '',
    ]
);
