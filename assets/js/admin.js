/**
 * 九流 - AI 摘要插件 后台 JS
 * 三级联动 + Ajax 测试 + 编辑页生成 + 缓存管理
 */
( function ( $ ) {
	'use strict';

	var WPAIAS = window.WPAIAS_ADMIN || {};
	var providers = WPAIAS.providers || {};
	var i18n = WPAIAS.i18n || {};

	function init() {
		bindProviderChange();
		bindToggleKey();
		bindTestConnection();
		bindCacheActions();
		bindMetaBoxActions();
		// 初始化模型列表。
		var $prov = $( '#wpaias-provider' );
		if ( $prov.length ) {
			loadModelOptions( $prov.val(), true );
			toggleCustomRows( $prov.val() );
		}
	}

	/**
	 * 二级联动：服务商 → 模型。
	 */
	function loadModelOptions( providerKey, useCurrent ) {
		var $sel = $( '#wpaias-model' );
		if ( ! $sel.length ) return;

		var current = useCurrent ? ( $sel.data( 'current' ) || '' ) : '';
		$sel.empty();

		var p = providers[ providerKey ];
		if ( ! p ) return;

		if ( providerKey === 'custom' ) {
			// 自定义模式：隐藏模型下拉。
			return;
		}

		var models = p.models || [];
		if ( models.length === 0 ) {
			$sel.append( $( '<option/>' ).val( '' ).text( '（无可用模型）' ) );
			return;
		}

		$.each( models, function ( i, m ) {
			$sel.append( $( '<option/>' ).val( m ).text( m ) );
		} );

		// 复原已选。
		if ( current && models.indexOf( current ) !== -1 ) {
			$sel.val( current );
		}
	}

	function toggleCustomRows( providerKey ) {
		var $modelRow  = $( '#wpaias-model-row' );
		var $cep       = $( '#wpaias-custom-endpoint-row' );
		var $cmodel    = $( '#wpaias-custom-model-row' );

		if ( providerKey === 'custom' ) {
			$modelRow.hide();
			$cep.show();
			$cmodel.show();
		} else {
			$modelRow.show();
			$cep.hide();
			$cmodel.hide();
		}
	}

	function bindProviderChange() {
		$( document ).on( 'change', '#wpaias-provider', function () {
			var key = $( this ).val();
			loadModelOptions( key, false );
			toggleCustomRows( key );
		} );
	}

	function bindToggleKey() {
		$( document ).on( 'click', '#wpaias-toggle-key', function ( e ) {
			e.preventDefault();
			var $i = $( '#wpaias-api-key' );
			$i.attr( 'type', $i.attr( 'type' ) === 'password' ? 'text' : 'password' );
		} );
	}

	function bindTestConnection() {
		$( document ).on( 'click', '#wpaias-test-conn', function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			var $res = $( '#wpaias-test-result' );

			$res.removeClass( 'ok fail' ).text( i18n.testing || '...' );
			$btn.prop( 'disabled', true );

			var data = {
				action: 'wpaias_test_connection',
				nonce: WPAIAS.nonce,
				provider: $( '#wpaias-provider' ).val(),
				model: $( '#wpaias-model' ).val() || $( '#wpaias-custom-model' ).val(),
				api_key: $( '#wpaias-api-key' ).val(),
				endpoint: $( '#wpaias-custom-endpoint' ).val(),
				custom_model: $( '#wpaias-custom-model' ).val()
			};

			$.post( WPAIAS.ajax_url, data )
				.done( function ( resp ) {
					if ( resp && resp.success ) {
						$res.addClass( 'ok' ).text( ( i18n.test_ok || 'OK' ) + ' ' + ( resp.data.sample || '' ) );
					} else {
						$res.addClass( 'fail' ).text( ( i18n.test_fail || 'fail: ' ) + ( resp && resp.data && resp.data.message ? resp.data.message : 'unknown' ) );
					}
				} )
				.fail( function ( xhr ) {
					$res.addClass( 'fail' ).text( ( i18n.test_fail || 'fail: ' ) + ( xhr.status + ' ' + xhr.statusText ) );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	function bindCacheActions() {
		$( document ).on( 'click', '#wpaias-clear-all', function ( e ) {
			e.preventDefault();
			if ( ! window.confirm( i18n.confirm || 'Confirm?' ) ) return;
			var $btn = $( this );
			var $res = $( '#wpaias-clear-all-result' );
			$btn.prop( 'disabled', true );
			$res.removeClass( 'ok fail' ).text( '...' );
			$.post( WPAIAS.ajax_url, {
				action: 'wpaias_clear_all_cache',
				nonce: WPAIAS.nonce
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					$res.addClass( 'ok' ).text( resp.data.message || ( i18n.cleared || 'cleared' ) );
					$( '#wpaias-cache-count' ).text( '0' );
				} else {
					$res.addClass( 'fail' ).text( resp && resp.data && resp.data.message ? resp.data.message : 'fail' );
				}
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );

		$( document ).on( 'click', '#wpaias-clear-by-id', function ( e ) {
			e.preventDefault();
			var id = parseInt( $( '#wpaias-clear-id' ).val(), 10 ) || 0;
			if ( ! id ) return;
			var $btn = $( this );
			var $res = $( '#wpaias-clear-id-result' );
			$btn.prop( 'disabled', true );
			$res.removeClass( 'ok fail' ).text( '...' );
			$.post( WPAIAS.ajax_url, {
				action: 'wpaias_clear_cache_by_id',
				nonce: WPAIAS.nonce,
				post_id: id
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					$res.addClass( 'ok' ).text( resp.data.message || ( i18n.cleared || 'cleared' ) );
					if ( typeof resp.data.count !== 'undefined' ) {
						$( '#wpaias-cache-count' ).text( resp.data.count );
					}
				} else {
					$res.addClass( 'fail' ).text( resp && resp.data && resp.data.message ? resp.data.message : 'fail' );
				}
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	function bindMetaBoxActions() {
		function doGen( $box, force ) {
			var pid = parseInt( $box.data( 'post-id' ), 10 ) || 0;
			if ( ! pid ) return;
			var $res = $box.find( '.wpaias-mb-result' );
			$res.show().removeClass( 'ok fail' ).text( i18n.generating || '...' );

			$.post( WPAIAS.ajax_url, {
				action: 'wpaias_generate_summary',
				nonce: WPAIAS.nonce,
				post_id: pid,
				force: force ? 1 : 0
			} ).done( function ( resp ) {
				if ( resp && resp.success ) {
					$res.addClass( 'ok' ).text( resp.data.message || ( i18n.gen_ok || 'ok' ) );
					$box.find( '.wpaias-mb-status' ).removeClass( 'no' ).addClass( 'ok' ).text( '● ' + ( i18n.gen_ok || '已生成' ) );
					$box.find( '.wpaias-mb-preview' ).html(
						'<small style="display:block;margin-top:8px;color:#888;">预览：</small>' +
						'<div class="wpaias-mb-text"></div>'
					);
					$box.find( '.wpaias-mb-text' ).text( ( resp.data.summary || '' ).substring( 0, 200 ) );
				} else {
					$res.addClass( 'fail' ).text( ( i18n.gen_fail || 'fail: ' ) + ( resp && resp.data && resp.data.message ? resp.data.message : 'unknown' ) );
				}
			} ).fail( function () {
				$res.addClass( 'fail' ).text( i18n.gen_fail || 'fail' );
			} );
		}

		$( document ).on( 'click', '.wpaias-mb-gen', function ( e ) {
			e.preventDefault();
			doGen( $( this ).closest( '.wpaias-mb' ), false );
		} );

		$( document ).on( 'click', '.wpaias-mb-regen', function ( e ) {
			e.preventDefault();
			if ( ! window.confirm( i18n.confirm || 'Confirm?' ) ) return;
			doGen( $( this ).closest( '.wpaias-mb' ), true );
		} );

		$( document ).on( 'click', '.wpaias-mb-clear', function ( e ) {
			e.preventDefault();
			var $box = $( this ).closest( '.wpaias-mb' );
			var pid  = parseInt( $box.data( 'post-id' ), 10 ) || 0;
			if ( ! pid ) return;
			$.post( WPAIAS.ajax_url, {
				action: 'wpaias_clear_post_cache',
				nonce: WPAIAS.nonce,
				post_id: pid
			} ).done( function ( resp ) {
				var $res = $box.find( '.wpaias-mb-result' );
				$res.show();
				if ( resp && resp.success ) {
					$res.removeClass( 'fail' ).addClass( 'ok' ).text( resp.data.message );
					$box.find( '.wpaias-mb-status' ).removeClass( 'ok' ).addClass( 'no' ).text( '○ 暂无缓存' );
					$box.find( '.wpaias-mb-preview' ).empty();
				} else {
					$res.removeClass( 'ok' ).addClass( 'fail' ).text( resp && resp.data && resp.data.message ? resp.data.message : 'fail' );
				}
			} );
		} );
	}

	$( init );
} )( jQuery );
