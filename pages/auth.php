<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
    wp_die( esc_html__( 'You do not have permission.', 'backoffice-manager-for-firebase' ) );
}

$service_account = bomff_get_service_account();
$view_uid        = isset( $_GET['uid'] ) ? sanitize_text_field( wp_unslash( $_GET['uid'] ) ) : '';
$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$page_token      = isset( $_GET['page_token'] ) ? sanitize_text_field( wp_unslash( $_GET['page_token'] ) ) : '';
$base_url        = admin_url( 'admin.php?page=bomff-auth' );

function bomff_auth_action_form( $action, $label, $user, $class = 'button-link' ) {
    $confirm = 'delete' === $action ? "return confirm('Are you sure you want to permanently delete this Firebase Auth user?');" : '';
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bomff-inline-form" onsubmit="<?php echo esc_attr( $confirm ); ?>">
        <?php wp_nonce_field( 'bomff_auth_action_' . $action . '_' . $user['uid'] ); ?>
        <input type="hidden" name="action" value="bomff_auth_action" />
        <input type="hidden" name="bomff_auth_action" value="<?php echo esc_attr( $action ); ?>" />
        <input type="hidden" name="uid" value="<?php echo esc_attr( $user['uid'] ); ?>" />
        <input type="hidden" name="email" value="<?php echo esc_attr( $user['email'] ); ?>" />
        <button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
    </form>
    <?php
}
?>

<div class="wrap bomff-wrap">
    <h1><?php esc_html_e( 'Authentication', 'backoffice-manager-for-firebase' ); ?></h1>

    <?php if ( isset( $_GET['bomff_auth_updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Firebase Authentication action completed.', 'backoffice-manager-for-firebase' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['bomff_auth_error'] ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bomff_auth_error'] ) ) ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! $service_account ) : ?>
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e( 'Firebase Authentication is not available yet.', 'backoffice-manager-for-firebase' ); ?></strong></p>
            <p>
                <?php esc_html_e( 'Upload a Firebase service account JSON key in Settings. The key must have permissions to use Firebase Authentication / Identity Toolkit for this project.', 'backoffice-manager-for-firebase' ); ?>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'backoffice-manager-for-firebase' ); ?></a>
            </p>
        </div>
    <?php elseif ( $view_uid ) : ?>
        <?php $user = bomff_auth_get_user( $view_uid ); ?>
        <?php if ( is_wp_error( $user ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $user->get_error_message() ); ?></p></div>
        <?php else : ?>
            <p><a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( '← Back to users', 'backoffice-manager-for-firebase' ); ?></a></p>
            <div class="bomff-section bomff-mt-20">
                <h2><?php esc_html_e( 'User details', 'backoffice-manager-for-firebase' ); ?></h2>
                <table class="widefat striped bomff-detail-table">
                    <tbody>
                        <tr><th><?php esc_html_e( 'UID', 'backoffice-manager-for-firebase' ); ?></th><td><code><?php echo esc_html( $user['uid'] ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Email', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $user['email'] ?: '—' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Display name', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $user['displayName'] ?: '—' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Phone number', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $user['phoneNumber'] ?: '—' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Photo URL', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo $user['photoUrl'] ? '<a href="' . esc_url( $user['photoUrl'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $user['photoUrl'] ) . '</a>' : '—'; ?></td></tr>
                        <tr><th><?php esc_html_e( 'Provider data', 'backoffice-manager-for-firebase' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( $user['providerUserInfo'], JSON_PRETTY_PRINT ) ); ?></pre></td></tr>
                        <tr><th><?php esc_html_e( 'Email verified', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo $user['emailVerified'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Disabled', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo $user['disabled'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Created date', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo bomff_auth_format_date( $user['createdAt'] ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Last sign-in date', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo bomff_auth_format_date( $user['lastLoginAt'] ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Custom claims', 'backoffice-manager-for-firebase' ); ?></th><td><pre><?php echo esc_html( wp_json_encode( is_array( $user['customClaims'] ) ? $user['customClaims'] : array(), JSON_PRETTY_PRINT ) ); ?></pre><p class="description"><?php esc_html_e( 'Custom claims are read-only in this MVP.', 'backoffice-manager-for-firebase' ); ?></p></td></tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <?php $result = bomff_auth_list_users( $page_token, 100 ); ?>
        <?php if ( is_wp_error( $result ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
        <?php else : ?>
            <?php $users = bomff_auth_filter_users( $result['users'], $search ); ?>
            <form method="get" class="search-form bomff-mt-20">
                <input type="hidden" name="page" value="bomff-auth" />
                <p class="search-box">
                    <label class="screen-reader-text" for="bomff-auth-search"><?php esc_html_e( 'Search users', 'backoffice-manager-for-firebase' ); ?></label>
                    <input type="search" id="bomff-auth-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Email, UID, or display name', 'backoffice-manager-for-firebase' ); ?>" />
                    <?php submit_button( __( 'Search users', 'backoffice-manager-for-firebase' ), '', '', false ); ?>
                </p>
            </form>

            <div class="bomff-table-scroll bomff-mt-20">
                <table class="widefat striped bomff-auth-table">
                    <thead><tr><th><?php esc_html_e( 'UID', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Email', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Display name', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Provider(s)', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Email verified', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Disabled', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Created date', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Last sign-in date', 'backoffice-manager-for-firebase' ); ?></th><th><?php esc_html_e( 'Actions', 'backoffice-manager-for-firebase' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( empty( $users ) ) : ?>
                        <tr><td colspan="9" class="bomff-center-muted"><?php esc_html_e( 'No Firebase Auth users found.', 'backoffice-manager-for-firebase' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $users as $user ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $user['uid'] ); ?></code></td>
                                <td><?php echo esc_html( $user['email'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( $user['displayName'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( bomff_auth_provider_labels( $user ) ); ?></td>
                                <td><?php echo $user['emailVerified'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td>
                                <td><?php echo $user['disabled'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td>
                                <td><?php echo bomff_auth_format_date( $user['createdAt'] ); ?></td>
                                <td><?php echo bomff_auth_format_date( $user['lastLoginAt'] ); ?></td>
                                <td class="bomff-auth-actions">
                                    <a href="<?php echo esc_url( add_query_arg( 'uid', rawurlencode( $user['uid'] ), $base_url ) ); ?>"><?php esc_html_e( 'View details', 'backoffice-manager-for-firebase' ); ?></a>
                                    <?php bomff_auth_action_form( 'password_reset', __( 'Send password reset email', 'backoffice-manager-for-firebase' ), $user ); ?>
                                    <?php bomff_auth_action_form( 'email_verification', __( 'Send email verification email', 'backoffice-manager-for-firebase' ), $user ); ?>
                                    <?php bomff_auth_action_form( $user['disabled'] ? 'enable' : 'disable', $user['disabled'] ? __( 'Enable user', 'backoffice-manager-for-firebase' ) : __( 'Disable user', 'backoffice-manager-for-firebase' ), $user ); ?>
                                    <?php bomff_auth_action_form( 'delete', __( 'Delete user', 'backoffice-manager-for-firebase' ), $user, 'button-link-delete' ); ?>
                                    <button type="button" class="button-link bomff-copy-uid" data-uid="<?php echo esc_attr( $user['uid'] ); ?>"><?php esc_html_e( 'Copy UID', 'backoffice-manager-for-firebase' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ( ! empty( $result['nextPageToken'] ) ) : ?>
                <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page_token' => rawurlencode( $result['nextPageToken'] ) ), $base_url ) ); ?>"><?php esc_html_e( 'Next page', 'backoffice-manager-for-firebase' ); ?></a></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
