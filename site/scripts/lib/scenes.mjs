// The steps that put a screen in the state a screenshot shows (manifest `scene`). Each gets the Playwright page, after it loaded.
export const scenes = {
	// The public page with its Meetup tutorial folded: the form and the preview are what the picture is about.
	'hide-tutorial': async ( page ) => {
		await page.locator( '#evcert-tutorial > summary' ).click();
		// No focus ring on the summary: the picture shows the page, not where the pointer was.
		await page.evaluate( () => document.activeElement?.blur() );
		await page.waitForTimeout( 400 );
	},
	// The editor with the inserter open on the left and the settings on the right, the Colors panel expanded.
	'editor-panels': async ( page ) => {
		await page.getByRole( 'button', { name: 'Add element' } ).click();
		const settings = page.getByRole( 'button', { name: 'Settings', exact: true } );
		if ( ( await settings.getAttribute( 'aria-pressed' ) ) !== 'true' ) {
			await settings.click();
		}
		const colors = page.locator( '.components-panel__body-title button', { hasText: 'Colors' } ).first();
		if ( ( await colors.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
			await colors.click();
		}
		await page.waitForTimeout( 700 );
	},
	// The editor with the whole certificate selected, as the bar over several elements shows it.
	'editor-select-all': async ( page ) => {
		await page.locator( '.evcert-stage' ).first().click( { position: { x: 5, y: 5 } } ).catch( () => {} );
		await page.keyboard.press( 'Meta+a' );
		await page.waitForTimeout( 600 );
	},
};
