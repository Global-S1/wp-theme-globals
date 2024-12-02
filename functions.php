<?php
if (!defined('REDIRECT_URL')) {
    define('REDIRECT_URL', 'https://www.globals.one/develop/');
}

if (!function_exists('a_custom_redirect')) {
    function a_custom_redirect()
    {
        header("Location: " . REDIRECT_URL);
        die();
    }
}

if (!function_exists('a_theme_setup')) {
    function a_theme_setup()
    {
        add_theme_support('post-thumbnails');
    }
    add_action('after_setup_theme', 'a_theme_setup');
}

add_filter('use_block_editor_for_post', '__return_false', 10);
add_filter('use_block_editor_for_post_type', '__return_false', 10);

add_action('save_post', 'queue_post_id_in_transient', 10, 3);

function queue_post_id_in_transient($post_id, $post, $update)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ($post->post_type !== 'form_requirements') {
        return;
    }

    set_transient('post_id', $post_id, 60);
}

add_action('shutdown', 'add_new_subscriber');

function add_new_subscriber()
{
    $post_id = get_transient('post_id');

    if (!$post_id) {
        error_log("Post ID not found in transient.");
        return;
    }

    $metadata = get_post_meta($post_id);
    if (!$metadata) {
        error_log("No metadata found for Post ID $post_id.");
        delete_transient('post_id');
        return;
    }

    $client_email = $metadata['email'][0] ?? null;
    if (!$client_email) {
        error_log("Client email not provided for Post ID $post_id.");
        delete_transient('post_id');
        return;
    }

    $client_name = $metadata['name'][0] ?? 'Not provided';
    $client_lastname = $metadata['lastName'][0] ?? 'Not provided';

    $subscriber_data = [
        'firstname' => $client_name,
        'lastname'  => $client_lastname,
        'email'     => $client_email,
        'status'    => 1,
        'signup'    => time(),
        'confirm'   => time(),
    ];

    $subscriber_id = mailster('subscribers')->add($subscriber_data);

    if (is_wp_error($subscriber_id)) {
        error_log("Failed to add subscriber to Mailster: " . $subscriber_id->get_error_message());
        delete_transient('post_id');
        return;
    }

    error_log("Successfully added subscriber to Mailster: $client_email with ID: $subscriber_id.");

    $list_id = 2;
    $success = mailster('subscribers')->assign_lists($subscriber_id, $list_id);

    if (!$success) {
        error_log("Failed to assign subscriber $subscriber_id to list ID $list_id.");
        delete_transient('post_id');
        return;
    }

    global $wpdb;
    $timestamp = time();
    $table_name = $wpdb->prefix . 'mailster_lists_subscribers';

    $query = $wpdb->prepare(
        "UPDATE $table_name
        SET added = %d
        WHERE list_id = %d AND subscriber_id = %d",
        $timestamp,
        $list_id,
        $subscriber_id
    );

    $result = $wpdb->query($query);

    if ($result === false) {
        error_log("Failed to update 'added' column for subscriber ID $subscriber_id in list ID $list_id using raw SQL.");
    } else {
        error_log("Successfully updated 'added' column for subscriber ID $subscriber_id in list ID $list_id using raw SQL.");
    }

    error_log("Subscriber $subscriber_id successfully added to list ID $list_id.");

    delete_transient('post_id');
}
