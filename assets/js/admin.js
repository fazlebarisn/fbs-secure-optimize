/**
 * Admin Interactive Controller for FBS Secure Optimize v2.0.0
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

	// 1. Instant Independent Module Toggle via AJAX
	$(document).on('change', '.fbsseop-ajax-module-toggle', function () {
		var $checkbox = $(this);
		var moduleKey = $checkbox.data('module');
		var isEnabled = $checkbox.is(':checked') ? 1 : 0;
		var $card = $checkbox.closest('.fbsseop-module-card');

		$card.toggleClass('is-enabled', !!isEnabled).toggleClass('is-disabled', !isEnabled);
		$card.find('.fbsseop-status-text').text(isEnabled ? '● Active' : '○ Disabled');

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
				showToast(res.data.message, false);
			} else {
				showToast((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError, true);
			}
		});
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

	// 6. Run 6-Layer Malware, Core Checksum & Hidden-Admin Scan
	$('#fbsseop-run-scan-btn').on('click', function () {
		var $btn = $(this);
		var $results = $('#fbsseop-scan-results');
		$btn.prop('disabled', true).find('span:last').text(fbsseopAdmin.strings.scanning);
		$results.show().html('<p style="padding:14px;background:#f8fafc;border-radius:10px;font-weight:600;">⏳ Inspecting root PHP files, official WP.org MD5 checksums, uploads/, mu-plugins, plugin/theme heuristics, wp_options, and hidden administrator accounts...</p>');

		$.post(fbsseopAdmin.ajaxUrl, {
			action: 'fbsseop_run_scan',
			nonce: fbsseopAdmin.nonce
		}).done(function (res) {
			if (!res || !res.success) {
				$results.html('<div class="fbsseop-toast fbsseop-toast--error">' + escHtml((res && res.data && res.data.message) || fbsseopAdmin.strings.genericError) + '</div>');
				return;
			}

			var d = res.data;
			var html = '';

			if (d.total_issues === 0) {
				html += '<div class="fbsseop-toast">✓ ' + escHtml(d.message) + ' (' + escHtml(d.scanned_files) + ' PHP files verified)</div>';
			} else {
				html += '<div class="fbsseop-toast fbsseop-toast--error">⚠ ' + escHtml(d.message) + '</div>';
			}

			// File Findings Table
			if (d.findings && d.findings.length > 0) {
				html += '<h3 style="margin:18px 0 8px;">Flagged Filesystem Findings (' + d.findings.length + ')</h3>';
				html += '<table class="fbsseop-mini-table"><thead><tr><th>Severity</th><th>File</th><th>Reason</th><th>Actions</th></tr></thead><tbody>';
				$.each(d.findings, function (_, item) {
					var sevClass = item.severity === 'CRITICAL' ? 'fbsseop-sev--critical' : 'fbsseop-sev--high';
					html += '<tr>';
					html += '<td><span class="fbsseop-sev ' + sevClass + '">' + escHtml(item.severity) + '</span></td>';
					html += '<td><code>' + escHtml(item.file) + '</code></td>';
					html += '<td>' + escHtml(item.reason) + '</td>';
					html += '<td style="display:flex;gap:6px;flex-wrap:wrap;">';
					html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-preview-file-btn" data-file="' + escHtml(item.file) + '">Preview</button>';
					if (item.can_delete) {
						if (d.is_pro) {
							html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-pro-delete-file-btn" data-file="' + escHtml(item.file) + '">Delete (Pro)</button>';
						} else {
							html += '<a href="' + escHtml(fbsseopAdmin.proUrl) + '" target="_blank" rel="noopener" class="fbsseop-btn fbsseop-btn--sm">1-Click Delete (Pro)</a>';
						}
					}
					html += '</td></tr>';
				});
				html += '</tbody></table>';
			}

			// Database Threats
			if (d.db_threats && d.db_threats.length > 0) {
				html += '<h3 style="margin:20px 0 8px;">Flagged Database Options / Snippets (' + d.db_threats.length + ')</h3>';
				html += '<table class="fbsseop-mini-table"><thead><tr><th>Severity</th><th>Option / Row</th><th>Reason</th><th>Action</th></tr></thead><tbody>';
				$.each(d.db_threats, function (_, dbItem) {
					html += '<tr>';
					html += '<td><span class="fbsseop-sev fbsseop-sev--high">' + escHtml(dbItem.severity) + '</span></td>';
					html += '<td><code>' + escHtml(dbItem.name) + '</code></td>';
					html += '<td>' + escHtml(dbItem.reason) + '</td>';
					html += '<td>';
					if (d.is_pro) {
						html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-pro-delete-db-btn" data-table="' + escHtml(dbItem.table) + '" data-rowid="' + escHtml(dbItem.row_id) + '">Delete Row (Pro)</button>';
					} else {
						html += '<a href="' + escHtml(fbsseopAdmin.proUrl) + '" target="_blank" rel="noopener" class="fbsseop-btn fbsseop-btn--sm">Unlock Pro Cleanup</a>';
					}
					html += '</td></tr>';
				});
				html += '</tbody></table>';
			}

			// Hidden / Unauthorized Administrators
			var hiddenUsers = (d.hidden_audit && d.hidden_audit.hidden_users) ? d.hidden_audit.hidden_users : [];
			if (hiddenUsers.length > 0) {
				html += '<h3 style="margin:20px 0 8px;">⚠ Hidden or Stealth-Elevated Admin Accounts (' + hiddenUsers.length + ')</h3>';
				html += '<table class="fbsseop-mini-table"><thead><tr><th>User ID</th><th>Login & Email</th><th>Detection Type</th><th>Action</th></tr></thead><tbody>';
				$.each(hiddenUsers, function (_, u) {
					html += '<tr>';
					html += '<td>#' + escHtml(u.ID) + '</td>';
					html += '<td><strong>' + escHtml(u.user_login) + '</strong> (' + escHtml(u.user_email) + ')</td>';
					html += '<td>' + escHtml(u.hidden_type) + '</td>';
					html += '<td>';
					if (d.is_pro) {
						html += '<button type="button" class="fbsseop-btn fbsseop-btn--sm fbsseop-btn--danger fbsseop-pro-disarm-user-btn" data-userid="' + escHtml(u.ID) + '">Disarm User (Pro)</button>';
					} else {
						html += '<a href="' + escHtml(fbsseopAdmin.proUrl) + '" target="_blank" rel="noopener" class="fbsseop-btn fbsseop-btn--sm">Pro Auto-Disarm</a>';
					}
					html += '</td></tr>';
				});
				html += '</tbody></table>';
			}

			$results.html(html);
		}).fail(function () {
			$results.html('<div class="fbsseop-toast fbsseop-toast--error">' + escHtml(fbsseopAdmin.strings.genericError) + '</div>');
		}).always(function () {
			$btn.prop('disabled', false).find('span:last').text(fbsseopAdmin.strings.scanBtnLabel);
		});
	});

	// 7. File Snippet Preview Modal
	$(document).on('click', '.fbsseop-preview-file-btn', function () {
		var file = $(this).data('file');
		$('#fbsseop-preview-title').text('Inspecting: ' + file);
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

	$('#fbsseop-close-modal').on('click', function () {
		$('#fbsseop-preview-modal').slideUp(150);
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
