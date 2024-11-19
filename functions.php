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

    $queued_post_ids = get_transient('queued_post_ids') ?: [];
    $queued_post_ids[] = $post_id;
    set_transient('queued_post_ids', $queued_post_ids, 60);
}

add_action('shutdown', 'process_transient_post_ids_after_shutdown');

function process_transient_post_ids_after_shutdown()
{
    $queued_post_ids = get_transient('queued_post_ids');
    if (!empty($queued_post_ids)) {
        foreach ($queued_post_ids as $post_id) {
            $metadata = get_post_meta($post_id);

            $client_email = $metadata['email'][0] ?? null;
            $client_name = $metadata['name'][0] ?? 'Not provided';
            $client_lastname = $metadata['lastName'][0] ?? 'Not provided';
            $admin_email = get_option('admin_email');

            $formatted_data = [
                'First Name' => $client_name,
                'Last Name' => $client_lastname,
                'Correo Electrónico' => $client_email ?? 'Not provided',
                'Phone' => $metadata['phone'][0] ?? 'Not provided',
                'Selecciona el tipo de proyecto' => $metadata['requirements'][0] ?? 'Not provided',
                'Descripción' => $metadata['description'][0] ?? 'Not provided',
            ];

            if ($client_email) {
                $subscriber_data = [
                    'firstname'  => $client_name,
                    'lastname'   => $client_lastname,
                    'email'      => $client_email,
                    'status'     => 1,
                ];

                $subscriber_id = mailster('subscribers')->add($subscriber_data);

                if (!is_wp_error($subscriber_id)) {
                    error_log("Successfully added subscriber to Mailster: $client_email with ID: $subscriber_id.");
                } else {
                    error_log("Failed to add subscriber to Mailster: " . $subscriber_id->get_error_message());
                }
            } else {
                error_log("Client email not provided for Post ID $post_id.");
            }

            $email_subject = "New Form Submission Received";
            $email_message = "Hello,\n\nYou have received a new form submission. Here are the details:\n\n";
            foreach ($formatted_data as $key => $value) {
                $email_message .= "{$key}: {$value}\n";
            }
            $email_message .= "\nThank you.";

            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            if ($admin_email) {
                $admin_mail_result = wp_mail($admin_email, $email_subject, $email_message, $headers);
                if ($admin_mail_result) {
                    error_log("Email sent successfully to admin: $admin_email for Post ID $post_id.");
                } else {
                    error_log("Failed to send email to admin: $admin_email for Post ID $post_id.");
                }
            } else {
                error_log("Admin email is not configured for Post ID $post_id.");
            }

            if ($client_email) {
                $client_subject = "Thank you for your submission!";
                $client_message = "Hello {$formatted_data['First Name']},\n\n";
                $client_message .= "We have received your form submission with the following details:\n\n";
                foreach ($formatted_data as $key => $value) {
                    $client_message .= "{$key}: {$value}\n";
                }
                $client_message .= "\nWe will get back to you shortly.\n\nBest regards,\nYour Company Team";

                $client_mail_result = wp_mail($client_email, $client_subject, $client_message, $headers);
                if ($client_mail_result) {
                    error_log("Email sent successfully to client: $client_email for Post ID $post_id.");
                } else {
                    error_log("Failed to send email to client: $client_email for Post ID $post_id.");
                }
            } else {
                error_log("Client email not provided for Post ID $post_id.");
            }
        }
        delete_transient('queued_post_ids');
    }
}
