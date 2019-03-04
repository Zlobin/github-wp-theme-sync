<?php

/**
 * Plugin Name: github_theme_sync
 * Plugin URI:
 * Description: wordpress theme sync with github
 * Version: 1.0.0
 * Author: Eugene Zlobin
 * Author URI: https://github.com/Zlobin
 * License: GNU GENERAL PUBLIC LICENSE
 */

include_once(ABSPATH . 'wp-admin/includes/theme.php');
include_once(ABSPATH . 'wp-admin/includes/file.php');
include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
include_once(ABSPATH . 'wp-admin/includes/misc.php');
include_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');

include_once('gh_repo_downloader.php');

define('GTS_GH_TOKEN_FIELD', 'gts_gh_token');
define('GTS_GH_REPO_FIELD', 'gts_gh_repo');
define('GTS_GH_BRANCH_FIELD', 'gts_gh_branch');
define('GTS_GH_WEBHOOK_TOKEN', 'gts_gh_webhook_token');
define('GTS_THEME_NAME_FIELD', 'gts_theme_name');
define('GTS_PLUGIN_PATH', plugin_dir_path(__FILE__));

add_action('admin_menu', 'gts_plugin_menu');

if (!function_exists('gts_plugin_menu')) {
    function gts_plugin_menu() {
        add_options_page('Github Theme Sync Options', 'Github Theme Sync', 'manage_options', 'gts', 'gts_admin_menu');
    }
}

if (!function_exists('gts_rrmdir')) {
    function gts_rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir . '/' . $object) == 'dir') {
                        rrmdir($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}

if (!function_exists('gts_admin_menu')) {
    function gts_admin_menu() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (isset($_POST['submit']) && $_POST['submit'] === 'save') {
            update_option(GTS_GH_TOKEN_FIELD, $_POST[GTS_GH_TOKEN_FIELD]);
            update_option(GTS_GH_REPO_FIELD, $_POST[GTS_GH_REPO_FIELD]);
            update_option(GTS_GH_BRANCH_FIELD, $_POST[GTS_GH_BRANCH_FIELD]);
            update_option(GTS_GH_WEBHOOK_TOKEN, $_POST[GTS_GH_WEBHOOK_TOKEN]);
            update_option(GTS_THEME_NAME_FIELD, $_POST[GTS_THEME_NAME_FIELD]);
        }

        if (isset($_POST['submit']) && $_POST['submit'] === 'install') {
            gts_install_theme();
        }

        $gts_gh_token = get_option(GTS_GH_TOKEN_FIELD);
        $gts_gh_repo = get_option(GTS_GH_REPO_FIELD);
        $gts_gh_branch = get_option(GTS_GH_BRANCH_FIELD);
        $gts_gh_webhook_token = get_option(GTS_GH_WEBHOOK_TOKEN);
        $gts_theme_name = get_option(GTS_THEME_NAME_FIELD);

        echo '<div class="wrap">';
        echo '<form name="form1" method="post" action="">';

        echo '<p>Github TOKEN<br>';
        echo '<input type="text" name="' . GTS_GH_TOKEN_FIELD . '" value="' . $gts_gh_token . '" size="20">';
        echo '</p><hr />';

        echo '<p>Github repository<br>';
        echo '<input type="text" name="' . GTS_GH_REPO_FIELD . '" value="' . $gts_gh_repo . '" size="20">';
        echo '</p><hr />';

        echo '<p>Github branch<br>';
        echo '<input type="text" name="' . GTS_GH_BRANCH_FIELD . '" value="' . $gts_gh_branch . '" size="20">';
        echo '</p><hr />';

        echo '<p>Github webhook token<br>';
        echo '<input type="text" name="' . GTS_GH_WEBHOOK_TOKEN . '" value="' . $gts_gh_webhook_token . '" size="20">';
        echo '</p><hr />';

        echo '<p>Theme name folder<br>';
        echo '<input type="text" name="' . GTS_THEME_NAME_FIELD . '" value="' . $gts_theme_name . '" size="20">';
        echo '</p><hr />';

        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="save" /> ';
        echo '<input type="submit" name="submit" class="button-primary" value="install" /> ';
        echo '</p>';


        echo '</form>';
        echo '</div>';
    }
}

if (!function_exists('gts_download_gh_zipball')) {
    function gts_download_gh_zipball() {
        $downloadedFile = GTS_PLUGIN_PATH . 'themes/' . get_option(GTS_THEME_NAME_FIELD) . '.zip';

        // Download from github.
        $repoDL = new GHRepoDownloader();
        $repoDL->download(array(
            'token'  => get_option(GTS_GH_TOKEN_FIELD),
            'branch' => get_option(GTS_GH_BRANCH_FIELD),
            'repo'   => get_option(GTS_GH_REPO_FIELD),
            'saveAs' => $downloadedFile,
        ));

        return $downloadedFile;
    }
}

if (!function_exists('gts_gh_getfoldername')) {
    function gts_gh_getfoldername($zipball) {
        $folderName = '';

        $zip = new ZipArchive;
        if ($zip->open($zipball) === TRUE) {
            $stat = $zip->statIndex(0);
            if ($stat && $stat['name']) {
                $folderName = $stat['name'];
            }
        }

        return $folderName;
    }
}

if (!function_exists('gts_install_theme')) {
    function gts_install_theme() {
        $zipball_path = gts_download_gh_zipball();
        $folder_name = substr(gts_gh_getfoldername($zipball_path), 0, -1);
        $gts_theme_name = get_option(GTS_THEME_NAME_FIELD);
        $theme_folder_path = get_theme_root() . '/';

        gts_rrmdir($theme_folder_path . $folder_name);
        $success = (new Theme_Upgrader())->install($zipball_path);

        if ($success === TRUE) {
            // Remove old theme.
            gts_rrmdir($theme_folder_path . $gts_theme_name);
            // Delete downloaded zipball.
            unlink($zipball_path);
            if (rename($theme_folder_path . $folder_name, $theme_folder_path . $gts_theme_name)) {
                if (wp_get_theme($gts_theme_name)->exists()) {
                    switch_theme($gts_theme_name);
                }
            }
        }
    }
}

if (!function_exists('gts_gh_webhook')) {
    function gts_gh_webhook() {
        $gts_gh_webhook_token = get_option(GTS_GH_WEBHOOK_TOKEN);
        if (isset($_GET['gtswebhook']) && $_GET['gtswebhook'] === $gts_gh_webhook_token) {
            gts_install_theme();
        }
    }
}

add_action('wp_loaded', function() {
    // Activate github webhook.
    gts_gh_webhook();
});
