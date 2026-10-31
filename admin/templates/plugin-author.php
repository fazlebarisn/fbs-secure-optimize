<?php
/**
 * Meet The Author & Studio Admin Template.
 *
 * @package FBS_Secure_Optimize
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

$fbsseop_author_img = FBS_SECURE_OPTIMIZE_PLUGIN_URL . 'assets/img/fazle-bari-profile.jpg';
?>
<div class="wrap fbsseop-author-wrap">
	<style>
		.fbsseop-author-wrap {
			max-width: 1240px;
			margin: 20px 20px 40px 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
			color: #0f172a;
		}
		.fbsseop-author-wrap * {
			box-sizing: border-box;
		}

		/* Top Studio Banner */
		.fbsseop-author-hero {
			background: linear-gradient(135deg, #090d16 0%, #1e1b4b 60%, #0f172a 100%);
			border-radius: 16px;
			padding: 28px 32px;
			color: #ffffff;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 24px;
			flex-wrap: wrap;
			margin-bottom: 24px;
			box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
			border: 1px solid rgba(255, 255, 255, 0.08);
		}
		.fbsseop-author-hero__eyebrow {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 4px 10px;
			border-radius: 999px;
			background: rgba(16, 185, 129, 0.16);
			border: 1px solid rgba(16, 185, 129, 0.38);
			color: #34d399;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.05em;
			text-transform: uppercase;
			margin-bottom: 10px;
		}
		.fbsseop-author-hero h1 {
			margin: 0;
			padding: 0;
			font-size: 25px;
			font-weight: 800;
			color: #ffffff;
			letter-spacing: -0.02em;
			line-height: 1.25;
		}
		.fbsseop-author-hero p {
			margin: 6px 0 0;
			font-size: 14px;
			color: #cbd5e1;
			max-width: 680px;
		}
		.fbsseop-author-hero__actions {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}
		.fbsseop-author-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 7px;
			padding: 10px 18px;
			border-radius: 9px;
			font-size: 13.5px;
			font-weight: 700;
			text-decoration: none;
			transition: all 0.18s ease;
			cursor: pointer;
		}
		.fbsseop-author-btn--emerald {
			background: #10b981;
			color: #052e16;
			border: 1px solid #10b981;
			box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
		}
		.fbsseop-author-btn--emerald:hover {
			background: #059669;
			border-color: #059669;
			color: #ffffff;
			transform: translateY(-1px);
		}
		.fbsseop-author-btn--glass {
			background: rgba(255, 255, 255, 0.1);
			color: #ffffff;
			border: 1px solid rgba(255, 255, 255, 0.2);
		}
		.fbsseop-author-btn--glass:hover {
			background: rgba(255, 255, 255, 0.18);
			color: #ffffff;
			transform: translateY(-1px);
		}
		.fbsseop-author-btn--indigo {
			background: #4f46e5;
			color: #ffffff;
			border: 1px solid #4f46e5;
		}
		.fbsseop-author-btn--indigo:hover {
			background: #4338ca;
			color: #ffffff;
			transform: translateY(-1px);
		}
		.fbsseop-author-btn--outline {
			background: #ffffff;
			color: #1e293b;
			border: 1px solid #cbd5e1;
		}
		.fbsseop-author-btn--outline:hover {
			border-color: #94a3b8;
			color: #0f172a;
			transform: translateY(-1px);
		}

		/* Main Grid */
		.fbsseop-author-grid {
			display: grid;
			grid-template-columns: 340px 1fr;
			gap: 24px;
			align-items: start;
		}

		/* Left Profile Sidebar */
		.fbsseop-author-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 16px;
			padding: 26px;
			box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
			text-align: center;
		}
		.fbsseop-author-photo-wrap {
			width: 100%;
			max-width: 268px;
			margin: 0 auto 18px;
			border-radius: 16px;
			overflow: hidden;
			background: #0b1218;
			box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
			border: 2px solid #e2e8f0;
		}
		.fbsseop-author-photo-wrap img {
			display: block;
			width: 100%;
			height: auto;
			aspect-ratio: 1 / 1;
			object-fit: cover;
		}
		.fbsseop-author-community-pill {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 4px 11px;
			border-radius: 999px;
			background: #ecfdf5;
			border: 1px solid #a7f3d0;
			color: #047857;
			font-size: 11.5px;
			font-weight: 700;
			margin-bottom: 10px;
		}
		.fbsseop-author-card h2 {
			margin: 0 0 4px;
			font-size: 22px;
			font-weight: 800;
			color: #0f172a;
			letter-spacing: -0.01em;
		}
		.fbsseop-author-role {
			margin: 0 0 20px;
			font-size: 14px;
			font-weight: 600;
			color: #4f46e5;
		}

		.fbsseop-author-meta {
			text-align: left;
			border-top: 1px solid #f1f5f9;
			border-bottom: 1px solid #f1f5f9;
			padding: 16px 0;
			margin-bottom: 20px;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.fbsseop-author-meta__item {
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 13px;
			color: #334155;
		}
		.fbsseop-author-meta__item .dashicons {
			color: #64748b;
			font-size: 17px;
			width: 17px;
			height: 17px;
			flex-shrink: 0;
		}
		.fbsseop-author-meta__item a {
			color: #334155;
			text-decoration: none;
			font-weight: 500;
		}
		.fbsseop-author-meta__item a:hover {
			color: #4f46e5;
		}

		/* Social Icons */
		.fbsseop-author-socials {
			display: flex;
			justify-content: center;
			gap: 10px;
		}
		.fbsseop-author-social {
			width: 40px;
			height: 40px;
			border-radius: 10px;
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			color: #475569;
			text-decoration: none;
			transition: all 0.18s ease;
		}
		.fbsseop-author-social:hover {
			background: #0f172a;
			border-color: #0f172a;
			color: #ffffff;
			transform: translateY(-2px);
		}
		.fbsseop-author-social svg {
			width: 18px;
			height: 18px;
			fill: currentColor;
		}

		/* Right Main Content */
		.fbsseop-author-main {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 16px;
			padding: 32px;
			box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
		}
		.fbsseop-author-stats {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 14px;
			margin-bottom: 28px;
		}
		.fbsseop-author-stat {
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 16px 18px;
		}
		.fbsseop-author-stat strong {
			display: block;
			font-size: 21px;
			font-weight: 800;
			color: #0f172a;
			line-height: 1.2;
		}
		.fbsseop-author-stat span {
			display: block;
			margin-top: 4px;
			font-size: 12px;
			font-weight: 600;
			color: #64748b;
		}

		.fbsseop-author-block {
			margin-bottom: 28px;
		}
		.fbsseop-author-block:last-child {
			margin-bottom: 0;
		}
		.fbsseop-author-block h3 {
			margin: 0 0 12px;
			padding-bottom: 10px;
			font-size: 17px;
			font-weight: 800;
			color: #0f172a;
			border-bottom: 1px solid #e2e8f0;
			position: relative;
		}
		.fbsseop-author-block h3::after {
			content: "";
			position: absolute;
			left: 0;
			bottom: -1px;
			width: 44px;
			height: 2px;
			background: #4f46e5;
		}
		.fbsseop-author-block p {
			margin: 0 0 12px;
			font-size: 14.5px;
			line-height: 1.72;
			color: #334155;
		}
		.fbsseop-author-block p:last-child {
			margin-bottom: 0;
		}

		.fbsseop-skill-pills {
			display: flex;
			flex-wrap: wrap;
			gap: 9px;
		}
		.fbsseop-skill-pill {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 7px 14px;
			border-radius: 999px;
			background: #f1f5f9;
			border: 1px solid #e2e8f0;
			font-size: 12.5px;
			font-weight: 600;
			color: #1e293b;
		}
		.fbsseop-skill-pill::before {
			content: "";
			width: 6px;
			height: 6px;
			border-radius: 50%;
			background: #10b981;
		}

		.fbsseop-author-cta-box {
			margin-top: 28px;
			background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
			border: 1px solid #c7d2fe;
			border-radius: 14px;
			padding: 22px 24px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 20px;
			flex-wrap: wrap;
		}
		.fbsseop-author-cta-box h4 {
			margin: 0 0 4px;
			font-size: 16px;
			font-weight: 800;
			color: #0f172a;
		}
		.fbsseop-author-cta-box p {
			margin: 0;
			font-size: 13.5px;
			color: #475569;
			max-width: 540px;
		}
		.fbsseop-author-cta-box__btns {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}

		@media (max-width: 960px) {
			.fbsseop-author-grid {
				grid-template-columns: 1fr;
			}
			.fbsseop-author-stats {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<!-- Top Hero Banner -->
	<div class="fbsseop-author-hero">
		<div>
			<span class="fbsseop-author-hero__eyebrow">
				<?php esc_html_e( 'Official Plugin Creator & Engineering Studio', 'fbs-secure-optimize' ); ?>
			</span>
			<h1><?php esc_html_e( 'Meet Fazle Bari & FBS Fusion', 'fbs-secure-optimize' ); ?></h1>
			<p>
				<?php esc_html_e( 'Senior WordPress, WooCommerce & Laravel engineering — building clean, modular plugins and sub-second, fortress-grade websites.', 'fbs-secure-optimize' ); ?>
			</p>
		</div>
		<div class="fbsseop-author-hero__actions">
			<a href="https://profiles.wordpress.org/fazlebari/" target="_blank" rel="noopener noreferrer" class="fbsseop-author-btn fbsseop-author-btn--glass">
				<span class="dashicons dashicons-wordpress-alt"></span>
				<?php esc_html_e( 'WP.org Profile ↗', 'fbs-secure-optimize' ); ?>
			</a>
			<a href="https://fbsfusion.com/" target="_blank" rel="noopener noreferrer" class="fbsseop-author-btn fbsseop-author-btn--emerald">
				<?php esc_html_e( 'Visit FBSFusion.com ↗', 'fbs-secure-optimize' ); ?>
			</a>
		</div>
	</div>

	<div class="fbsseop-author-grid">
		<!-- Left Author Profile Card -->
		<aside class="fbsseop-author-card">
			<div class="fbsseop-author-photo-wrap">
				<img
					src="<?php echo esc_url( $fbsseop_author_img ); ?>"
					alt="<?php esc_attr_e( 'Fazle Bari — Lead Architect at FBS Fusion', 'fbs-secure-optimize' ); ?>"
					width="268"
					height="268"
				>
			</div>

			<span class="fbsseop-author-community-pill">
				<?php esc_html_e( '● WordPress Community Contributor', 'fbs-secure-optimize' ); ?>
			</span>

			<h2><?php esc_html_e( 'Fazle Bari', 'fbs-secure-optimize' ); ?></h2>
			<p class="fbsseop-author-role"><?php esc_html_e( 'Lead Architect, FBS Fusion', 'fbs-secure-optimize' ); ?></p>

			<div class="fbsseop-author-meta">
				<div class="fbsseop-author-meta__item">
					<span class="dashicons dashicons-email-alt"></span>
					<a href="mailto:fazle@fbsfusion.com">fazle@fbsfusion.com</a>
				</div>
				<div class="fbsseop-author-meta__item">
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<a href="https://fbsfusion.com/" target="_blank" rel="noopener noreferrer">fbsfusion.com</a>
				</div>
				<div class="fbsseop-author-meta__item">
					<span class="dashicons dashicons-wordpress"></span>
					<a href="https://profiles.wordpress.org/fazlebari/" target="_blank" rel="noopener noreferrer">profiles.wordpress.org/fazlebari</a>
				</div>
				<div class="fbsseop-author-meta__item">
					<span class="dashicons dashicons-location"></span>
					<span><?php esc_html_e( 'Dhaka, Bangladesh (Global Remote)', 'fbs-secure-optimize' ); ?></span>
				</div>
			</div>

			<div class="fbsseop-author-socials">
				<a href="https://github.com/fazlebarisn" target="_blank" rel="noopener noreferrer" class="fbsseop-author-social" aria-label="GitHub" title="GitHub">
					<svg role="img" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
				</a>
				<a href="https://www.linkedin.com/in/fazle-bari/" target="_blank" rel="noopener noreferrer" class="fbsseop-author-social" aria-label="LinkedIn" title="LinkedIn">
					<svg role="img" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0z"/></svg>
				</a>
				<a href="https://profiles.wordpress.org/fazlebari/" target="_blank" rel="noopener noreferrer" class="fbsseop-author-social" aria-label="WordPress.org" title="WordPress.org Profile">
					<svg role="img" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zM1.807 12c0-1.48.318-2.885.886-4.156l4.885 13.385A10.203 10.203 0 011.807 12zm10.193 10.193c-1.001 0-1.968-.147-2.883-.417l3.06-8.895 3.136 8.591c.021.051.046.097.074.142a10.155 10.155 0 01-3.387.579zm1.404-14.99c.614-.032 1.167-.097 1.167-.097.55-.065.485-.872-.065-.84 0 0-1.652.13-2.717.13-1.002 0-2.685-.13-2.685-.13-.55-.032-.614.808-.065.84 0 0 .52.065 1.069.097l1.589 4.354-2.232 6.693-3.713-11.047c.615-.032 1.168-.097 1.168-.097.549-.065.484-.872-.065-.84 0 0-1.653.13-2.718.13-.191 0-.416-.005-.655-.013A10.184 10.184 0 0112 1.807c2.655 0 5.072 1.015 6.887 2.677-.044-.003-.086-.009-.131-.009-1.002 0-1.712.872-1.712 1.809 0 .84.485 1.551 1.002 2.391.388.679.84 1.551.84 2.811 0 .872-.335 1.883-.776 3.295l-1.018 3.4-3.688-10.978zm5.069 13.797l3.081-8.898c.576-1.44.768-2.592.768-3.616 0-.372-.024-.716-.068-1.038a10.168 10.168 0 01-.002 8.708 10.214 10.214 0 01-3.779 4.844z"/></svg>
				</a>
				<a href="https://www.facebook.com/fbsfusion" target="_blank" rel="noopener noreferrer" class="fbsseop-author-social" aria-label="Facebook" title="FBS Fusion on Facebook">
					<svg role="img" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
				</a>
			</div>
		</aside>

		<!-- Right Main Bio & Studio Card -->
		<div class="fbsseop-author-main">
			<div class="fbsseop-author-stats">
				<div class="fbsseop-author-stat">
					<strong>6+</strong>
					<span><?php esc_html_e( 'Official WP.org Plugins', 'fbs-secure-optimize' ); ?></span>
				</div>
				<div class="fbsseop-author-stat">
					<strong>95–100</strong>
					<span><?php esc_html_e( 'Lighthouse Performance Target', 'fbs-secure-optimize' ); ?></span>
				</div>
				<div class="fbsseop-author-stat">
					<strong>Grade A+</strong>
					<span><?php esc_html_e( 'HTTP Security & WAF Hardening', 'fbs-secure-optimize' ); ?></span>
				</div>
			</div>

			<div class="fbsseop-author-block">
				<h3><?php esc_html_e( 'About Fazle Bari', 'fbs-secure-optimize' ); ?></h3>
				<p>
					<?php esc_html_e( 'I am a Full-Stack WordPress, WooCommerce & Laravel Software Architect and active WordPress community contributor. Over the past five years, I have engineered custom high-concurrency WooCommerce plugins, Core Web Vitals optimization engines, and forensic malware defense tools strictly following WordPress Coding Standards (WPCS).', 'fbs-secure-optimize' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'As Lead Architect at FBS Fusion, I pair deep WordPress core internals with modern Laravel backend systems and AI APIs to help businesses run sub-second, secure, and scalable digital platforms.', 'fbs-secure-optimize' ); ?>
				</p>
			</div>

			<div class="fbsseop-author-block">
				<h3><?php esc_html_e( 'Core Engineering Specializations', 'fbs-secure-optimize' ); ?></h3>
				<div class="fbsseop-skill-pills">
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'WooCommerce Plugin Engineering', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'Core Web Vitals & Sub-Second LCP', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'Malware Forensics & WAF Security', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'Custom WordPress Hook Architecture', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'PHP 8.x & Laravel SaaS Backends', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'REST APIs, Webhooks & AI Integrations', 'fbs-secure-optimize' ); ?></span>
					<span class="fbsseop-skill-pill"><?php esc_html_e( 'MySQL & Autoload Optimization', 'fbs-secure-optimize' ); ?></span>
				</div>
			</div>

			<div class="fbsseop-author-block">
				<h3><?php esc_html_e( 'Our Engineering Promise', 'fbs-secure-optimize' ); ?></h3>
				<p>
					<?php
					echo wp_kses(
						__( 'Every FBS Fusion plugin is built around three non-negotiable principles: <strong>performance</strong>, <strong>security</strong>, and <strong>modular simplicity</strong>. Disabled modules register zero runtime hooks, database queries are lean and prepared, and our interfaces stay fast, clean, and transparent out of the box.', 'fbs-secure-optimize' ),
						array( 'strong' => array() )
					);
					?>
				</p>
			</div>

			<div class="fbsseop-author-cta-box">
				<div>
					<h4><?php esc_html_e( 'Need Custom WordPress, PageSpeed, or Malware Rescue?', 'fbs-secure-optimize' ); ?></h4>
					<p><?php esc_html_e( 'Work directly with Fazle Bari and the FBS Fusion engineering team for custom WooCommerce plugins, guaranteed Core Web Vitals optimization, or deep server cleanup.', 'fbs-secure-optimize' ); ?></p>
				</div>
				<div class="fbsseop-author-cta-box__btns">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fbs-secure-optimize-plugins' ) ); ?>" class="fbsseop-author-btn fbsseop-author-btn--outline">
						<?php esc_html_e( 'Explore Our Plugins', 'fbs-secure-optimize' ); ?>
					</a>
					<a href="https://fbsfusion.com/hire-us/" target="_blank" rel="noopener noreferrer" class="fbsseop-author-btn fbsseop-author-btn--indigo">
						<?php esc_html_e( 'Hire FBS Fusion ↗', 'fbs-secure-optimize' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
