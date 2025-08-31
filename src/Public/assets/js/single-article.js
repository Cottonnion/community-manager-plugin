jQuery( document ).ready(
	function ($) {
		var $authorImg = $( '.author-avatar' );
		if ($authorImg.length === 0) {
			return;
		}

		// Click handler
		$authorImg.on(
			'click',
			function () {
				var $overlay = $( '<div class="author-lightbox-overlay"><img src="' + $( this ).attr( 'src' ) + '" /></div>' );
				$( 'body' ).append( $overlay );
				$overlay.fadeIn( 200 );

				// Close on overlay click
				$overlay.on(
					'click',
					function () {
						$overlay.fadeOut(
							200,
							function () {
								$overlay.remove();
							}
						);
					}
				);
			}
		);
	}
);
