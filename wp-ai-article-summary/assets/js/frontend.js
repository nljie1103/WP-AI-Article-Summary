/**
 * 九流 - AI 摘要插件 · 前端 JS
 * 入场动画 / 打字机效果 / 首次访问 Ajax 拉取
 */
( function () {
	'use strict';

	var WPAIAS = window.WPAIAS_FRONT || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var nodes = document.querySelectorAll( '.wpaias-summary' );
		if ( ! nodes.length ) {
			return;
		}
		nodes.forEach( function ( box ) {
			initBox( box );
		} );
	} );

	function initBox( box ) {
		var state = box.getAttribute( 'data-state' );
		var delay = parseInt( box.getAttribute( 'data-delay' ), 10 ) || 0;

		if ( 'ready' === state ) {
			// 已有缓存，直接动画。
			setTimeout( function () {
				playAnimation( box );
			}, delay );
			return;
		}

		// 否则 Ajax 拉取。
		fetchSummary( box );
	}

	function fetchSummary( box ) {
		if ( ! WPAIAS.ajax_url || ! WPAIAS.nonce ) {
			showError( box, 'Missing config.' );
			return;
		}
		var pid = parseInt( box.getAttribute( 'data-post-id' ), 10 ) || 0;
		if ( ! pid ) return;

		var form = new FormData();
		form.append( 'action', 'wpaias_front_generate' );
		form.append( 'nonce', WPAIAS.nonce );
		form.append( 'post_id', pid );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', WPAIAS.ajax_url, true );
		xhr.onreadystatechange = function () {
			if ( 4 !== xhr.readyState ) return;
			try {
				var resp = JSON.parse( xhr.responseText );
				if ( resp && resp.success && resp.data && resp.data.summary ) {
					injectSummary( box, resp.data.summary );
				} else {
					var msg = ( resp && resp.data && resp.data.message ) ? resp.data.message : 'AI 摘要生成失败';
					showError( box, msg );
				}
			} catch ( e ) {
				showError( box, '响应解析失败' );
			}
		};
		xhr.send( form );
	}

	function injectSummary( box, summary ) {
		var placeholder = box.querySelector( '.wpaias-summary__placeholder' );
		var text        = box.querySelector( '.wpaias-summary__text' );
		if ( placeholder ) placeholder.style.display = 'none';
		if ( text ) {
			text.removeAttribute( 'data-pending' );
			text.textContent = summary;
		}
		box.setAttribute( 'data-state', 'ready' );

		var delay = parseInt( box.getAttribute( 'data-delay' ), 10 ) || 0;
		setTimeout( function () {
			playAnimation( box );
		}, delay );
	}

	function showError( box, msg ) {
		var placeholder = box.querySelector( '.wpaias-summary__placeholder' );
		if ( placeholder ) {
			placeholder.innerHTML = '<span class="wpaias-summary__loading-text" style="color:#a55;">' + escapeHtml( msg ) + '</span>';
		}
		box.setAttribute( 'data-state', 'error' );
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ];
		} );
	}

	/**
	 * 入场动画分发。
	 */
	function playAnimation( box ) {
		var anim = box.getAttribute( 'data-anim' ) || 'none';
		var text = box.querySelector( '.wpaias-summary__text' );

		// 已通过 CSS 类自动产生入场效果，typewriter 单独处理。
		box.classList.add( 'wpaias-anim-active' );

		if ( anim === 'typewriter' && text ) {
			runTypewriter( box, text );
		} else if ( anim === 'line-fade' && text ) {
			runLineFade( box, text );
		}
	}

	/**
	 * 打字机效果。
	 */
	function runTypewriter( box, text ) {
		var speed   = parseInt( box.getAttribute( 'data-speed' ), 10 ) || 35;
		var cursor  = parseInt( box.getAttribute( 'data-cursor' ), 10 ) || 0;
		var content = text.textContent || '';
		text.textContent = '';
		text.classList.add( 'wpaias-typing' );

		if ( cursor ) {
			text.classList.add( 'wpaias-with-cursor' );
		}

		var i = 0;
		function step() {
			if ( i >= content.length ) {
				// 完成后光标消失。
				text.classList.remove( 'wpaias-with-cursor' );
				text.classList.remove( 'wpaias-typing' );
				text.classList.add( 'wpaias-typed' );
				return;
			}
			text.textContent = content.substring( 0, i + 1 );
			i++;
			window.setTimeout( step, speed );
		}
		step();
	}

	/**
	 * 逐行渐入。
	 */
	function runLineFade( box, text ) {
		var raw = text.textContent || '';
		var lines = raw.split( /[\n。！？!?]/ ).filter( function ( s ) { return s.trim().length > 0; } );

		if ( lines.length <= 1 ) {
			lines = raw.split( /(?<=[，,；;])/ ).filter( function ( s ) { return s.trim().length > 0; } );
		}
		if ( lines.length === 0 ) lines = [ raw ];

		text.innerHTML = '';
		lines.forEach( function ( line, idx ) {
			var span = document.createElement( 'span' );
			span.className = 'wpaias-line';
			span.style.animationDelay = ( idx * 0.12 ) + 's';
			span.textContent = line;
			text.appendChild( span );
		} );
	}

} )();
