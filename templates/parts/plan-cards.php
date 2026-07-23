<?php
/**
 * Plan cards grid (`.plans`), used by the Pricing and Toolbox pages.
 *
 * Ported from the PlanCards component in components/Plans.tsx. The source swaps
 * the displayed price between monthly and annual via React state shared with a
 * billing toggle; that toggle is a Plan 3 interactive widget, so here each
 * card's price shows the monthly figure and carries `data-price-m` /
 * `data-price-a` (plus matching sub-labels) for Plan 3 to swap client-side. The
 * button class is derived from the plan's `feat` flag (dark for the featured
 * plan, outline otherwise), replacing the source's raw `btn` class string that
 * was deliberately dropped from the content data.
 *
 * The wrapper's negative top margin pulls the cards up to overlap the preceding
 * `.pb-tall` hero, matching the source.
 *
 * $vars:
 * - plans (array, required) List of plans, each array(
 *     name, desc, priceM (int), priceA (int), feat (bool), pop (bool),
 *     features (string[]) ).
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blueworx_pc_plans = isset( $plans ) && is_array( $plans ) ? $plans : array();

// The feature check glyph, ported verbatim from components/Plans.tsx.
$blueworx_pc_check = '<svg class="ck" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14.4l-4.2-4.2 1.5-1.5 2.7 2.7 5-5 1.5 1.5z"/></svg>';
?>
<div class="plan-cards-wrap" style="margin:-190px var(--gut) 0;position:relative;z-index:3">
	<div class="plans">
		<?php foreach ( $blueworx_pc_plans as $blueworx_pc_plan ) : ?>
			<?php
			$blueworx_pc_feat = ! empty( $blueworx_pc_plan['feat'] );
			$blueworx_pc_pop  = ! empty( $blueworx_pc_plan['pop'] );
			$blueworx_pc_btn  = $blueworx_pc_feat ? 'plan-btn dark' : 'plan-btn out';
			?>
			<div class="<?php echo $blueworx_pc_feat ? 'plan-card feat' : 'plan-card'; ?>">
				<div class="plan-top">
					<div class="plan-name">
						<span><?php echo esc_html( $blueworx_pc_plan['name'] ); ?></span>
						<?php if ( $blueworx_pc_pop ) : ?>
							<span class="pop"><?php esc_html_e( 'Popular', 'blueworx-labs-wordpress' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="plan-desc"><?php echo esc_html( $blueworx_pc_plan['desc'] ); ?></div>
					<div class="plan-price" data-price-m="<?php echo esc_attr( (string) $blueworx_pc_plan['priceM'] ); ?>" data-price-a="<?php echo esc_attr( (string) $blueworx_pc_plan['priceA'] ); ?>">
						<b>$<?php echo esc_html( (string) $blueworx_pc_plan['priceM'] ); ?></b>
						<em data-sub-m="<?php esc_attr_e( 'per month', 'blueworx-labs-wordpress' ); ?>" data-sub-a="<?php esc_attr_e( 'per month, billed yearly', 'blueworx-labs-wordpress' ); ?>"><?php esc_html_e( 'per month', 'blueworx-labs-wordpress' ); ?></em>
					</div>
					<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="<?php echo esc_attr( $blueworx_pc_btn ); ?>" style="display:flex;align-items:center;justify-content:center;text-decoration:none"><?php esc_html_e( 'Get started', 'blueworx-labs-wordpress' ); ?></a>
				</div>
				<div class="plan-feats">
					<div class="lbl"><?php esc_html_e( 'FEATURES', 'blueworx-labs-wordpress' ); ?></div>
					<?php foreach ( (array) $blueworx_pc_plan['features'] as $blueworx_pc_feature ) : ?>
						<div class="pf">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted check glyph.
							echo $blueworx_pc_check;
							?>
							<?php echo esc_html( $blueworx_pc_feature ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
