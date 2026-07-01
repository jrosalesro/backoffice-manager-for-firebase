<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'backoffice-manager-for-firebase' ) );
}

// Project ID from saved settings
$firebase_options = get_option( 'bomff_firebase_config', array() );
$project_id       = isset( $firebase_options['projectId'] ) ? trim( (string) $firebase_options['projectId'] ) : '';

// Official links (source of truth)
$firestore_usage_url   = $project_id ? "https://console.firebase.google.com/project/{$project_id}/firestore/usage" : '';
$storage_usage_url     = $project_id ? "https://console.firebase.google.com/project/{$project_id}/storage/usage" : '';
$project_overview_url  = $project_id ? "https://console.firebase.google.com/project/{$project_id}/overview" : '';
$gcp_budgets_url       = $project_id ? "https://console.cloud.google.com/billing/budgets?project={$project_id}" : '';

// Generic docs / pricing
$firebase_pricing_url  = 'https://firebase.google.com/pricing';
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Firebase Integration — Home', 'backoffice-manager-for-firebase' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'System status, quick links, and official usage & billing pages (Spark/Blaze).', 'backoffice-manager-for-firebase' ); ?>
    </p>

    <div id="firebase-admin-panel-app">

        <!-- Status -->
        <h2><?php esc_html_e( 'Status', 'backoffice-manager-for-firebase' ); ?></h2>
        <div id="bomff-status" class="bomff-section">
            <p>
                <?php esc_html_e( 'Firebase status:', 'backoffice-manager-for-firebase' ); ?>
                <span id="firebase-connection-status"><?php esc_html_e( 'Loading…', 'backoffice-manager-for-firebase' ); ?></span>
            </p>
            <p>
                <?php esc_html_e( 'Authentication status:', 'backoffice-manager-for-firebase' ); ?>
                <span id="firebase-auth-status"><?php esc_html_e( 'Checking…', 'backoffice-manager-for-firebase' ); ?></span>
            </p>
        </div>


        <!-- Auth -->
        <h2><?php esc_html_e( 'Sign in (Firebase Authentication)', 'backoffice-manager-for-firebase' ); ?></h2>
        <div class="bomff-section" id="bomff-auth-box">
            <div id="bomff-auth-when-signed-out">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bomff-auth-email"><?php esc_html_e( 'Email', 'backoffice-manager-for-firebase' ); ?></label></th>
                        <td><input type="email" id="bomff-auth-email" class="regular-text" placeholder="demo@firebase.test" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bomff-auth-password"><?php esc_html_e( 'Password', 'backoffice-manager-for-firebase' ); ?></label></th>
                        <td><input type="password" id="bomff-auth-password" class="regular-text" /></td>
                    </tr>
                </table>

                <p>
                    <button type="button" class="button button-primary" id="bomff-auth-login">
                        <?php esc_html_e( 'Sign in', 'backoffice-manager-for-firebase' ); ?>
                    </button>
                    <span id="bomff-auth-msg" class="description" style="margin-left:10px;"></span>
                </p>
            </div>

            <div id="bomff-auth-when-signed-in" style="display:none;">
                <p>
                    <button type="button" class="button" id="bomff-auth-logout">
                        <?php esc_html_e( 'Sign out', 'backoffice-manager-for-firebase' ); ?>
                    </button>
                    <span id="bomff-auth-msg-in" class="description" style="margin-left:10px;"></span>
                </p>
            </div>
        </div>

        <!-- Usage / billing -->
        <h2><?php esc_html_e( 'Usage & billing (Spark / Blaze)', 'backoffice-manager-for-firebase' ); ?></h2>
        <div class="bomff-section">

            <?php if ( empty( $project_id ) ) : ?>
                <p style="color:#b32d2e; font-weight:bold;">
                    <?php
                    echo wp_kses_post(
                        __( '⚠️ No <code>projectId</code> configured. Go to <strong>Settings</strong> and save your Firebase configuration.', 'backoffice-manager-for-firebase' )
                    );
                    ?>
                </p>
            <?php else : ?>
                <p>
                    <?php
                    echo wp_kses_post(
                        __( 'This plugin <strong>does not invent</strong> usage metrics. To see real reads/writes/storage, use the official Firebase Console for your project:', 'backoffice-manager-for-firebase' )
                    );
                    ?>
                    <code><?php echo esc_html( $project_id ); ?></code>
                </p>

                <p>
                    <a class="button button-primary" href="<?php echo esc_url( $firestore_usage_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'View Firestore usage (reads/writes/deletes)', 'backoffice-manager-for-firebase' ); ?>
                    </a>

                    <a class="button" href="<?php echo esc_url( $storage_usage_url ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">
                        <?php esc_html_e( 'View Storage usage', 'backoffice-manager-for-firebase' ); ?>
                    </a>

                    <a class="button" href="<?php echo esc_url( $project_overview_url ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">
                        <?php esc_html_e( 'Project overview', 'backoffice-manager-for-firebase' ); ?>
                    </a>
                </p>

                <hr style="margin: 16px 0;" />

                <p style="margin:0 0 8px 0;">
                    <?php
                    echo wp_kses_post(
                        __( '<strong>Quick note (Spark):</strong> if you exceed the free tier, you may not be charged immediately; sometimes you are limited instead.', 'backoffice-manager-for-firebase' )
                    );
                    ?>
                </p>

                <p style="margin:0 0 8px 0;">
                    <?php esc_html_e( 'What you should keep an eye on:', 'backoffice-manager-for-firebase' ); ?>
                </p>

                <ul style="margin: 0 0 10px 20px; list-style: disc;">
                    <li><?php esc_html_e( 'Reads / Writes / Deletes (Firestore)', 'backoffice-manager-for-firebase' ); ?></li>
                    <li><?php esc_html_e( 'Storage (Firestore and Cloud Storage)', 'backoffice-manager-for-firebase' ); ?></li>
                    <li><?php esc_html_e( 'Bandwidth / egress (especially if you serve large files)', 'backoffice-manager-for-firebase' ); ?></li>
                </ul>

                <p style="margin:0 0 10px 0;">
                    <a href="<?php echo esc_url( $firebase_pricing_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'See official Firebase limits and pricing', 'backoffice-manager-for-firebase' ); ?>
                    </a>
                </p>

                <p style="margin:0;">
                    <?php
                    echo wp_kses_post(
                        __( '<strong>If you ever move to Blaze:</strong> enable budgets/alerts to avoid surprises.', 'backoffice-manager-for-firebase' )
                    );
                    ?>
                    <?php if ( $gcp_budgets_url ) : ?>
                        <a href="<?php echo esc_url( $gcp_budgets_url ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:6px;">
                            <?php esc_html_e( 'Open Budgets in Google Cloud', 'backoffice-manager-for-firebase' ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

        </div>

        <!-- Internal quick links -->
        <h2><?php esc_html_e( 'Quick links', 'backoffice-manager-for-firebase' ); ?></h2>
        <div class="bomff-section">
            <p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-firestore' ) ); ?>">
                    <?php esc_html_e( 'Open Firestore', 'backoffice-manager-for-firebase' ); ?>
                </a>

                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-settings' ) ); ?>" style="margin-left:8px;">
                    <?php esc_html_e( 'Settings', 'backoffice-manager-for-firebase' ); ?>
                </a>
            </p>

            <p style="color:#666; margin-top:10px;">
                <?php esc_html_e( 'Tip: avoid large Firebase admin loads (full collections / continuous listeners) if you want to minimize reads.', 'backoffice-manager-for-firebase' ); ?>
            </p>
        </div>

    </div>
</div>
