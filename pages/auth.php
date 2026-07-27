<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
    wp_die( esc_html__( 'You do not have permission.', 'backoffice-manager-for-firebase' ) );
}

$bomff_service_account = $bomff_view_data['service_account'] ?? null;
$bomff_view_uid        = $bomff_view_data['view_uid'] ?? '';
$bomff_search          = $bomff_view_data['search'] ?? '';
$bomff_page_token      = $bomff_view_data['page_token'] ?? '';
$bomff_base_url        = admin_url( 'admin.php?page=bomff-auth' );
$bomff_not_set         = __( 'Not set', 'backoffice-manager-for-firebase' );
?>

<div class="wrap bomff-wrap">
    <h1><?php esc_html_e( 'Authentication', 'backoffice-manager-for-firebase' ); ?></h1>

    <?php if ( isset( $_GET['bomff_auth_updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Firebase Authentication action completed.', 'backoffice-manager-for-firebase' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['bomff_auth_error'] ) ) : ?>
        <?php bomff_auth_render_error_notice( sanitize_text_field( wp_unslash( $_GET['bomff_auth_error'] ) ), bomff_auth_get_notice_error_data() ); ?>
    <?php endif; ?>

    <?php if ( ! $bomff_service_account ) : ?>
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e( 'Firebase Authentication is not available yet.', 'backoffice-manager-for-firebase' ); ?></strong></p>
            <p>
                <?php esc_html_e( 'Upload a Firebase service account JSON key in Settings. The key must have permissions to use Firebase Authentication / Identity Toolkit for this project.', 'backoffice-manager-for-firebase' ); ?>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'backoffice-manager-for-firebase' ); ?></a>
            </p>
        </div>
    <?php elseif ( $bomff_view_uid ) : ?>
        <?php $bomff_user = bomff_auth_get_user( $bomff_view_uid ); ?>
        <?php if ( is_wp_error( $bomff_user ) ) : ?>
            <?php bomff_auth_render_error_notice( $bomff_user->get_error_message(), $bomff_user->get_error_data() ); ?>
        <?php else : ?>
            <p><a class="button" href="<?php echo esc_url( $bomff_base_url ); ?>"><?php esc_html_e( '← Back to users', 'backoffice-manager-for-firebase' ); ?></a></p>
            <div class="bomff-section bomff-mt-20">
                <h2><?php esc_html_e( 'User details', 'backoffice-manager-for-firebase' ); ?></h2>
                <table class="widefat striped bomff-detail-table">
                    <tbody>
                        <tr><th><?php esc_html_e( 'UID', 'backoffice-manager-for-firebase' ); ?></th><td><code><?php echo esc_html( $bomff_user['uid'] ); ?></code></td></tr>
                        <tr><th><?php esc_html_e( 'Email', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $bomff_user['email'] ?: $bomff_not_set ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Display name', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $bomff_user['displayName'] ?: $bomff_not_set ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Phone number', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( $bomff_user['phoneNumber'] ?: $bomff_not_set ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Photo URL', 'backoffice-manager-for-firebase' ); ?></th><td><?php if ( $bomff_user['photoUrl'] ) : ?><a href="<?php echo esc_url( $bomff_user['photoUrl'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $bomff_user['photoUrl'] ); ?></a><?php else : echo esc_html( $bomff_not_set ); endif; ?></td></tr>
                        <tr><th><?php esc_html_e( 'Providers', 'backoffice-manager-for-firebase' ); ?></th><td><strong><?php echo esc_html( bomff_auth_provider_labels( $bomff_user ) ); ?></strong><?php if ( ! empty( $bomff_user['providerUserInfo'] ) ) : ?><details class="bomff-provider-details"><summary><?php esc_html_e( 'View original provider JSON', 'backoffice-manager-for-firebase' ); ?></summary><pre><?php echo esc_html( wp_json_encode( $bomff_user['providerUserInfo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></details><?php endif; ?></td></tr>
                        <tr><th><?php esc_html_e( 'Email verified', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo $bomff_user['emailVerified'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Disabled', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo $bomff_user['disabled'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Created date', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( bomff_auth_format_date( $bomff_user['createdAt'] ) ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Last sign-in date', 'backoffice-manager-for-firebase' ); ?></th><td><?php echo esc_html( bomff_auth_format_date( $bomff_user['lastLoginAt'] ) ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Custom claims', 'backoffice-manager-for-firebase' ); ?></th><td><?php if ( ! empty( $bomff_user['customClaims'] ) ) : ?><pre><?php echo esc_html( wp_json_encode( is_array( $bomff_user['customClaims'] ) ? $bomff_user['customClaims'] : array(), JSON_PRETTY_PRINT ) ); ?></pre><?php else : echo esc_html( $bomff_not_set ); endif; ?><p class="description"><?php esc_html_e( 'Custom claims are read-only in this version.', 'backoffice-manager-for-firebase' ); ?></p></td></tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <?php $bomff_result = bomff_auth_list_users( $bomff_page_token, 100 ); ?>
        <?php if ( is_wp_error( $bomff_result ) ) : ?>
            <?php bomff_auth_render_error_notice( $bomff_result->get_error_message(), $bomff_result->get_error_data() ); ?>
        <?php else : ?>
            <?php $bomff_users = bomff_auth_filter_users( $bomff_result['users'], $bomff_search ); ?>
            <form method="get" class="search-form bomff-auth-search-form">
                <input type="hidden" name="page" value="bomff-auth" />
                <p class="search-box">
                    <label class="screen-reader-text" for="bomff-auth-search"><?php esc_html_e( 'Search users', 'backoffice-manager-for-firebase' ); ?></label>
                    <input type="search" id="bomff-auth-search" name="s" value="<?php echo esc_attr( $bomff_search ); ?>" placeholder="<?php echo esc_attr__( 'Email, UID, or display name', 'backoffice-manager-for-firebase' ); ?>" />
                    <?php submit_button( __( 'Search users', 'backoffice-manager-for-firebase' ), '', '', false ); ?>
                </p>
            </form>

            <div class="bomff-table-scroll bomff-auth-list-table-wrap">
                <table class="wp-list-table widefat fixed striped table-view-list bomff-auth-table">
                    <thead><tr><th scope="col" class="column-primary"><?php esc_html_e( 'UID', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Email', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Display name', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Provider(s)', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Email verified', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Disabled', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Created date', 'backoffice-manager-for-firebase' ); ?></th><th scope="col"><?php esc_html_e( 'Last sign-in date', 'backoffice-manager-for-firebase' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( empty( $bomff_users ) ) : ?>
                        <tr><td colspan="8" class="bomff-center-muted"><?php echo '' !== $bomff_search ? esc_html__( 'No users matched your search.', 'backoffice-manager-for-firebase' ) : esc_html__( 'No Firebase Authentication users found.', 'backoffice-manager-for-firebase' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $bomff_users as $bomff_user ) : ?>
                            <tr>
                                <td class="column-primary" data-colname="<?php echo esc_attr__( 'UID', 'backoffice-manager-for-firebase' ); ?>"><code class="bomff-auth-uid"><?php echo esc_html( $bomff_user['uid'] ); ?></code><button type="button" class="button-link bomff-copy-uid" data-uid="<?php echo esc_attr( $bomff_user['uid'] ); ?>" aria-label="<?php echo esc_attr__( 'Copy UID', 'backoffice-manager-for-firebase' ); ?>" title="<?php echo esc_attr__( 'Copy UID', 'backoffice-manager-for-firebase' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Copy UID', 'backoffice-manager-for-firebase' ); ?></span></button><span class="bomff-copy-feedback" aria-live="polite"></span><button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'backoffice-manager-for-firebase' ); ?></span></button></td>
                                <td data-colname="<?php echo esc_attr__( 'Email', 'backoffice-manager-for-firebase' ); ?>"><?php echo esc_html( $bomff_user['email'] ?: '—' ); ?>
                                    <div class="row-actions">
                                        <span class="view"><a href="<?php echo esc_url( add_query_arg( 'uid', rawurlencode( $bomff_user['uid'] ), $bomff_base_url ) ); ?>"><?php esc_html_e( 'View details', 'backoffice-manager-for-firebase' ); ?></a> | </span>
                                        <span class="password-reset"><?php bomff_auth_action_form( 'password_reset', __( 'Reset password', 'backoffice-manager-for-firebase' ), $bomff_user, 'button-link', 'bomff-row-action-form' ); ?> | </span>
                                        <span class="email-verification"><?php bomff_auth_action_form( 'email_verification', __( 'Send verification', 'backoffice-manager-for-firebase' ), $bomff_user, 'button-link', 'bomff-row-action-form' ); ?> | </span>
                                        <span class="toggle-disabled"><?php bomff_auth_action_form( $bomff_user['disabled'] ? 'enable' : 'disable', $bomff_user['disabled'] ? __( 'Enable', 'backoffice-manager-for-firebase' ) : __( 'Disable', 'backoffice-manager-for-firebase' ), $bomff_user, 'button-link', 'bomff-row-action-form' ); ?> | </span>
                                        <span class="delete"><?php bomff_auth_action_form( 'delete', __( 'Delete', 'backoffice-manager-for-firebase' ), $bomff_user, 'button-link-delete', 'bomff-row-action-form' ); ?></span>
                                    </div>
                                </td>
                                <td data-colname="<?php echo esc_attr__( 'Display name', 'backoffice-manager-for-firebase' ); ?>"><?php echo esc_html( $bomff_user['displayName'] ?: '—' ); ?></td>
                                <td data-colname="<?php echo esc_attr__( 'Provider(s)', 'backoffice-manager-for-firebase' ); ?>"><?php echo esc_html( bomff_auth_provider_labels( $bomff_user ) ); ?></td>
                                <td data-colname="<?php echo esc_attr__( 'Email verified', 'backoffice-manager-for-firebase' ); ?>"><?php echo $bomff_user['emailVerified'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td>
                                <td data-colname="<?php echo esc_attr__( 'Disabled', 'backoffice-manager-for-firebase' ); ?>"><?php echo $bomff_user['disabled'] ? esc_html__( 'Yes', 'backoffice-manager-for-firebase' ) : esc_html__( 'No', 'backoffice-manager-for-firebase' ); ?></td>
                                <td data-colname="<?php echo esc_attr__( 'Created date', 'backoffice-manager-for-firebase' ); ?>"><?php echo esc_html( bomff_auth_format_date( $bomff_user['createdAt'] ) ); ?></td>
                                <td data-colname="<?php echo esc_attr__( 'Last sign-in date', 'backoffice-manager-for-firebase' ); ?>"><?php echo esc_html( bomff_auth_format_date( $bomff_user['lastLoginAt'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ( ! empty( $bomff_result['nextPageToken'] ) ) : ?>
                <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page_token' => rawurlencode( $bomff_result['nextPageToken'] ) ), $bomff_base_url ) ); ?>"><?php esc_html_e( 'Next page', 'backoffice-manager-for-firebase' ); ?></a></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
