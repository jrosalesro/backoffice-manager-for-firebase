<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
    return;
}

$service_account = bomff_get_service_account();
?>

<div class="wrap bomff-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php if ( isset( $_GET['bomff_saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Firebase Service Account configured successfully.', 'backoffice-manager-for-firebase' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['bomff_deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Firebase credentials removed.', 'backoffice-manager-for-firebase' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['bomff_error'] ) ) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bomff_error'] ) ) ); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info">
        <p>
            <?php esc_html_e( 'This plugin now uses a Firebase Service Account for secure server-side access. End users no longer need to log into Firebase from WordPress.', 'backoffice-manager-for-firebase' ); ?>
        </p>
    </div>

    <div class="bomff-section">
        <h2><?php esc_html_e( 'Quick setup', 'backoffice-manager-for-firebase' ); ?></h2>

        <ol>
            <li><?php esc_html_e( 'Open Firebase Console → Project Settings → Service Accounts.', 'backoffice-manager-for-firebase' ); ?></li>
            <li><?php esc_html_e( 'Click “Generate new private key”.', 'backoffice-manager-for-firebase' ); ?></li>
            <li><?php esc_html_e( 'Upload the downloaded JSON file below.', 'backoffice-manager-for-firebase' ); ?></li>
        </ol>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field( 'bomff_save_service_account' ); ?>
            <input type="hidden" name="action" value="bomff_save_service_account" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="bomff_service_account_json">
                            <?php esc_html_e( 'Service Account JSON', 'backoffice-manager-for-firebase' ); ?>
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
                            <?php esc_html_e( 'The file is encrypted before being stored in WordPress.', 'backoffice-manager-for-firebase' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Firebase credentials', 'backoffice-manager-for-firebase' ) ); ?>
        </form>
    </div>

    <div class="bomff-section bomff-mt-20">
        <h2><?php esc_html_e( 'Current status', 'backoffice-manager-for-firebase' ); ?></h2>

        <?php if ( $service_account ) : ?>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Project ID', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td><code><?php echo esc_html( $service_account['project_id'] ?? '' ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Client email', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td><code><?php echo esc_html( $service_account['client_email'] ?? '' ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Storage mode', 'backoffice-manager-for-firebase' ); ?></strong></td>
                        <td>
                            <?php if ( defined( 'BOMFF_FIREBASE_SERVICE_ACCOUNT_PATH' ) ) : ?>
                                <span class="bomff-badge bomff-badge--success">
                                    <?php esc_html_e( 'wp-config.php / external file', 'backoffice-manager-for-firebase' ); ?>
                                </span>
                            <?php else : ?>
                                <span class="bomff-badge">
                                    <?php esc_html_e( 'Encrypted WordPress database', 'backoffice-manager-for-firebase' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ( ! defined( 'BOMFF_FIREBASE_SERVICE_ACCOUNT_PATH' ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bomff-mt-20">
                    <?php wp_nonce_field( 'bomff_delete_service_account' ); ?>
                    <input type="hidden" name="action" value="bomff_delete_service_account" />

                    <?php submit_button(
                        __( 'Remove credentials', 'backoffice-manager-for-firebase' ),
                        'delete',
                        'submit',
                        false,
                        array(
                            'onclick' => "return confirm('Are you sure you want to remove the Firebase credentials?');",
                        )
                    ); ?>
                </form>
            <?php endif; ?>

        <?php else : ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e( 'Firebase is not configured yet.', 'backoffice-manager-for-firebase' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bomff-section bomff-mt-20">
        <h2><?php esc_html_e( 'Advanced / production setup', 'backoffice-manager-for-firebase' ); ?></h2>

        <p>
            <?php esc_html_e( 'For production environments, you can store the Service Account outside the database and define the path in wp-config.php.', 'backoffice-manager-for-firebase' ); ?>
        </p>

<pre><code>define(
    'BOMFF_FIREBASE_SERVICE_ACCOUNT_PATH',
    '/secure/path/firebase-service-account.json'
);</code></pre>
    </div>
</div>