<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( BOMFF_CAPABILITY ) ) {
    wp_die( esc_html__( 'You do not have permission.', 'backoffice-manager-for-firebase' ) );
}

$bomff_demo_mode = ! empty( $bomff_view_data['demo_mode'] );
?>

<div class="wrap bomff-wrap">
  <h1><?php echo esc_html( $bomff_demo_mode ? __( 'Firestore Demo Mode', 'backoffice-manager-for-firebase' ) : __( 'Firestore', 'backoffice-manager-for-firebase' ) ); ?></h1>

  <div id="firebase-admin-panel-app">

    <?php if ( $bomff_demo_mode ) : ?>
      <div class="notice notice-info bomff-demo-banner">
        <p>
          <strong><?php esc_html_e( 'Demo Mode: no real Firebase project is connected.', 'backoffice-manager-for-firebase' ); ?></strong>
          <?php esc_html_e( 'Explore mock Firestore-like data safely. Demo edits and deletes stay in this WordPress user account and never touch Firebase.', 'backoffice-manager-for-firebase' ); ?>
        </p>
      </div>
    <?php endif; ?>

    <div id="bomff-config-warning" class="notice notice-warning bomff-hidden">
      <p>
        <?php esc_html_e( 'Firebase is not configured yet.', 'backoffice-manager-for-firebase' ); ?>
        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-settings' ) ); ?>">
          <?php esc_html_e( 'Open Settings', 'backoffice-manager-for-firebase' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bomff-demo' ) ); ?>">
          <?php esc_html_e( 'Try Demo Mode', 'backoffice-manager-for-firebase' ); ?>
        </a>
      </p>
    </div>

    <span id="firebase-connection-status" class="bomff-hidden" aria-hidden="true"></span>

    <div class="bomff-section bomff-mt-20">
      <h2><?php esc_html_e( 'Guided creation', 'backoffice-manager-for-firebase' ); ?></h2>

      <div class="bomff-structure-box">
        <p class="bomff-mt-0">
          <?php
          echo wp_kses_post(
            __( 'A <strong>structure</strong> defines which fields are used to <strong>create</strong> new documents using a guided form.', 'backoffice-manager-for-firebase' )
          );
          ?>
        </p>

        <div class="bomff-controls">
          <button id="bomff-import-structure" class="button button-secondary" type="button">
            <?php esc_html_e( 'Import structure from collection', 'backoffice-manager-for-firebase' ); ?>
          </button>

          <button id="bomff-view-structure" class="button" type="button" disabled>
            <?php esc_html_e( 'View structure', 'backoffice-manager-for-firebase' ); ?>
          </button>

          <button id="bomff-create-doc" class="button button-primary" type="button" disabled>
            <?php esc_html_e( 'Create document', 'backoffice-manager-for-firebase' ); ?>
          </button>

          <button id="bomff-delete-structure" class="button" type="button" disabled>
            <?php esc_html_e( 'Delete structure', 'backoffice-manager-for-firebase' ); ?>
          </button>

          <?php do_action( 'bomff_firestore_guided_toolbar_end' ); ?>
        </div>

        <p id="bomff-structure-msg" class="bomff-msg-top"></p>
      </div>
    </div>

    <div class="bomff-section bomff-mt-20">
      <h2><?php esc_html_e( 'Explore a collection', 'backoffice-manager-for-firebase' ); ?></h2>

      <?php if ( $bomff_demo_mode ) : ?>
        <div class="bomff-demo-collections" aria-label="<?php echo esc_attr__( 'Demo collections', 'backoffice-manager-for-firebase' ); ?>">
          <span><?php esc_html_e( 'Demo collections:', 'backoffice-manager-for-firebase' ); ?></span>
          <button class="button bomff-demo-collection" type="button" data-collection="users"><?php esc_html_e( 'users', 'backoffice-manager-for-firebase' ); ?></button>
          <button class="button bomff-demo-collection" type="button" data-collection="orders"><?php esc_html_e( 'orders', 'backoffice-manager-for-firebase' ); ?></button>
          <button class="button bomff-demo-collection" type="button" data-collection="products"><?php esc_html_e( 'products', 'backoffice-manager-for-firebase' ); ?></button>
        </div>
      <?php endif; ?>

      <div class="bomff-controls">
        <input
          type="text"
          id="bomff-collection-name"
          placeholder="<?php echo esc_attr( $bomff_demo_mode ? __( 'e.g. users', 'backoffice-manager-for-firebase' ) : __( 'e.g. affiliates', 'backoffice-manager-for-firebase' ) ); ?>"
        />

        <select id="bomff-page-size">
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>

        <button id="bomff-load-collection" class="button button-primary" type="button">
          <?php esc_html_e( 'Load', 'backoffice-manager-for-firebase' ); ?>
        </button>

        <button id="bomff-clear-results" class="button" type="button">
          <?php esc_html_e( 'Clear', 'backoffice-manager-for-firebase' ); ?>
        </button>
      </div>

      <div class="bomff-controls-row">
        <input
          type="text"
          id="bomff-doc-id"
          placeholder="<?php echo esc_attr__( 'Doc ID', 'backoffice-manager-for-firebase' ); ?>"
        />

        <button id="bomff-load-doc" class="button" type="button">
          <?php esc_html_e( 'Load document', 'backoffice-manager-for-firebase' ); ?>
        </button>
      </div>

      <p id="bomff-collection-msg"></p>
    </div>

    <div class="bomff-section bomff-my-10">
      <button id="bomff-prev-page" class="button" type="button" disabled>
        <?php esc_html_e( '◀ Prev', 'backoffice-manager-for-firebase' ); ?>
      </button>

      <button id="bomff-next-page" class="button" type="button" disabled>
        <?php esc_html_e( 'Next ▶', 'backoffice-manager-for-firebase' ); ?>
      </button>
    </div>

    <div class="bomff-section">
      <div class="bomff-table-scroll">
        <table class="widefat striped" id="bomff-results-table">
          <thead>
            <tr id="bomff-results-head-row">
              <th><?php esc_html_e( 'Doc ID', 'backoffice-manager-for-firebase' ); ?></th>
              <th><?php esc_html_e( 'Fields', 'backoffice-manager-for-firebase' ); ?></th>
              <th class="bomff-col-actions"><?php esc_html_e( 'Actions', 'backoffice-manager-for-firebase' ); ?></th>
            </tr>
          </thead>

          <tbody id="bomff-collection-results">
            <tr>
              <td colspan="3" class="bomff-center-muted">
                <?php esc_html_e( 'Enter a collection and click “Load”.', 'backoffice-manager-for-firebase' ); ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
