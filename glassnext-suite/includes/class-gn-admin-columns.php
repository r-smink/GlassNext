<?php
if (!defined('ABSPATH')) exit;

add_filter('manage_gn_submission_posts_columns', function($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        if ($key === 'title') {
            $new['cb'] = $columns['cb'];
            $new['title'] = 'Aanvraag';
            $new['gn_offer_number'] = 'Offertenummer';
            $new['gn_customer'] = 'Klant / organisatie';
            $new['gn_email'] = 'E-mail';
            $new['gn_status'] = 'Status';
            $new['date'] = 'Datum';
        } elseif ($key !== 'cb') {
            $new[$key] = $label;
        }
    }
    if (!isset($new['cb'])) $new['cb'] = '<input type="checkbox">';
    return $new;
});

add_action('manage_gn_submission_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'gn_offer_number':
            echo esc_html(get_post_meta($post_id, '_gn_offer_number', true));
            break;
        case 'gn_customer':
            echo esc_html(get_post_meta($post_id, '_gn_customer_name', true));
            break;
        case 'gn_email':
            echo esc_html(get_post_meta($post_id, '_gn_email', true));
            break;
        case 'gn_status':
            $status = get_post_meta($post_id, '_gn_status', true) ?: 'nieuw';
            $colors = ['nieuw' => '#1786d8', 'bekeken' => '#b45309', 'afgehandeld' => '#087f5b'];
            $color = $colors[$status] ?? '#64748b';
            echo '<span style="color:' . esc_attr($color) . ';font-weight:700;">' . esc_html(ucfirst($status)) . '</span>';
            break;
    }
}, 10, 2);

add_action('add_meta_boxes', function() {
    add_meta_box('gn_submission_detail', 'Aanvraag details', function($post) {
        $offer_number = get_post_meta($post->ID, '_gn_offer_number', true);
        $customer = get_post_meta($post->ID, '_gn_customer_name', true);
        $contact = get_post_meta($post->ID, '_gn_contact_name', true);
        $email = get_post_meta($post->ID, '_gn_email', true);
        $phone = get_post_meta($post->ID, '_gn_phone', true);
        $address = get_post_meta($post->ID, '_gn_address', true);
        $city = get_post_meta($post->ID, '_gn_city', true);
        $status = get_post_meta($post->ID, '_gn_status', true) ?: 'nieuw';
        $filename = get_post_meta($post->ID, '_gn_filename', true);

        echo '<table class="form-table">';
        echo '<tr><th>Offertenummer</th><td><strong>' . esc_html($offer_number) . '</strong></td></tr>';
        echo '<tr><th>Klant / organisatie</th><td>' . esc_html($customer) . '</td></tr>';
        echo '<tr><th>Contactpersoon</th><td>' . esc_html($contact) . '</td></tr>';
        echo '<tr><th>E-mail</th><td>' . esc_html($email) . '</td></tr>';
        echo '<tr><th>Telefoon</th><td>' . esc_html($phone) . '</td></tr>';
        echo '<tr><th>Adres</th><td>' . esc_html($address) . '</td></tr>';
        echo '<tr><th>Postcode / plaats</th><td>' . esc_html($city) . '</td></tr>';
        echo '<tr><th>Status</th><td>';
        echo '<select name="gn_status">';
        foreach (['nieuw' => 'Nieuw', 'bekeken' => 'Bekeken', 'afgehandeld' => 'Afgehandeld'] as $val => $label) {
            echo '<option value="' . esc_attr($val) . '" ' . selected($status, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</table>';

        echo '<p style="margin-top:20px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?gn_download_json=1&post_id=' . $post->ID)) . '" class="button button-primary">';
        echo 'JSON bestand downloaden (' . esc_html($filename) . ')';
        echo '</a>';
        echo '</p>';
    }, 'gn_submission', 'normal', 'high');
});

add_action('save_post_gn_submission', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['gn_status'])) {
        update_post_meta($post_id, '_gn_status', sanitize_text_field($_POST['gn_status']));
    }
});
