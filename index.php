<?php
// if (function_exists('a_custom_redirect')) {
// a_custom_redirect();
// }
// header("Location: https://www.globals.one/develop/");
// exit;

if (!function_exists('a_custom_redirect')) {
    require_once get_template_directory() . '/functions.php';
}
a_custom_redirect();
