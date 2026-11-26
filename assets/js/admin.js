/**
 * Admin Interactive Controller for FBS Secure Optimize v2.1.0
 */
(function ($) {
	'use strict';

	function showToast(message, isError) {
		var $toast = $('#fbsseop-ajax-toast');
		if (!$toast.length) {
			return;
		}
		$toast
			.removeClass('fbsseop-toast--error')
			.toggleClass('fbsseop-toast--error', !!isError)
			.html('<span>' + $('<div>').text(message).html() + '</span>')
			.stop(true, true)
			.fadeIn(180);

		setTimeout(function () {
			$toast.fadeOut(300);
		}, 4500);
	}

	function escHtml(str) {
		return $('<div>').text(str == null ? '' : String(str)).html();
	}

	function syncModuleVisibility(modulesMap) {
		var showAssetOpt = !!modulesMap.asset_optimizer;
		var showPerf = !!(modulesMap.asset_optimizer || modulesMap.database_cleanup);
		var showSec = !!(modulesMap.security_headers || modulesMap.login_security || modulesMap.wp_hardening);
		var showScan = !!modulesMap.malware_scanner;

		var $perfTab = $('.fbsseop-tab[data-tab="performance"]');
		var $secTab = $('.fbsseop-tab[data-tab="security"]');
		var $scanTab = $('.fbsseop-tab[data-tab="scanner"]');
		var $heroPurgeBtn = $('#fbsseop-purge-cache-btn');
		var $adminBarPurge = $('#wp-admin-bar-fbsseop-toolbar');
		var $wpSubmenuPerf = $('#toplevel_page_fbs-secure-optimize .wp-submenu a[href*="performance"]').closest('li');
		var $wpSubmenuSec = $('#toplevel_page_fbs-secure-optimize .wp-submenu a[href*="security"]').closest('li');
		var $heroScanBtn = $('#fbsseop-hero-scan-btn');
		var $wpSubmenuScan = $('#toplevel_page_fbs-secure-optimize .wp-submenu a[href*="scanner"]').closest('li');

		if (showAssetOpt) {
			$heroPurgeBtn.fadeIn(150);
			$adminBarPurge.show();
		} else {
			$heroPurgeBtn.fadeOut(150);
			$adminBarPurge.hide();
		}

		if (showPerf) {
			$perfTab.fadeIn(150);
			$wpSubmenuPerf.show();
		} else {
			$perfTab.fadeOut(150);
			$wpSubmenuPerf.hide();
		}

		if (showSec) {
			$secTab.fadeIn(150);
			$wpSubmenuSec.show();
		} else {
			$secTab.fadeOut(150);
			$wpSubmenuSec.hide();
		}

		if (showScan) {
			$scanTab.fadeIn(150);
			$heroScanBtn.fadeIn(150);
			$wpSubmenuScan.show();
		} else {
			$scanTab.fadeOut(150);
			$heroScanBtn.fadeOut(150);
			$wpSubmenuScan.hide();
		}
	}

	function collectCurrentModuleStates() {
		var states = {};
		$('.fbsseop-ajax-module-toggle').each(function () {
			states[$(this).data('module')] = $(this).is(':checked') ? 1 : 0;
		});
		return states;
	}

	// 1. Instant Independent Module Toggle via AJAX + Live Auto-Hide
	$(document).on('change', '.fbsseop-ajax-module-toggle', function () {
		var $checkbox = $(this);
		var moduleKey = $checkbox.data('module');
		var isEnabled = $checkbox.is(':checked') ? 1 : 0;
		var $card = $checkbox.closest('.fbsseop-module-card');

		$card.toggleClass('is-enabled', !!isEnabled).toggleClass('is-disabled', !isEnabled);
		$card.find('.fbsseop-status-text').text(isEnabled ? '● Active' : '○ Disabled');

		if (isEnabled) {
			$card.find('.fbsseop-configure-link').fadeIn(140);
		} else {
			$card.find('.fbsseop-configure-link').fadeOut(140);
		}

		syncModuleVisibility(collectCurrentModuleStates());

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_toggle_module',
			nonce: fbsseopAdmin.nonce,
			module: moduleKey,
			enabled: isEnabled
		}).done(function (res) {
			if (res && res.success) {
				$('#fbsseop-module-counter').html(
					'<span class="fbsseop-dot"></span> ' +
					escHtml(res.data.active_count + ' / ' + res.data.total_count + ' Modules Active')
				);
				if (res.data.modules) {
					syncModuleVisibility(res.data.modules);
				}
				showToast(res.data.message, false);
			} else {
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});

	// 1b. Live Auto-Hide for Pro Independent Module Toggles
	$(document).on('change', '.fbsseop-pro-module-toggle', function () {
		var $checkbox = $(this);
		var proKey = $checkbox.data('pro-module');
		var isEnabled = $checkbox.is(':checked');
		var $card = $checkbox.closest('.fbsseop-module-card');

		$card.toggleClass('is-enabled', isEnabled).toggleClass('is-disabled', !isEnabled);

		var $sections = $('.fbsseop-pro-section[data-pro-section="' + proKey + '"]');
		if (isEnabled) {
			$sections.stop(true, true).slideDown(160);
		} else {
			$sections.stop(true, true).slideUp(160);
		}

		var wafOn = $('.fbsseop-pro-module-toggle[data-pro-module="pro_waf"]').is(':checked');
		var rateOn = $('.fbsseop-pro-module-toggle[data-pro-module="pro_ratelimit"]').is(':checked');
		if (wafOn || rateOn) {
			$('#fbsseop-pro-left-panel').stop(true, true).fadeIn(160);
		} else {
			$('#fbsseop-pro-left-panel').stop(true, true).fadeOut(160);
		}
	});

	// 1c. 1-Click Universal Cache Purge (Hero Header Button)
	$('#fbsseop-purge-cache-btn').on('click', function () {
		var $btn = $(this);
		var origHtml = $btn.html();
		$btn.prop('disabled', true).find('span:last').text(fbsseopAdmin.strings.purging || 'Purging cache...');

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_purge_cache',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (res && res.success) {
				showToast(res.data.message, false);
			} else {
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			showToast(fbsseopAdmin.strings.genericError, true);
		}).always(function () {
			$btn.prop('disabled', false).html(origHtml);
		});
	});

	// 1d. 1-Click Apply Max PageSpeed / Core Web Vitals Preset
	$('#fbsseop-apply-cwv-preset').on('click', function () {
		var recommended = [
			'preload_lcp_image',
			'cls_stabilize_builders',
			'cls_image_dimensions',
			'deblock_css_imports',
			'non_blocking_google_fonts',
			'async_icon_css',
			'inline_small_css',
			'unload_unused_assets',
			'delay_third_party_js',
			'defer_js',
			'enable_page_cache',
			'lazy_load_images',
			'lazy_load_iframes',
			'disable_emojis',
			'disable_dashicons',
			'clean_wp_head',
			'heartbeat_control',
			'disable_embeds',
			'minify_css'
		];

		$('.fbsseop-asset-opt-checkbox').each(function () {
			var opt = $(this).data('opt');
			if ($.inArray(opt, recommended) !== -1) {
				$(this).prop('checked', true);
			}
		});

		showToast('Max PageSpeed Preset toggled on! Click "Save Speed & Database Settings" below to activate.', false);
	});

	// 2. Manual 1-Click Database Cleanup
	$('#fbsseop-run-cleanup-btn').on('click', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmCleanup)) {
			return;
		}
		var $btn = $(this);
		var origHtml = $btn.html();
		$btn.prop('disabled', true).text(fbsseopAdmin.strings.cleaning);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_cleanup_database',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (res && res.success) {
				showToast(res.data.message, false);
			} else {
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			showToast(fbsseopAdmin.strings.genericError, true);
		}).always(function () {
			$btn.prop('disabled', false).html(origHtml);
		});
	});

	// 3. 1-Click Protect wp-content/uploads/
	$('#fbsseop-protect-uploads-btn').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_protect_uploads',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (res && res.success) {
				$('#fbsseop-uploads-status-text').html('<strong style="color:#059669;">✓ ' + escHtml(res.data.message) + '</strong>');
				$btn.remove();
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});

	// 4. 1-Click Deactivate High-Risk Plugin
	$(document).on('click', '.fbsseop-deactivate-risky', function () {
		var $btn = $(this);
		var basename = $btn.data('plugin');
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_deactivate_risky_plugin',
			nonce: fbsseopAdmin.nonce,
			plugin: basename
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('.fbsseop-risky-item').fadeOut(200, function () {
					$(this).remove();
				});
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});

	// 5. Clear Failed Login Lockouts
	$('#fbsseop-clear-lockouts-btn').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_clear_lockouts',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (res && res.success) {
				showToast(res.data.message, false);
				window.location.reload();
			} else {
				$btn.prop('disabled', false);
			}
		});
	});

	function sevBadgeClass(sev) {
		if (sev === 'CRITICAL') {
			return 'fbsseop-sev--critical';
		}
		if (sev === 'HIGH') {
			return 'fbsseop-sev--high';
		}
		if (sev === 'MEDIUM') {
			return 'fbsseop-sev--medium';
		}
		return 'fbsseop-sev--info';
	}

	var scanStages = [
		{ pct: 12, label: 'Layer 1 / 6: Auditing root PHP files & official WordPress.org Core MD5 checksums...' },
		{ pct: 28, label: 'Layer 2 / 6: Inspecting wp-content/uploads/ shells & mu-plugins persistence...' },
		{ pct: 48, label: 'Layer 3 / 6: Scanning plugin & theme heuristics against official WP.org checksums...' },
		{ pct: 68, label: 'Layer 4 / 6: Auditing database code snippets (wp_snippets, Elementor, WPCode, HFCM) & WP-Cron...' },
		{ pct: 84, label: 'Layer 5 / 6: Reflecting user-concealment hooks, rogue admins & Application Passwords...' },
		{ pct: 93, label: 'Layer 6 / 6: Running live Visitor vs. Googlebot cloaked SEO spam loopback check...' }
	];

	function buildScanProgressHtml() {
		var chips = [
			'Root & Core MD5',
			'Uploads & MU-Plugins',
			'Plugin/Theme Checksums',
			'DB Snippets & Cron',
			'Hook & Admin Audit',
			'Googlebot Cloaking'
		];
		var html = '<div class="fbsseop-scan-progress" id="fbsseop-scan-progress-card">';
		html += '<div class="fbsseop-scan-progress__top">';
		html += '<div class="fbsseop-scan-progress__status"><span class="dashicons dashicons-update fbsseop-spin"></span><strong id="fbsseop-scan-stage-label">' + escHtml(scanStages[0].label) + '</strong></div>';
		html += '<div class="fbsseop-scan-progress__meta"><span id="fbsseop-scan-pct">12%</span><span class="fbsseop-scan-progress__sep">•</span><span id="fbsseop-scan-elapsed">0.0s</span></div>';
		html += '</div>';
		html += '<div class="fbsseop-scan-progress__track"><div class="fbsseop-scan-progress__fill" id="fbsseop-scan-bar-fill" style="width:12%;"></div></div>';
		html += '<div class="fbsseop-scan-stages">';
		for (var i = 0; i < chips.length; i++) {
			var cls = i === 0 ? ' is-active' : '';
			html += '<div class="fbsseop-scan-stage' + cls + '" data-stage-idx="' + i + '"><span class="fbsseop-scan-stage__dot"></span><span>' + escHtml(chips[i]) + '</span></div>';
		}
		html += '</div></div>';
		return html;
	}

	function updateScanProgressStage(stageIdx) {
		var st = scanStages[stageIdx];
		if (!st) {
			return;
		}
		$('#fbsseop-scan-stage-label').text(st.label);
		$('#fbsseop-scan-pct').text(st.pct + '%');
		$('#fbsseop-scan-bar-fill').css('width', st.pct + '%');
		$('.fbsseop-scan-stage').each(function () {
			var idx = parseInt($(this).attr('data-stage-idx'), 10);
			$(this).toggleClass('is-done', idx < stageIdx).toggleClass('is-active', idx === stageIdx);
		});
	}

	// 6. Run Deep Malware, Database Rootkit, Cloaking & Hidden-Admin Scan
	$('#fbsseop-run-scan-btn').on('click', function () {
		var $btn = $(this);
		var $results = $('#fbsseop-scan-results');
		var startTs = Date.now();
		var currentStage = 0;

		$btn.prop('disabled', true);
		$btn.find('.dashicons').addClass('fbsseop-spin');
		$btn.find('span:last').text(fbsseopAdmin.strings.scanning);
		$results.show().html(buildScanProgressHtml());

		var elapsedTimer = setInterval(function () {
			var secs = ((Date.now() - startTs) / 1000).toFixed(1);
			$('#fbsseop-scan-elapsed').text(secs + 's');
		}, 150);

		var stageTimer = setInterval(function () {
			if (currentStage < scanStages.length - 1) {
				currentStage++;
				updateScanProgressStage(currentStage);
			}
		}, 850);

		function stopProgressTimers() {
			clearInterval(elapsedTimer);
			clearInterval(stageTimer);
		}

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_run_scan',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			stopProgressTimers();

			if (!res || !res.success) {
				$results.html('<div class="fbsseop-toast fbsseop-toast--error">' + escHtml((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError) + '</div>');
				return;
			}

			var d = res.data;
			var html = '';

			if (d.total_issues === 0) {
				html += '<div class="fbsseop-toast"><span class="dashicons dashicons-yes-alt"></span><span>' + escHtml(d.message) + ' (' + escHtml(d.scanned_files) + ' PHP files inspected)</span></div>';
			} else {
				html += '<div class="fbsseop-toast fbsseop-toast--error"><span class="dashicons dashicons-warning"></span><span>' + escHtml(d.message) + ' (' + escHtml(d.scanned_files) + ' PHP files inspected)</span></div>';
			}

			if (d.verified_clean_files && d.verified_clean_files > 0) {
				html += '<div class="fbsseop-verified-note">✓ <strong>' + escHtml(d.verified_clean_files) + ' vendor PHP file(s)</strong> matched eval/shell heuristics but were cryptographically verified 100% identical to official WordPress.org plugin checksums (false positives suppressed).</div>';
			}

			// 6a. Front-End Live DOM & Googlebot Cloaked SEO Spam
			if (d.frontend_threats && d.frontend_threats.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-admin-site-alt3"></span><span>Front-End Hidden SEO Spam & Search-Engine Cloaking (' + d.frontend_threats.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>Severity</th><th>HTTP View</th><th>Cloaked / Spam Target</th><th>Forensic Detail</th></tr></thead><tbody>';
				$.each(d.frontend_threats, function (_, ft) {
					html += '<tr>';
					html += '<td><span class="fbsseop-sev ' + sevBadgeClass(ft.severity) + '">' + escHtml(ft.severity) + '</span></td>';
					html += '<td><strong>' + escHtml(ft.view) + '</strong></td>';
					html += '<td><code>' + escHtml(ft.url) + '</code></td>';
					html += '<td><div>' + escHtml(ft.reason) + '</div>';
					if (ft.preview) {
						html += '<code class="fbsseop-code-inline-preview">' + escHtml(ft.preview) + '</code>';
					}
					html += '</td></tr>';
				});
				html += '</tbody></table></div>';
			}

			// 6b. Flagged Database Snippets, CPTs, Options & WP-Cron Persistence
			if (d.db_threats && d.db_threats.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-database"></span><span>Flagged Database Snippets, Options & WP-Cron Persistence (' + d.db_threats.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>Severity</th><th>Database Location</th><th>Matched Signatures</th><th>Direct-DB Actions</th></tr></thead><tbody>';
				$.each(d.db_threats, function (_, dbItem) {
					var src = dbItem.source || 'wp_options';
					var hookAttr = dbItem.hook_name || '';
					html += '<tr>';
					html += '<td><span class="fbsseop-sev ' + sevBadgeClass(dbItem.severity) + '">' + escHtml(dbItem.severity) + '</span></td>';
					html += '<td><code>' + escHtml(dbItem.name) + '</code></td>';
					html += '<td>' + escHtml(dbItem.reason) + '</td>';
					html += '<td class="fbsseop-td-actions"><div class="fbsseop-row-actions">';
					html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-view-db-code-btn" data-source="' + escHtml(src) + '" data-rowid="' + escHtml(dbItem.row_id) + '" data-hook="' + escHtml(hookAttr) + '">View Full Code</button>';
					if (dbItem.can_disable) {
						html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--warn fbsseop-remediate-snippet-btn" data-mode="deactivate" data-source="' + escHtml(src) + '" data-rowid="' + escHtml(dbItem.row_id) + '">Deactivate</button>';
					}
					if (dbItem.can_delete) {
						var delLabel = src === 'wp_cron' ? 'Unschedule' : 'Delete';
						html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-remediate-snippet-btn" data-mode="delete" data-source="' + escHtml(src) + '" data-rowid="' + escHtml(dbItem.row_id) + '" data-hook="' + escHtml(hookAttr) + '">' + escHtml(delLabel) + '</button>';
					}
					html += '</div></td></tr>';
				});
				html += '</tbody></table></div>';
			}

			// 6c. Hidden or Rogue Administrator Accounts
			var hiddenUsers = (d.hidden_audit && d.hidden_audit.hidden_users) ? d.hidden_audit.hidden_users : [];
			if (hiddenUsers.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-admin-users"></span><span>Hidden, Rogue or Anomalous Admin Accounts (' + hiddenUsers.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>User ID</th><th>Login & Email</th><th>Registered</th><th>Forensic Anomaly</th><th>Direct-DB Action</th></tr></thead><tbody>';
				$.each(hiddenUsers, function (_, u) {
					var emailDisplay = u.user_email ? escHtml(u.user_email) : '<span class="fbsseop-sev fbsseop-sev--critical">EMPTY EMAIL</span>';
					html += '<tr>';
					html += '<td><strong>#' + escHtml(u.ID) + '</strong></td>';
					html += '<td><strong>' + escHtml(u.user_login) + '</strong><br><small>' + emailDisplay + '</small></td>';
					html += '<td><code>' + escHtml(u.user_registered || '—') + '</code></td>';
					html += '<td>' + escHtml(u.hidden_type) + '</td>';
					html += '<td class="fbsseop-td-actions"><div class="fbsseop-row-actions">';
					if (u.is_current) {
						html += '<span class="fbsseop-sev fbsseop-sev--info">Current Session (Protected)</span>';
					} else {
						html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-force-delete-user-btn" data-userid="' + escHtml(u.ID) + '">Force Delete User</button>';
						if (d.is_pro) {
							html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-pro-disarm-user-btn" data-userid="' + escHtml(u.ID) + '">Disarm Role (Pro)</button>';
						}
					}
					html += '</div></td></tr>';
				});
				html += '</tbody></table></div>';
			}

			// 6d. User-Concealment & Admin UI Hook Reflection
			var suspHooks = (d.hidden_audit && d.hidden_audit.suspicious_hooks) ? d.hidden_audit.suspicious_hooks : [];
			if (suspHooks.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-visibility"></span><span>User-Concealment & Admin UI Hook Reflection (' + suspHooks.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>Severity</th><th>Hook</th><th>Callback</th><th>Source File & Line</th><th>Assessment</th></tr></thead><tbody>';
				$.each(suspHooks, function (_, hk) {
					var loc = hk.file ? (hk.file + (hk.line ? ':' + hk.line : '')) : 'Dynamic / Database Execution';
					html += '<tr>';
					html += '<td><span class="fbsseop-sev ' + sevBadgeClass(hk.severity) + '">' + escHtml(hk.severity) + '</span></td>';
					html += '<td><code>' + escHtml(hk.hook) + '</code> <small>(p:' + escHtml(hk.priority) + ')</small></td>';
					html += '<td><code>' + escHtml(hk.callback) + '</code></td>';
					html += '<td><code>' + escHtml(loc) + '</code></td>';
					html += '<td>' + escHtml(hk.reason) + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table></div>';
			}

			// 6e. Flagged Filesystem Findings Table
			if (d.findings && d.findings.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-media-code"></span><span>Flagged Filesystem Findings (' + d.findings.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>Severity</th><th>File</th><th>Reason</th><th>Actions</th></tr></thead><tbody>';
				$.each(d.findings, function (_, item) {
					html += '<tr>';
					html += '<td><span class="fbsseop-sev ' + sevBadgeClass(item.severity) + '">' + escHtml(item.severity) + '</span></td>';
					html += '<td><code>' + escHtml(item.file) + '</code></td>';
					html += '<td>' + escHtml(item.reason) + '</td>';
					html += '<td class="fbsseop-td-actions"><div class="fbsseop-row-actions">';
					html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-preview-file-btn" data-file="' + escHtml(item.file) + '">Preview</button>';
					if (item.can_delete) {
						if (d.is_pro) {
							html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-pro-delete-file-btn" data-file="' + escHtml(item.file) + '">Delete (Pro)</button>';
						} else {
							html += '<a href="' + escHtml(fbsseopAdmin.proUrl) + '" target="_blank" rel="noopener" class="fbsseop-btn fbsseop-btn--sm">1-Click Delete (Pro)</a>';
						}
					}
					html += '</div></td></tr>';
				});
				html += '</tbody></table></div>';
			}

			// 6f. Collapsed Active Database Code Snippets Inventory (Read-Only View Full Code)
			if (d.active_snippets && d.active_snippets.length > 0) {
				var flaggedCount = 0;
				$.each(d.active_snippets, function (_, sItem) {
					if (sItem.is_flagged) {
						flaggedCount++;
					}
				});

				var summaryIcon = flaggedCount === 0 ? 'dashicons-yes-alt is-clean' : 'dashicons-editor-code';
				var summaryText = flaggedCount === 0
					? d.active_snippets.length + ' Active Database Snippet(s) Inspected & Verified Clean'
					: d.active_snippets.length + ' Active Database Snippet(s) in MySQL (' + flaggedCount + ' flagged above)';

				html += '<details class="fbsseop-audit-drawer">';
				html += '<summary class="fbsseop-audit-drawer__summary">';
				html += '<span class="fbsseop-audit-drawer__left"><span class="dashicons ' + summaryIcon + '"></span><strong>' + escHtml(summaryText) + '</strong></span>';
				html += '<span class="fbsseop-audit-drawer__Action">Click to View Forensic Inventory ▾</span>';
				html += '</summary>';
				html += '<div class="fbsseop-audit-drawer__body">';
				html += '<p style="margin:0 0 10px;font-size:12.5px;color:#475569;">Queried directly via raw SQL so self-hiding admin table rootkits (such as <code>SnCache</code> CSS/JS row hiders) cannot conceal active PHP/HTML snippets.</p>';
				html += '<div class="fbsseop-table-wrap" style="margin-bottom:0;"><table class="fbsseop-scan-table"><thead><tr><th>Status</th><th>Source Table</th><th>ID & Snippet Name</th><th>Scope / Size</th><th>Inspect</th></tr></thead><tbody>';
				$.each(d.active_snippets, function (_, sn) {
					var stBadge = sn.is_flagged
						? '<span class="fbsseop-sev fbsseop-sev--critical">FLAGGED</span>'
						: '<span class="fbsseop-sev fbsseop-sev--ok">CLEAN</span>';
					html += '<tr>';
					html += '<td>' + stBadge + '</td>';
					html += '<td><code>' + escHtml(sn.table) + '</code></td>';
					html += '<td><strong>#' + escHtml(sn.row_id) + ' — ' + escHtml(sn.name) + '</strong></td>';
					html += '<td>' + escHtml(sn.scope) + ' (' + escHtml(sn.code_len) + ' B)</td>';
					html += '<td class="fbsseop-td-actions"><div class="fbsseop-row-actions">';
					html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-view-db-code-btn" data-source="' + escHtml(sn.source) + '" data-rowid="' + escHtml(sn.row_id) + '">View Full Code</button>';
					html += '</div></td></tr>';
				});
				html += '</tbody></table></div></div></details>';
			}

			// 6g. Active REST API Application Passwords Audit
			if (d.app_passwords && d.app_passwords.length > 0) {
				html += '<h3 class="fbsseop-scan-section-title"><span class="dashicons dashicons-admin-network"></span><span>Active Application Passwords Audit (' + d.app_passwords.length + ')</span></h3>';
				html += '<div class="fbsseop-table-wrap"><table class="fbsseop-scan-table"><thead><tr><th>User</th><th>App Password Name</th><th>Created</th><th>Last Used & IP</th><th>Action</th></tr></thead><tbody>';
				$.each(d.app_passwords, function (_, ap) {
					var rolePill = ap.is_admin ? ' <span class="fbsseop-sev fbsseop-sev--high">ADMIN</span>' : '';
					html += '<tr>';
					html += '<td><strong>' + escHtml(ap.user_login) + '</strong>' + rolePill + '</td>';
					html += '<td><code>' + escHtml(ap.name) + '</code></td>';
					html += '<td>' + escHtml(ap.created) + '</td>';
					html += '<td>' + escHtml(ap.last_used) + ' (' + escHtml(ap.last_ip) + ')</td>';
					html += '<td class="fbsseop-td-actions"><div class="fbsseop-row-actions"><button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-revoke-app-pass-btn" data-userid="' + escHtml(ap.user_id) + '" data-uuid="' + escHtml(ap.uuid) + '">Revoke</button></div></td>';
					html += '</tr>';
				});
				html += '</tbody></table></div>';
			}

			// Snap progress bar to 100% cleanly before revealing final report
			$('#fbsseop-scan-pct').text('100%');
			$('#fbsseop-scan-bar-fill').css('width', '100%');
			$('.fbsseop-scan-stage').removeClass('is-active').addClass('is-done');
			$('#fbsseop-scan-stage-label').text('100% — Deep Forensic Scan Complete!');

			setTimeout(function () {
				$results.html(html);
			}, 260);
		}).fail(function () {
			stopProgressTimers();
			$results.html('<div class="fbsseop-toast fbsseop-toast--error">' + escHtml(fbsseopAdmin.strings.genericError) + '</div>');
		}).always(function () {
			$btn.prop('disabled', false);
			$btn.find('.dashicons').removeClass('fbsseop-spin');
			$btn.find('span:last').text(fbsseopAdmin.strings.scanBtnLabel);
		});
	});

	// 7. File & Database Full Code Preview Modal
	$(document).on('click', '.fbsseop-preview-file-btn', function () {
		var file = $(this).data('file');
		$('#fbsseop-preview-title').text('Inspecting File: ' + file);
		$('#fbsseop-preview-code').text('Loading file snippet...');
		$('#fbsseop-preview-modal').slideDown(160);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_preview_file',
			nonce: fbsseopAdmin.nonce,
			file: file
		}).done(function (res) {
			if (res && res.success) {
				$('#fbsseop-preview-code').text(res.data.content);
			} else {
				$('#fbsseop-preview-code').text((res && res.data && res.data.message) || 'Unable to load preview.');
			}
		});
	});

	$(document).on('click', '.fbsseop-view-db-code-btn', function () {
		var $btn = $(this);
		$('#fbsseop-preview-title').text('Loading database payload...');
		$('#fbsseop-preview-code').text('Querying database record directly...');
		$('#fbsseop-preview-modal').slideDown(160);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_view_db_code',
			nonce: fbsseopAdmin.nonce,
			source: $btn.data('source'),
			row_id: $btn.data('rowid') || 0,
			hook_name: $btn.data('hook') || ''
		}).done(function (res) {
			if (res && res.success) {
				$('#fbsseop-preview-title').text('Database Payload: ' + res.data.title);
				$('#fbsseop-preview-code').text(res.data.content);
			} else {
				$('#fbsseop-preview-code').text((res && res.data && res.data.message) || 'Unable to load database payload.');
			}
		});
	});

	$('#fbsseop-close-modal').on('click', function () {
		$('#fbsseop-preview-modal').slideUp(150);
	});

	// 7b. Free Direct-DB Remediation Actions (Snippets, Rogue Users, Application Passwords & Session Invalidation)
	$(document).on('click', '.fbsseop-remediate-snippet-btn', function () {
		var $btn = $(this);
		var mode = $btn.data('mode');
		var confirmMsg = mode === 'deactivate'
			? fbsseopAdmin.strings.confirmDeactivateSnippet
			: fbsseopAdmin.strings.confirmDeleteSnippet;

		if (!window.confirm(confirmMsg)) {
			return;
		}

		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_remediate_snippet',
			nonce: fbsseopAdmin.nonce,
			mode: mode,
			source: $btn.data('source'),
			row_id: $btn.data('rowid') || 0,
			hook_name: $btn.data('hook') || ''
		}).done(function (res) {
			if (res && res.success) {
				if (mode === 'delete') {
					$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				} else {
					$btn.text('Deactivated').removeClass('fbsseop-btn--warn');
				}
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			showToast(fbsseopAdmin.strings.genericError, true);
		});
	});

	$(document).on('click', '.fbsseop-force-delete-user-btn', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmForceDeleteUser)) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_force_delete_user',
			nonce: fbsseopAdmin.nonce,
			user_id: $btn.data('userid')
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			showToast(fbsseopAdmin.strings.genericError, true);
		});
	});

	$(document).on('click', '.fbsseop-revoke-app-pass-btn', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmRevokeAppPass)) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_revoke_app_password',
			nonce: fbsseopAdmin.nonce,
			user_id: $btn.data('userid'),
			uuid: $btn.data('uuid')
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			showToast(fbsseopAdmin.strings.genericError, true);
		});
	});

	$('#fbsseop-post-cleanup-btn').on('click', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmPostCleanup)) {
			return;
		}
		var $btn = $(this);
		var origHtml = $btn.html();
		$btn.prop('disabled', true).find('span:last').text(fbsseopAdmin.strings.postCleaning);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_post_cleanup_purge',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (res && res.success) {
				showToast(res.data.message, false);
			} else {
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		}).fail(function () {
			showToast(fbsseopAdmin.strings.genericError, true);
		}).always(function () {
			$btn.prop('disabled', false).html(origHtml);
		});
	});

	// 8. Pro Remediation Actions (Active when FBS Secure Optimize Pro is installed)
	$(document).on('click', '.fbsseop-pro-delete-file-btn', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmDelete)) {
			return;
		}
		var $btn = $(this);
		var file = $btn.data('file');
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_pro_delete_file',
			nonce: fbsseopAdmin.nonce,
			file: file
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});

	$(document).on('click', '.fbsseop-pro-delete-db-btn', function () {
		if (!window.confirm(fbsseopAdmin.strings.confirmDelete)) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_pro_delete_db_threat',
			nonce: fbsseopAdmin.nonce,
			table: $btn.data('table'),
			row_id: $btn.data('rowid')
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});

	$(document).on('click', '.fbsseop-pro-disarm-user-btn', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_pro_disarm_user',
			nonce: fbsseopAdmin.nonce,
			user_id: $btn.data('userid')
		}).done(function (res) {
			if (res && res.success) {
				$btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
				showToast(res.data.message, false);
			} else {
				$btn.prop('disabled', false);
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
	});
})(jQuery);
