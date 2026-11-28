// The steps that put a screen in the state a screenshot shows (manifest `scene`). Each gets the Playwright page, after it loaded.
export const scenes = {
	// The editor with the whole certificate selected, as the bar over several elements shows it.
	'editor-select-all': async ( page ) => {
		await page.locator( '.evcert-stage' ).first().click( { position: { x: 5, y: 5 } } ).catch( () => {} );
		await page.keyboard.press( 'Meta+a' );
		await page.waitForTimeout( 600 );
	},
};
