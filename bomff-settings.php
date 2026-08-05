<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
    return;
}

$bomff_service_account = $bomff_view_data['service_account'] ?? null;
$bomff_active_tab      = $bomff_view_data['active_tab'] ?? 'connection';
$bomff_allowed_tabs    = array( 'connection', 'permissions' );

if ( ! in_array( $bomff_active_tab, $bomff_allowed_tabs, true ) ) {
    $bomff_active_tab = 'connection';
}

$bomff_base_url      = admin_url( 'admin.php?page=bomff-settings' );
$bomff_firebase_help = 'https://firebase.google.com/docs/admin/setup#initialize_the_sdk_in_non-google_environments';
?>

<div class="wrap bomff-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php if ( '1' === sanitize_key( bomff_get_get_value( 'bomff_saved' ) ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Firebase connection saved successfully.', 'backoffice-manager-for-firebase' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( '1' === sanitize_key( bomff_get_get_value( 'bomff_deleted' ) ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Firebase connection removed.', 'backoffice-manager-for-firebase' ); ?></p>
        </div>
    <?php endif; ?>


    <?php $bomff_error = sanitize_text_field( bomff_get_get_value( 'bomff_error' ) ); ?>
    <?php if ( '' !== $bomff_error ) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $bomff_error ); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper bomff-mt-20" aria-label="<?php echo esc_attr__( 'Settings sections', 'backoffice-manager-for-firebase' ); ?>">
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'connection', $bomff_base_url ) ); ?>" class="nav-tab <?php echo esc_attr( 'connection' === $bomff_active_tab ? 'nav-tab-active' : '' ); ?>">
            <?php esc_html_e( 'Connection', 'backoffice-manager-for-firebase' ); ?>
        </a>

        <a href="<?php echo esc_url( add_query_arg( 'tab', 'permissions', $bomff_base_url ) ); ?>" class="nav-tab <?php echo esc_attr( 'permissions' === $bomff_active_tab ? 'nav-tab-active' : '' ); ?>">
            <?php esc_html_e( 'Permissions', 'backoffice-manager-for-firebase' ); ?>
        </a>
    </nav>

    <?php if ( 'connection' === $bomff_active_tab ) : ?>

        <div class="bomff-section bomff-mt-20">
            <h2><?php esc_html_e( 'Connect Firebase', 'backoffice-manager-for-firebase' ); ?></h2>

            <p>
                <?php esc_html_e( 'Upload the private key JSON file from your Firebase project to start managing Firestore from WordPress.', 'backoffice-manager-for-firebase' ); ?>
            </p>

            <p>
                <a href="<?php echo esc_url( $bomff_firebase_help ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Open the official Firebase guide to create the JSON key file', 'backoffice-manager-for-firebase' ); ?>
                </a>
            </p>

            <ol>
                <li><?php esc_html_e( 'Open your Firebase project settings.', 'backoffice-manager-for-firebase' ); ?></li>
                <li><?php esc_html_e( 'Go to the service accounts section and generate a new private key.', 'backoffice-manager-for-firebase' ); ?></li>
                <li><?php esc_html_e( 'Upload the downloaded JSON file below.', 'backoffice-manager-for-firebase' ); ?></li>
            </ol>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'bomff_save_service_account' ); ?>
                <input type="hidden" name="action" value="bomff_save_service_account" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="bomff_service_account_json">
                                <?php esc_html_e( 'Firebase JSON key file', 'backoffice-manager-for-firebase' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="file"
                                id="bomff_service_account_json"
                                name="bomff_service_account_json"
                                accept="application/json,.json"
                                required
                            />
                            <p class="description">
                                <?php esc_html_e( 'Keep this file private. The credentials are stored encrypted in WordPress after upload.', 'backoffice-manager-for-firebase' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save connection', 'backoffice-manager-for-firebase' ) ); ?>
            </form>
        </div>

        <div class="bomff-section bomff-mt-20">
            <h2><?php esc_html_e( 'Current connection', 'backoffice-manager-for-firebase' ); ?></h2>

            <?php if ( $bomff_service_account ) : ?>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Status', 'backoffice-manager-for-firebase' ); ?></strong></td>
                            <td>
                                <span class="bomff-badge bomff-badge--success">
                                    <?php esc_html_e( 'Connected', 'backoffice-manager-for-firebase' ); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Project ID', 'backoffice-manager-for-firebase' ); ?></strong></td>
                            <td><code><?php echo esc_html( $bomff_service_account['project_id'] ?? '' ); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Firebase account', 'backoffice-manager-for-firebase' ); ?></strong></td>
                            <td><code><?php echo esc_html( $bomff_service_account['client_email'] ?? '' ); ?></code></td>
                        </tr>
                    </tbody>
                </table>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bomff-mt-20">
                    <?php wp_nonce_field( 'bomff_delete_service_account' ); ?>
                    <input type="hidden" name="action" value="bomff_delete_service_account" />

                    <?php submit_button(
                        __( 'Remove connection', 'backoffice-manager-for-firebase' ),
                        'delete',
                        'submit',
                        false,
                        array(
                            'onclick' => "return confirm('Are you sure you want to remove the Firebase connection?');",
                        )
                    ); ?>
                </form>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'Firebase is not connected yet.', 'backoffice-manager-for-firebase' ); ?></p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-demo' ) ); ?>">
                            <?php esc_html_e( 'Try Demo Mode', 'backoffice-manager-for-firebase' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bomff-section bomff-mt-20">
            <h2><?php esc_html_e( 'Onboarding', 'backoffice-manager-for-firebase' ); ?></h2>
            <p>
                <?php esc_html_e( 'Reopen the welcome screen to choose between connecting Firebase and exploring Demo Mode again.', 'backoffice-manager-for-firebase' ); ?>
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'bomff_show_onboarding' ); ?>
                <input type="hidden" name="action" value="bomff_show_onboarding" />
                <?php submit_button( __( 'Show Welcome Screen Again', 'backoffice-manager-for-firebase' ), 'secondary', 'submit', false ); ?>
            </form>
        </div>

    <?php elseif ( 'permissions' === $bomff_active_tab ) : ?>

        <div class="bomff-section bomff-mt-20">
            <h2><?php esc_html_e( 'Permissions', 'backoffice-manager-for-firebase' ); ?></h2>

            <p>
                <?php esc_html_e( 'Only WordPress administrators can access and modify Firestore data in this version.', 'backoffice-manager-for-firebase' ); ?>
            </p>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Required capability', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td><code><?php echo esc_html( BOMFF_CAPABILITY ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Default WordPress role', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td><?php esc_html_e( 'Administrator', 'backoffice-manager-for-firebase' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Protection', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td><?php esc_html_e( 'WordPress capability checks and nonces are applied to admin actions.', 'backoffice-manager-for-firebase' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
