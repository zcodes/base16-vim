<?php

use Base16\Builder;

/**
 * Base16 Builder CLI (Command Line Interface)
 */

// Source paths
$sources_list = 'sources.yaml';
$schemes_list = 'sources/schemes/list.yaml';
$templates_list = 'sources/templates/list.yaml';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "You must run 'composer install' before using base16-builder-php.\n";
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

$builder = new Builder;

// Parse sources lists
$src_list = Builder::parse($sources_list);
$sch_list = [];

if (file_exists($schemes_list)) {
    $sch_list = Builder::parse($schemes_list);
}

/**
 * Switches between functions based on supplied argument
 */
switch (@$argv[1]) {

    /**
     * Displays a help message
     */
    case '-h':
        echo "Base16 Builder PHP CLI\n";
        echo "https://github.com/chriskempson/base16-builder-php\n";
        break;

        /**
         * Updates template and scheme sources
         */
    case 'update':
        $builder->updateSources($src_list, 'sources');

        // Parse source lists incase the sources have just been fetched
        if (file_exists($schemes_list)) {
            $sch_list = Builder::parse($schemes_list);
        }

        $builder->updateSources($sch_list, 'schemes');
        break;

        /**
         * Build all themes and schemes
         */
    default:
        if (count($sch_list) == 0) {
            echo "Warning: Could not parse schemes or missing "
                . "$schemes_list, did you do `php ${argv[0]} update`?\n";
        }

        $rendered_templates = [];
        $tpl_confs = Builder::parse("templates/config.yaml");

        // Loop template files
        foreach ($tpl_confs as $tpl_file => $tpl_conf) {

            $file_path = $tpl_conf['output'];

            // Remove all previous output
            array_map('unlink', glob(
                $file_path. '/base16-*' . $tpl_conf['extension']
            ));

            // Loop scheme repositories
            foreach ($sch_list as $sch_name => $sch_url) {

                // Loop scheme files
                foreach (glob("schemes/$sch_name/*.yaml") as $sch_file) {

                    $sch_data = Builder::parse($sch_file);
                    $tpl_data = $builder->buildTemplateData($sch_data);

                    $sch_slug = Builder::slugify(
                        basename($sch_file, '.yaml'));
                    $tpl_data['scheme-slug'] = $sch_slug;

                    $file_name = 'base16-' .  $sch_slug
                        . $tpl_conf['extension'];

                    $render = $builder->renderTemplate(
                        "templates/$tpl_file"
                        . ".mustache",
                        $tpl_data);

                    $builder->writeFile($file_path, $file_name, $render);

                    $full_file = $file_path . '/' . $file_name;

                    // Store a list of templates that have been 
                    // overwritten. This may happen if scheme repos house
                    // schemes files with the same names
                    $overwritten_templates = [];
                    if (in_array($full_file, $rendered_templates)) {
                        $overwritten_templates[] = $full_file;
                    }

                    // Store a list of templates generated for this 
                    // session
                    $rendered_templates[] = $full_file;

                    echo "Built $full_file\n";
                }
            }
        }

        // Warn if a template has been overwritten
        foreach ($overwritten_templates as $overwritten_template) {
            echo "\nWarning: $overwritten_template was overwritten.\n";
        }

        break;
}
