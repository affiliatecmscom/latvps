/*
 * Compare (LAT Review) , single post.
 * Chèn checkbox "Compare" vào mỗi product card (.acms-list__item[data-asin]),
 * cho chọn MIN..MAX sản phẩm rồi sang /compare/?ids=ASIN,ASIN...
 * Config từ mu-plugin: ACMS_CMP { base, min, max }.
 */
( function () {
	'use strict';
	if ( typeof ACMS_CMP === 'undefined' || ! ACMS_CMP.base ) {
		return;
	}
	var MIN  = parseInt( ACMS_CMP.min, 10 ) || 2;
	var MAX  = parseInt( ACMS_CMP.max, 10 ) || 4;
	var BASE = ACMS_CMP.base;

	var items = Array.prototype.slice.call(
		document.querySelectorAll( '.acms-list__item[data-asin]' )
	).filter( function ( it ) {
		return ( it.getAttribute( 'data-asin' ) || '' ).trim() !== '';
	} );
	if ( items.length < MIN ) {
		return;
	}

	var selected = [];

	// Thanh gom nổi
	var bar = document.createElement( 'div' );
	bar.className = 'acms-cmp-bar';
	bar.hidden = true;
	bar.innerHTML =
		'<span class="acms-cmp-bar__count"></span>' +
		'<button type="button" class="acms-cmp-bar__go btn btn--primary">Compare</button>' +
		'<button type="button" class="acms-cmp-bar__clear">Clear</button>';
	document.body.appendChild( bar );
	var countEl = bar.querySelector( '.acms-cmp-bar__count' );
	var goBtn   = bar.querySelector( '.acms-cmp-bar__go' );
	var clearEl = bar.querySelector( '.acms-cmp-bar__clear' );

	function refresh() {
		bar.hidden = selected.length === 0;
		countEl.textContent = selected.length + ' of ' + MAX + ' selected';
		goBtn.disabled = selected.length < MIN;
		items.forEach( function ( it ) {
			var cb = it.querySelector( '.acms-cmp-check input' );
			if ( cb && ! cb.checked ) {
				cb.disabled = selected.length >= MAX;
			}
		} );
	}

	items.forEach( function ( it ) {
		var asin = it.getAttribute( 'data-asin' ).trim();
		var label = document.createElement( 'label' );
		label.className = 'acms-cmp-check';
		label.innerHTML = '<input type="checkbox" value="' + asin + '"><span>Compare</span>';
		// Đặt DƯỚI CÙNG list nút "Buy on..." (.acms-list__action) để không che giá/giảm giá.
		var action = it.querySelector( '.acms-list__action' ) || it;
		action.appendChild( label );

		var cb = label.querySelector( 'input' );
		cb.addEventListener( 'change', function () {
			if ( cb.checked ) {
				if ( selected.length >= MAX ) {
					cb.checked = false;
					return;
				}
				if ( selected.indexOf( asin ) === -1 ) {
					selected.push( asin );
				}
			} else {
				var i = selected.indexOf( asin );
				if ( i > -1 ) {
					selected.splice( i, 1 );
				}
			}
			it.classList.toggle( 'is-cmp-selected', cb.checked );
			refresh();
		} );
	} );

	goBtn.addEventListener( 'click', function () {
		if ( selected.length >= MIN ) {
			window.location.href = BASE + '?ids=' + selected.join( ',' );
		}
	} );

	clearEl.addEventListener( 'click', function () {
		selected = [];
		items.forEach( function ( it ) {
			var cb = it.querySelector( '.acms-cmp-check input' );
			if ( cb ) {
				cb.checked = false;
				cb.disabled = false;
			}
			it.classList.remove( 'is-cmp-selected' );
		} );
		refresh();
	} );
} )();
