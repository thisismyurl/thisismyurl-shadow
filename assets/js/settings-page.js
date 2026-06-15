/**
 * This Is My URL Shadow Settings Page JavaScript
 *
 * Handles:
 * - Auto-save of general settings (toggles, selects) via thisismyurl_shadow_save_setting
 * - Scan config saves via thisismyurl_shadow_save_scan_config
 * - Diagnostic toggle via thisismyurl_shadow_toggle_diagnostic
 * - Diagnostic frequency override via thisismyurl_shadow_save_diagnostic_frequency
 * - Diagnostics table filtering and searching
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6093.1200
 */
/* global thisismyurlShadowSettingsData, jQuery */

( function ( $ ) {
	'use strict';

	var WPSSettings = {

		/**
		 * Pending debounce timers keyed by element ID.
		 */
		timers: {},

		/**
		 * Boot the settings page.
		 */
		init: function () {
			this.bindAutoSave();
			this.bindScanConfig();
			this.bindDiagnosticToggles();
			this.bindDiagnosticFrequency();
			this.bindDiagnosticFilters();
			this.syncScanTimeVisibility();
			this.bindGovernanceReport();
		},

		getErrorMessage: function ( response ) {
			if ( response && response.data && response.data.message ) {
				return response.data.message;
			}

			return thisismyurlShadowSettingsData.i18n.saveError;
		},

		sendRequest: function ( data, onSuccess, onError ) {
			$.ajax( {
				url:  thisismyurlShadowSettingsData.ajaxUrl,
				type: 'POST',
				data: data,
				success: function ( response ) {
					if ( response && response.success ) {
						if ( 'function' === typeof onSuccess ) {
							onSuccess( response );
						}
						return;
					}

					if ( 'function' === typeof onError ) {
						onError( WPSSettings.getErrorMessage( response ), response );
					}
				},
				error: function () {
					if ( 'function' === typeof onError ) {
						onError( thisismyurlShadowSettingsData.i18n.saveError );
					}
				}
			} );
		},

		// ────────────────────────────────────────────────────────────────────
		// Auto-save: generic thisismyurl_shadow_* options
		// ────────────────────────────────────────────────────────────────────

		bindAutoSave: function () {
			var self = this;
			$( document ).on( 'change', '.wps-auto-save', function () {
				var $el = $( this );
				self.saveOption( $el );
			} );
		},

		saveOption: function ( $el ) {
			var option = $el.data( 'option' );
			var type   = $el.data( 'type' ) || 'string';
			var value;

			if ( 'bool' === type ) {
				value = $el.is( ':checked' ) ? '1' : '0';
			} else if ( 'integer' === type ) {
				value = parseInt( $el.val(), 10 );
			} else {
				value = $el.val();
			}

			var $status = $el.closest( '.wps-settings-row-control' ).find( '.wps-save-status' );
			this.setSaving( $status );

			this.sendRequest(
				{
					action: 'thisismyurl_shadow_save_setting',
					nonce:  thisismyurlShadowSettingsData.adminNonce,
					option: option,
					value:  value
				},
				function () {
					WPSSettings.setSaved( $status );
				},
				function ( msg ) {
					WPSSettings.setError( $status, msg );
				}
			);
		},

		// ────────────────────────────────────────────────────────────────────
		// Scan config saves
		// ────────────────────────────────────────────────────────────────────

		bindScanConfig: function () {
			var self = this;

			$( document ).on( 'change', '.wps-save-scan-config', function () {
				var $el  = $( this );
				var type = $el.data( 'type' ) || 'string';
				var key  = $el.data( 'key' );
				var value;

				if ( 'bool' === type ) {
					value = $el.is( ':checked' ) ? '1' : '0';
				} else {
					value = $el.val();
				}

				var $status = $el.closest( '.wps-settings-row-control' ).find( '.wps-save-status' );
				self.setSaving( $status );

				this.sendRequest(
					{
						action: 'thisismyurl_shadow_save_scan_config',
						nonce:  thisismyurlShadowSettingsData.adminNonce,
						key:    key,
						value:  value
					},
					function () {
						WPSSettings.setSaved( $status );

						// Show/hide the scan-time row when frequency changes.
						if ( 'frequency' === key ) {
							WPSSettings.syncScanTimeVisibility();
						}
					},
					function ( msg ) {
						WPSSettings.setError( $status, msg );
					}
				);
			} );
		},

		syncScanTimeVisibility: function () {
			var $freqSelect = $( '#wps-scan-frequency' );
			var $timeRow    = $( '#wps-scan-time-row' );

			if ( ! $freqSelect.length || ! $timeRow.length ) {
				return;
			}

			if ( 'manual' === $freqSelect.val() ) {
				$timeRow.hide();
			} else {
				$timeRow.show();
			}
		},

		// ────────────────────────────────────────────────────────────────────
		// Diagnostic enable/disable toggles
		// ────────────────────────────────────────────────────────────────────

		bindDiagnosticToggles: function () {
			$( document ).on( 'change', '.wps-diag-toggle', function () {
				var $cb        = $( this );
					var className  = $cb.data( 'class-name' );
				var enable     = $cb.is( ':checked' );
				var $td        = $cb.closest( 'td' );
				var $status    = $td.find( '.wps-save-status' );
				var $row       = $cb.closest( 'tr' );

				WPSSettings.setSaving( $status );

				WPSSettings.sendRequest(
					{
						action:     'thisismyurl_shadow_toggle_diagnostic',
						nonce:      thisismyurlShadowSettingsData.scanSettingsNonce,
						class_name: className,
						enable:     enable ? '1' : '0'
					},
					function () {
						WPSSettings.setSaved( $status );
						if ( enable ) {
							$row.removeClass( 'wps-diag-row--disabled' );
							$row.attr( 'data-enabled', 'enabled' );
						} else {
							$row.addClass( 'wps-diag-row--disabled' );
							$row.attr( 'data-enabled', 'disabled' );
						}
					},
					function ( msg ) {
						$cb.prop( 'checked', ! enable );
						WPSSettings.setError( $status, msg );
					}
				);
			} );
		},

		// ────────────────────────────────────────────────────────────────────
		// Diagnostic frequency overrides
		// ────────────────────────────────────────────────────────────────────

		bindDiagnosticFrequency: function () {
			$( document ).on( 'change', '.wps-diag-freq-select', function () {
				var $sel      = $( this );
					var className = $sel.data( 'class-name' );
				var frequency = $sel.val();
				var $status   = $sel.closest( 'td' ).find( '.wps-save-status' );

				WPSSettings.setSaving( $status );

				WPSSettings.sendRequest(
					{
						action:     'thisismyurl_shadow_save_diagnostic_frequency',
						nonce:      thisismyurlShadowSettingsData.scanSettingsNonce,
						class_name: className,
						frequency:  frequency
					},
					function () {
						WPSSettings.setSaved( $status );
					},
					function ( msg ) {
						WPSSettings.setError( $status, msg );
					}
				);
			} );
		},

		// ────────────────────────────────────────────────────────────────────
		// Diagnostics table filtering
		// ────────────────────────────────────────────────────────────────────

		bindDiagnosticFilters: function () {
			var self = this;

			$( '#wps-diag-search' ).on( 'input', function () {
				clearTimeout( self.timers.search );
				self.timers.search = setTimeout( function () {
					WPSSettings.filterDiagnostics();
				}, 200 );
			} );

			$( '#wps-diag-family-filter, #wps-diag-status-filter' ).on( 'change', function () {
				WPSSettings.filterDiagnostics();
			} );
		},

		filterDiagnostics: function () {
			var search   = ( $( '#wps-diag-search' ).val() || '' ).toLowerCase().trim();
			var family   = $( '#wps-diag-family-filter' ).val() || '';
			var status   = $( '#wps-diag-status-filter' ).val() || '';
			var $rows    = $( '#wps-diagnostics-table tbody .wps-diag-row' );
			var visible  = 0;

			$rows.each( function () {
				var $row       = $( this );
				var rowFamily  = $row.data( 'family' ) || '';
				var rowStatus  = $row.data( 'enabled' ) || '';
				var rowText    = $row.text().toLowerCase();

				var matchSearch = ! search  || rowText.indexOf( search )  > -1;
				var matchFamily = ! family  || rowFamily === family;
				var matchStatus = ! status  || rowStatus === status;

				if ( matchSearch && matchFamily && matchStatus ) {
					$row.show();
					visible++;
				} else {
					$row.hide();
				}
			} );

			$( '#wps-diag-no-results' ).prop( 'hidden', visible > 0 );
		},

		bindGovernanceReport: function () {
			var self = this;
			var $report = $( '#wps-governance-report' );

			if ( ! $report.length ) {
				return;
			}

			self.refreshReadinessSummary();

			$( document ).on( 'click', '#wps-export-inventory-json', function ( event ) {
				event.preventDefault();
				self.exportInventory( 'json' );
			} );

			$( document ).on( 'click', '#wps-export-inventory-csv', function ( event ) {
				event.preventDefault();
				self.exportInventory( 'csv' );
			} );

			$( document ).on( 'click', '#wps-refresh-readiness-summary', function ( event ) {
				event.preventDefault();
				self.refreshReadinessSummary();
			} );

			$( document ).on( 'click', '.wps-readiness-section-toggle', function ( event ) {
				event.preventDefault();

				var $btn = $( this );
				var $content = $btn.nextAll( '.wps-readiness-section-content' ).first();
				var $arrow = $btn.find( '.wps-toggle-arrow' );
				var isVisible = $content.is( ':visible' );

				if ( isVisible ) {
					$content.slideUp( 200 );
					$btn.attr( 'aria-expanded', 'false' );
					$arrow.css( 'transform', '' );
					return;
				}

				var state = $content.data( 'state' );
				var $list = $content.find( '.wps-inventory-list' );
				if ( $list.text().indexOf( 'Loading...' ) !== -1 ) {
					self.loadInventoryForState( state, $list );
				}

				$content.slideDown( 200 );
				$btn.attr( 'aria-expanded', 'true' );
				$arrow.css( 'transform', 'rotate(180deg)' );
			} );
		},

		refreshReadinessSummary: function () {
			var nonce = thisismyurlShadowSettingsData.scanSettingsNonce || '';

			$.post( thisismyurlShadowSettingsData.ajaxUrl, {
				action: 'thisismyurl_shadow_readiness_inventory',
				nonce: nonce
			} ).done( function ( response ) {
				if ( response && response.success && response.data ) {
					var summary = response.data.summary || {};
					$( '[data-count-prod-diag]' ).text( summary.diagnostics?.production || 0 );
					$( '[data-count-beta-diag]' ).text( summary.diagnostics?.beta || 0 );
					$( '[data-count-planned-diag]' ).text( summary.diagnostics?.planned || 0 );
					$( '[data-count-prod-treat]' ).text( summary.treatments?.production || 0 );
					$( '[data-count-beta-treat]' ).text( summary.treatments?.beta || 0 );
					$( '[data-count-planned-treat]' ).text( summary.treatments?.planned || 0 );
				}
			} ).fail( function () {
				$( '#wps-export-status' )
					.text( thisismyurlShadowSettingsData.i18n.refreshFailed )
					.removeClass( 'wps-governance-status--success' )
					.addClass( 'wps-governance-status--error' );
			} );

			$.post( thisismyurlShadowSettingsData.ajaxUrl, {
				action: 'thisismyurl_shadow_treatment_maturity',
				nonce: nonce
			} ).done( function ( response ) {
				if ( response && response.success && response.data ) {
					var counts = response.data.counts || {};
					var byRisk = counts.by_risk || {};
					$( '[data-count-treat-shipped]' ).text( counts.shipped ?? '—' );
					$( '[data-count-treat-guidance]' ).text( counts.guidance ?? '—' );
					$( '[data-count-treat-reversible]' ).text( counts.reversible ?? '—' );
					$( '[data-count-treat-safe]' ).text( byRisk.safe ?? '—' );
					$( '[data-count-treat-moderate]' ).text( byRisk.moderate ?? '—' );
					$( '[data-count-treat-high]' ).text( byRisk.high ?? '—' );
					$( '[data-count-treat-guidance-risk]' ).text( byRisk.guidance ?? '—' );
				}
			} );
		},

		loadInventoryForState: function ( state, $list ) {
			$.post( thisismyurlShadowSettingsData.ajaxUrl, {
				action: 'thisismyurl_shadow_readiness_inventory',
				nonce: thisismyurlShadowSettingsData.scanSettingsNonce || ''
			} ).done( function ( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					$list.html( '<em class="wps-inventory-message wps-inventory-message--error">' + thisismyurlShadowSettingsData.i18n.inventoryLoadFailed + '</em>' );
					return;
				}

				var inventory = response.data.inventory || {};
				var items = [];

				if ( inventory.diagnostics ) {
					$.each( inventory.diagnostics, function ( idx, diag ) {
						if ( ( diag.state || 'production' ) === state ) {
							items.push( {
								type: 'Diagnostic',
								name: diag.class || 'Unknown',
								file: diag.file || ''
							} );
						}
					} );
				}

				if ( inventory.treatments ) {
					$.each( inventory.treatments, function ( idx, treat ) {
						if ( ( treat.state || 'production' ) === state ) {
							items.push( {
								type: 'Treatment',
								name: treat.class || 'Unknown',
								file: treat.file || ''
							} );
						}
					} );
				}

				if ( ! items.length ) {
					$list.html( '<em class="wps-inventory-message">' + thisismyurlShadowSettingsData.i18n.noItemsFound + '</em>' );
					return;
				}

				var html = '<ul class="wps-inventory-items">';
				$.each( items, function ( idx, item ) {
					html += '<li class="wps-inventory-item">';
					html += '<strong>' + item.type + ':</strong> ' + $( '<div/>' ).text( item.name ).html();
					if ( item.file ) {
						html += '<br><small class="wps-inventory-file">' + $( '<div/>' ).text( item.file ).html() + '</small>';
					}
					html += '</li>';
				} );
				html += '</ul>';
				$list.html( html );
			} ).fail( function () {
				$list.html( '<em class="wps-inventory-message wps-inventory-message--error">' + thisismyurlShadowSettingsData.i18n.inventoryLoadFailed + '</em>' );
			} );
		},

		exportInventory: function ( format ) {
			var $status = $( '#wps-export-status' );
			$status
				.text( thisismyurlShadowSettingsData.i18n.exporting )
				.removeClass( 'wps-governance-status--error wps-governance-status--success' );

			var formData = new FormData();
			formData.append( 'action', 'thisismyurl_shadow_export_readiness_inventory' );
			formData.append( 'format', format );
			formData.append( 'nonce', thisismyurlShadowSettingsData.scanSettingsNonce || '' );

			fetch( thisismyurlShadowSettingsData.ajaxUrl, {
				method: 'POST',
				body: formData
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( thisismyurlShadowSettingsData.i18n.exportFailed );
				}

				return response.blob().then( function ( blob ) {
					var fileName = 'thisismyurl-shadow-readiness-inventory-' + new Date().toISOString().substring( 0, 10 ) + '.' + format;
					var url = window.URL.createObjectURL( blob );
					var link = document.createElement( 'a' );
					link.href = url;
					link.download = fileName;
					document.body.appendChild( link );
					link.click();
					document.body.removeChild( link );
					window.URL.revokeObjectURL( url );
					$status
						.text( thisismyurlShadowSettingsData.i18n.exportComplete )
						.removeClass( 'wps-governance-status--error' )
						.addClass( 'wps-governance-status--success' );
				} );
			} ).catch( function () {
				$status
					.text( thisismyurlShadowSettingsData.i18n.exportFailed )
					.removeClass( 'wps-governance-status--success' )
					.addClass( 'wps-governance-status--error' );
			} );
		},

		// ────────────────────────────────────────────────────────────────────
		// Status helpers
		// ────────────────────────────────────────────────────────────────────

		setSaving: function ( $status ) {
			$status
				.removeClass( 'is-saved is-error' )
				.addClass( 'is-saving' )
				.text( thisismyurlShadowSettingsData.i18n.saving );
		},

		setSaved: function ( $status ) {
			$status
				.removeClass( 'is-saving is-error' )
				.addClass( 'is-saved' )
				.text( thisismyurlShadowSettingsData.i18n.saved );

			clearTimeout( $status.data( 'clearTimer' ) );
			$status.data(
				'clearTimer',
				setTimeout( function () {
					$status.removeClass( 'is-saved' ).text( '' );
				}, 2500 )
			);
		},

		setError: function ( $status, message ) {
			$status
				.removeClass( 'is-saving is-saved' )
				.addClass( 'is-error' )
				.text( message || thisismyurlShadowSettingsData.i18n.saveError );
		}
	};

	$( function () {
		WPSSettings.init();
	} );

} )( jQuery );
