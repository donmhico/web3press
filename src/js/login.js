(($) => {
	if ( ! window.ethereum ) {
		console.log( 'No metamask installed' );
		return;
	}

	// TODO - Make sure #loginform is present.
	$('<div id="web3press-login-wrapper"><button id="web3press-login-btn" class="button button-large">Login with MetaMask</button></div>').insertBefore('#loginform');

	$('#web3press-login-btn').on('click', on_click_web3press_btn_cb );

	const web3 = new Web3( window.ethereum );

	async function on_click_web3press_btn_cb() {
		console.log('on_click_web3press_btn_cb');
		const message   = 'I have read the terms and conditions.';
		const addresses = await web3.eth.requestAccounts();
		const signature = await web3.eth.personal.sign(message, addresses[0]);

		var data = {
			'action': 'web3_validate_signature',
			'address': addresses[0],
			'message': message,
			'signature': signature
		};

		$.post( _wpUtilSettings.ajax.url, data, function( response ) {
			if ( ! response.success ) {
				// TODO show errors.
				return;
			}

			if ( response.data.action && 'redirect' === response.data.action ) {
				window.location.replace( response.data.url );
			}
			console.log(response);
		} );
	}
})(jQuery)