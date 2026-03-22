	<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure only administrators can access
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <p class="description">
        <?php
        esc_html_e(
            'Paste your Firebase Web App configuration to connect this WordPress admin panel to your Firebase project.',
            'backoffice-manager-for-firebase'
        );
        ?>
    </p>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'bomff_settings_group' );
        do_settings_sections( 'bomff-settings' );
        submit_button( __( 'Save Firebase configuration', 'backoffice-manager-for-firebase' ) );
        ?>
    </form>
</div>
