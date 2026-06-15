/**
 * This Is My URL Shadow Dashboard Engine
 * 
 * Minimal, focused JavaScript for admin dashboard
 * Philosophy: Vanilla JS, REST API driven, accessibility-first
 * 
 * @package ThisIsMyURL\Shadow
 */

(function() {
	'use strict';

	if ( window.thisismyurlShadowUI && window.thisismyurlShadowUI.__bootstrapped ) {
		return;
	}

	const thisismyurlShadowUI = {
		initialized: false,
		searchDebounceTimer: null,

		/**
		 * Initialize all dashboard features
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}
			this.initialized = true;
			this.__bootstrapped = true;

			this.bindAdminNoticeDismissals();
			this.initSearchFilter();
			this.initFilterButtons();
			this.initPreselectedFamilyFilter();
			this.initDetailPageActions();
			this.restoreLastActionFocus();
		},

		/*
		 * Reload the page after a destructive/state-changing action, flagging the
		 * detail heading so focus can be restored to it once the new page loads.
		 * Without this, the reload drops keyboard/SR users at the top of the page
		 * with no announcement of where they were (WCAG 2.4.3).
		 */
		reloadToLastAction: function( delay ) {
			window.setTimeout( () => {
				window.location.hash = 'wps-last-action';
				window.location.reload();
			}, delay );
		},

		/*
		 * On load after reloadToLastAction(), move focus to the detail heading so
		 * the keyboard/SR user lands back on the check they just acted on.
		 */
		restoreLastActionFocus: function() {
			if ( window.location.hash !== '#wps-last-action' ) {
				return;
			}
			const heading = document.getElementById( 'wps-detail-heading' );
			if ( heading ) {
				heading.focus();
			}
			// Clear the hash so a manual reload / back-nav doesn't re-fire the focus jump.
			history.replaceState( null, '', window.location.pathname + window.location.search );
		},

		initPreselectedFamilyFilter: function() {
			const app = document.querySelector( '[data-preselected-family]' );
			const family = app ? ( app.getAttribute( 'data-preselected-family' ) || '' ) : '';

			if ( ! family ) {
				return;
			}

			const familyEl = document.getElementById( 'wps-filter-family' );
			if ( familyEl ) {
				familyEl.value = family;
				this.filterTable();
			}
		},

		bindAdminNoticeDismissals: function() {
			document.addEventListener( 'click', ( e ) => {
				const fileWriteTrigger = e.target.closest( '.thisismyurl-shadow-dismiss-file-write-notice, .thisismyurl-shadow-file-write-notice .notice-dismiss' );
				if ( fileWriteTrigger ) {
					e.preventDefault();
					this.dismissAdminNotice( '.thisismyurl-shadow-file-write-notice', 'thisismyurl_shadow_dismiss_file_write_notice' );
					return;
				}

				const staleDiagnosticsTrigger = e.target.closest( '.thisismyurl-shadow-dismiss-stale-diagnostics-notice, .thisismyurl-shadow-stale-diagnostics-notice .notice-dismiss' );
				if ( staleDiagnosticsTrigger ) {
					e.preventDefault();
					this.dismissAdminNotice( '.thisismyurl-shadow-stale-diagnostics-notice', 'thisismyurl_shadow_dismiss_stale_diagnostics_notice' );
				}
			} );
		},

		dismissAdminNotice: function( selector, action ) {
			const notice = document.querySelector( selector );
			if ( ! notice ) {
				return;
			}

			const nonce = notice.getAttribute( 'data-nonce' ) || '';
			if ( nonce && ( window.ajaxurl || '/wp-admin/admin-ajax.php' ) ) {
				fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: new URLSearchParams( {
						action: action,
						nonce: nonce
					} )
				} ).catch( () => {
					// Ignore dismiss errors; the notice is non-critical UI only.
				} );
			}

			// Move focus off the dismiss control before it disappears, so keyboard and
			// screen-reader users are not stranded on a hidden element (WCAG 2.4.3).
			const focusTarget = document.querySelector( '.wrap h1' ) || document.body;
			if ( focusTarget ) {
				if ( focusTarget === document.body || ! focusTarget.hasAttribute( 'tabindex' ) ) {
					focusTarget.setAttribute( 'tabindex', '-1' );
				}
				focusTarget.focus();
			}

			// Respect the user's reduced-motion preference: skip the fade and hide immediately.
			const prefersReducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			if ( prefersReducedMotion ) {
				notice.style.display = 'none';
				return;
			}

			notice.style.transition = 'opacity 0.3s ease';
			notice.style.opacity = '0';
			window.setTimeout( () => {
				notice.style.display = 'none';
			}, 300 );
		},

		initSearchFilter: function() {
			const searchInput = document.getElementById('wps-search-diagnostics');
			if (!searchInput) return;

			if ( searchInput.dataset.wpsBoundInput === '1' ) {
				return;
			}
			searchInput.dataset.wpsBoundInput = '1';

			searchInput.addEventListener('input', (e) => {
				clearTimeout(this.searchDebounceTimer);
				this.searchDebounceTimer = setTimeout(() => {
					this.filterTable();
				}, 200);
			});
		},

		/**
		 * Filter button handlers (Status, Family, Confidence, Core)
		 */
		initFilterButtons: function() {
			const filters = [
				'wps-filter-result',
				'wps-filter-family',
				'wps-filter-confidence',
				'wps-filter-core'
			];

			filters.forEach(filterId => {
				const el = document.getElementById(filterId);
				if (el) {
					el.addEventListener('change', () => this.filterTable());
				}
			});

			const clearBtn = document.getElementById('wps-filter-clear');
			if (clearBtn) {
				clearBtn.addEventListener('click', () => this.clearFilters());
			}
		},

		/**
		 * Apply all active filters
		 */
		filterTable: function() {
			const searchText = (document.getElementById('wps-search-diagnostics')?.value || '').toLowerCase();
			const resultFilter = document.getElementById('wps-filter-result')?.value || 'all';
			const familyFilter = document.getElementById('wps-filter-family')?.value || 'all';
			const confidenceFilter = document.getElementById('wps-filter-confidence')?.value || 'all';
			const coreOnlyChecked = document.getElementById('wps-filter-core')?.checked || false;

			const rows = document.querySelectorAll('[data-diagnostic-row]');
			let visibleCount = 0;

			rows.forEach((row) => {
				const name = (row.getAttribute('data-name') || '').toLowerCase();
				const result = row.getAttribute('data-result') || 'unknown';
				const family = row.getAttribute('data-family') || '';
				const confidence = row.getAttribute('data-confidence') || 'standard';
				const isCore = row.getAttribute('data-core') === 'yes';

				const matchesSearch = !searchText || name.includes(searchText);
				const matchesResult = resultFilter === 'all' || result === resultFilter;
				const matchesFamily = familyFilter === 'all' || family === familyFilter;
				const matchesConfidence = confidenceFilter === 'all' || confidence === confidenceFilter;
				const matchesCore = !coreOnlyChecked || isCore;

				const isVisible = matchesSearch && matchesResult && matchesFamily && matchesConfidence && matchesCore;

				row.style.display = isVisible ? '' : 'none';
				if (isVisible) visibleCount++;

				// Update row index
				if (isVisible) {
					const indexCell = row.querySelector('[data-index-number]');
					if (indexCell) {
						indexCell.textContent = visibleCount;
					}
				}
			});

			// Show "no results" message if needed
			const noResults = document.getElementById('wps-no-results');
			if (noResults) {
				noResults.style.display = visibleCount === 0 ? '' : 'none';
			}
		},

		/**
		 * Clear all filters
		 */
		clearFilters: function() {
			const searchEl = document.getElementById('wps-search-diagnostics');
			const resultEl = document.getElementById('wps-filter-result');
			const familyEl = document.getElementById('wps-filter-family');
			const confidenceEl = document.getElementById('wps-filter-confidence');
			const coreEl = document.getElementById('wps-filter-core');

			if ( searchEl ) searchEl.value = '';
			if ( resultEl ) resultEl.value = 'all';
			if ( familyEl ) familyEl.value = 'all';
			if ( confidenceEl ) confidenceEl.value = 'all';
			if ( coreEl ) coreEl.checked = false;
			this.filterTable();
		},

		/**
		 * Toggle diagnostic enabled state
		 */
		toggleDiagnostic: function(button) {
			const className = button.getAttribute('data-class-name');
			const nonce = button.getAttribute('data-nonce');
			const currentState = button.getAttribute('data-enabled') === '1';

			if (!className || !nonce) return;

			button.disabled = true;
			const statusEl = button.closest('[data-status-for]')?.querySelector('[data-status-message]');

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'thisismyurl_shadow_toggle_diagnostic',
					nonce: nonce,
					class_name: className,
					enable: currentState ? 0 : 1
				})
			})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						button.setAttribute('data-enabled', currentState ? '0' : '1');
						button.textContent = currentState ? 'Enable' : 'Disable';
						if (statusEl) {
							statusEl.textContent = currentState ? 'Diagnostic disabled.' : 'Diagnostic enabled.';
						}
					} else {
						if (statusEl) {
							statusEl.textContent = 'Error: ' + (data.data?.message || 'Failed');
						}
					}
				})
				.catch(err => {
					if (statusEl) {
						statusEl.textContent = 'Error: Network request failed';
					}
				})
				.finally(() => {
					button.disabled = false;
				});
		},

		/**
		 * Detail page: Save frequency, run diagnostic, toggle
		 */
		initDetailPageActions: function() {
			const saveFreqBtn = document.getElementById('wps-save-frequency');
			if (saveFreqBtn) {
				saveFreqBtn.addEventListener('click', () => this.saveFrequency());
			}

			const runTreatmentBtn = document.getElementById('wps-run-treatment');
			if (runTreatmentBtn) {
				runTreatmentBtn.addEventListener('click', () => this.runTreatment(runTreatmentBtn));
			}

			const treatmentToggle = document.getElementById('wps-treatment-auto-toggle');
			if (treatmentToggle) {
				treatmentToggle.addEventListener('change', () => this.toggleTreatmentPreference(treatmentToggle));
			}

			const saveInputsBtn = document.getElementById('wps-save-treatment-inputs');
			if (saveInputsBtn) {
				saveInputsBtn.addEventListener('click', () => this.saveTreatmentInputs(saveInputsBtn));
			}
		},

		/**
		 * Run an automated treatment directly from the Guardian detail page.
		 */
		runTreatment: function(button) {
			const findingId = button?.getAttribute('data-finding-id');
			const nonce = button?.getAttribute('data-nonce');
			const statusEl = document.getElementById('wps-run-treatment-status');

			if (!findingId || !nonce) {
				if (statusEl) {
					statusEl.textContent = 'Missing fix information. Please refresh and try again.';
				}
				return;
			}

			button.disabled = true;
			if (statusEl) {
				statusEl.textContent = 'Applying fix...';
			}

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'thisismyurl_shadow_autofix_finding',
					nonce: nonce,
					finding_id: findingId,
				})
			})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						if (statusEl) {
							statusEl.textContent = data.data?.message || 'Fix applied. Refreshing…';
						}
						this.reloadToLastAction( 900 );
					} else if (statusEl) {
						statusEl.textContent = 'Error: ' + (data.data?.message || 'Could not apply fix.');
					}
				})
				.catch(() => {
					if (statusEl) {
						statusEl.textContent = 'Error: Network request failed';
					}
				})
				.finally(() => {
					button.disabled = false;
				});
		},

		/**
		 * Save treatment input requirements from the detail page.
		 */
		saveTreatmentInputs: function(button) {
			const form = document.getElementById('wps-treatment-inputs-form');
			const statusEl = document.getElementById('wps-treatment-inputs-status');
			if (!form) return;

			const findingId = form.getAttribute('data-finding-id');
			const nonce = form.getAttribute('data-nonce');
			if (!findingId || !nonce) return;

			const controls = form.querySelectorAll('[data-input-key]');
			const values = {};
			let missingRequired = false;

			controls.forEach(control => {
				const key = control.getAttribute('data-input-key');
				const type = control.getAttribute('data-input-type') || 'text';
				const required = control.getAttribute('data-required') === '1';
				if (!key) return;

				if (type === 'toggle') {
					values[key] = control.checked ? '1' : '0';
					if (required && !control.checked) {
						missingRequired = true;
					}
					return;
				}

				const textValue = (control.value || '').trim();
				values[key] = textValue;
				if (required && !textValue) {
					missingRequired = true;
				}
			});

			if (missingRequired) {
				if (statusEl) {
					statusEl.textContent = 'Please complete all required inputs before saving.';
				}
				return;
			}

			button.disabled = true;
			if (statusEl) {
				statusEl.textContent = 'Saving fix inputs...';
			}

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'thisismyurl_shadow_save_treatment_inputs',
					nonce: nonce,
					finding_id: findingId,
					values: JSON.stringify(values),
				})
			})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						if (statusEl) {
							const appliedCount = parseInt(data.data?.applied_count || 0, 10);
							statusEl.textContent = appliedCount > 0
								? 'Saved. Site settings were updated immediately where supported.'
								: (data.data?.message || 'Saved.');
						}
					} else if (statusEl) {
						statusEl.textContent = 'Error: ' + (data.data?.message || 'Could not save fix inputs.');
					}
				})
				.catch(() => {
					if (statusEl) {
						statusEl.textContent = 'Error: Network request failed';
					}
				})
				.finally(() => {
					button.disabled = false;
				});
		},

		/**
		 * Save treatment auto-apply preference for this diagnostic.
		 */
		toggleTreatmentPreference: function(control) {
			const className = control?.getAttribute('data-class-name');
			const nonce = control?.getAttribute('data-nonce');
			if (!className || !nonce) return;

			const enable = control.checked ? '1' : '0';
			const statusEl = document.getElementById('wps-treatment-toggle-status');

			control.disabled = true;
			if (statusEl) {
				statusEl.textContent = 'Saving treatment preference...';
			}

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'thisismyurl_shadow_toggle_treatment',
					nonce: nonce,
					class_name: className,
					enable: enable,
				})
			})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						if (statusEl) {
							statusEl.textContent = control.checked
								? 'Auto-apply is enabled for this fix.'
								: 'Auto-apply is disabled for this fix.';
						}
					} else {
						control.checked = !control.checked;
						if (statusEl) {
							statusEl.textContent = 'Error: ' + (data.data?.message || 'Could not save preference.');
						}
					}
				})
				.catch(() => {
					control.checked = !control.checked;
					if (statusEl) {
						statusEl.textContent = 'Error: Network request failed';
					}
				})
				.finally(() => {
					control.disabled = false;
				});
		},

		/**
		 * Save diagnostic frequency preference
		 */
		saveFrequency: function() {
			const select = document.getElementById('wps-frequency-select');
			const className = select?.getAttribute('data-class-name');
			const nonce = select?.getAttribute('data-nonce');
			const frequency = select?.value;

			if (!className || !nonce || !frequency) return;

			const button = document.getElementById('wps-save-frequency');
			button.disabled = true;

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'thisismyurl_shadow_save_diagnostic_frequency',
					nonce: nonce,
					class_name: className,
					frequency: frequency
				})
			})
				.then(res => res.json())
				.then(data => {
					const statusEl = document.getElementById('wps-frequency-status');
					if (data.success) {
						if (statusEl) {
							statusEl.textContent = data.data?.message || 'Schedule saved. Refreshing…';
						}
						this.reloadToLastAction( 700 );
					} else {
						if (statusEl) {
							statusEl.textContent = 'Error: ' + (data.data?.message || 'Failed');
						}
					}
				})
				.catch(err => {
					const statusEl = document.getElementById('wps-frequency-status');
					if (statusEl) {
						statusEl.textContent = 'Error: Network request failed';
					}
				})
				.finally(() => {
					button.disabled = false;
				});
		},

		// Cron-only diagnostic execution: manual run actions are intentionally removed.
	};

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => thisismyurlShadowUI.init());
	} else {
		thisismyurlShadowUI.init();
	}

	// Export for global access if needed
	window.thisismyurlShadowUI = thisismyurlShadowUI;
})();
