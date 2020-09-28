<?php
/**
 * A PHP implementation of a Base16 Theme Builder
 * Chris Kempson (http://chriskempson.com)
 *
 * Follows the conventions at
 *     http://chriskempson.com/projects/base16/builder.md
 */

namespace Base16;

use Symfony\Component\Yaml\Yaml;
use Mexitek\PHPColors\Color;

class Builder
{
    /**
     * Parses a YAML file
     */
    static public function parse($path)
    {
        return Yaml::parse( file_get_contents($path) );
    }

    /**
     * Uses git to fetch template or scheme sources
     */
    public function fetchSources($url_list, $path)
    {
        foreach ($url_list as $name => $url) {
            if (!file_exists("$path/$name")) {
                exec("git clone $url $path/$name\n");
            }
        }
    }

    /**
     * Uses git to update template or scheme sources
     */
    public function updateSources($url_list, $path)
    {
        foreach ($url_list as $name => $url) {
            echo "\n-- $path/$name\n";
            if (file_exists("$path/$name")) {
                echo exec("git -C $path/$name pull\n") . "\n";
            } else {
                $this->fetchSources([$name => $url], $path);
            }
        }
    }

    /**
     * Renders a template using Mustache
     */
    public function renderTemplate($path, $template_data)
    {
        $mustache = new \Mustache_Engine();
        $tpl = $mustache->loadTemplate($this->readFile($path));
        return $tpl->render($template_data);
    }

    /**
     * Populates a list of variables to be used in templates
     */
    public function buildTemplateData($scheme_data)
    {
        $vars['scheme-name'] = $scheme_data['scheme'];
        $vars['scheme-author'] = $scheme_data['author'];

        $bases = array('00', '01', '02', '03', '04', '05', '06', '07', '08',
            '09', '0A', '0B', '0C', '0D', '0E', '0F');

        foreach ($bases as $base) {
            $base_key = 'base' . $base;
            $color = new Color($scheme_data[$base_key]);

            $vars[$base_key . '-hex'] = $color->getHex();
            $vars[$base_key . '-hex-bgr'] = substr($color->getHex(), 4, 2) . 
                substr($color->getHex(), 2, 2) . substr($color->getHex(), 0, 2);
            $vars[$base_key . '-hex-r'] = substr($color->getHex(), 0, 2);
            $vars[$base_key . '-hex-g'] = substr($color->getHex(), 2, 2);
            $vars[$base_key . '-hex-b'] = substr($color->getHex(), 4, 2);
            $vars[$base_key . '-rgb-r'] = $color->getRgb()['R'];
            $vars[$base_key . '-rgb-g'] = $color->getRgb()['G'];
            $vars[$base_key . '-rgb-b'] = $color->getRgb()['B'];
            $vars[$base_key . '-dec-r'] = $color->getRgb()['R'] / 255;
            $vars[$base_key . '-dec-g'] = $color->getRgb()['G'] / 255;
            $vars[$base_key . '-dec-b'] = $color->getRgb()['B'] / 255;
        }

        // determine theme brightness with default background base00
        $hsp = sqrt(
            0.299 * ($vars['base00-rgb-r'] * $vars['base00-rgb-r']) +
            0.587 * ($vars['base00-rgb-g'] * $vars['base00-rgb-g']) +
            0.114 * ($vars['base00-rgb-b'] * $vars['base00-rgb-b'])
        );

        if ($hsp > 127.5) {
            $vars['brightness'] = 'light';
            $vars['diff_red'] = 'ffaaaa';
            $vars['diff_green'] = 'bbffbb';
            $vars['diff_yellow'] = 'ffffaa';
            $vars['diff_aqua'] = 'bbeeff';
        } else {
            $vars['brightness'] = 'dark';
            $vars['diff_red'] = '663333';
            $vars['diff_green'] = '336633';
            $vars['diff_yellow'] = '666633';
            $vars['diff_aqua'] = '333366';
        }

        return $vars;
    }

    /**
     * Reads a file
     */
    public function readFile($path)
    {
        return file_get_contents($path);
    }

    /**
     * Writes a file
     */
    public function writeFile($file_path, $file_name, $contents)
    {
        if (!is_dir($file_path)) mkdir($file_path);
        file_put_contents($file_path . '/' . $file_name, $contents);
    }

    /**
     * Slugify a string
     */
    static public function slugify($string)
    {
        return str_replace(' ', '-', strtolower($string));
    }

}
