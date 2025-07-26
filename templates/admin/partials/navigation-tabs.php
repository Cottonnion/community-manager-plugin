<?php
/**
 * Navigation tabs partial template
 *
 * @var string $current_tab Current tab
 * @var array $valid_tabs Valid tabs
 * @var OrganizationAccessDataHandler $data_handler Data handler instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<nav class="nav-tab-wrapper">
	<?php foreach ( $valid_tabs as $tab ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', $tab ) ); ?>" 
		   class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html( $data_handler->get_tab_label( $tab ) ); ?>
			<?php if ( $tab !== 'all' ) : ?>
				<span class="count">(<?php echo count( $data_handler->get_requests_by_tab( $tab ) ); ?>)</span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
