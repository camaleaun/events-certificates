// The steps that put a screen in the state a screenshot shows (manifest `scene`). Each gets the Playwright page, after it loaded.
export const scenes = {
	// A list with the pointer on its first row, so the row actions (Certificates...) show.
	'hover-first-row': async ( page ) => {
		await page.locator( '#the-list tr' ).first().hover();
		await page.waitForTimeout( 500 );
	},
	// The public page with its Meetup tutorial folded: the form and the preview are what the picture is about.
	'hide-tutorial': async ( page ) => {
		await page.locator( '#evcert-tutorial > summary' ).click();
		// No focus ring on the summary: the picture shows the page, not where the pointer was.
		await page.evaluate( () => document.activeElement?.blur() );
		await page.waitForTimeout( 400 );
	},
	// The editor with the inserter open on the left and the settings on the right, the Colors panel expanded.
	'editor-panels': async ( page ) => {
		// The preview data of the design (Certificate tab): a workload of 2 hours, as the sample event has, not the 8 of the default.
		await page.getByRole( 'button', { name: 'Settings', exact: true } ).click();
		await page.locator( '.components-panel__body-title button', { hasText: 'Preview data' } ).click();
		// The rows are buttons that show the value: the workload is the one that says 8.
		await page.getByRole( 'button', { name: /^[28]$/ } ).click();
		await page.getByLabel( 'Workload (hours)' ).fill( '2' );
		await page.keyboard.press( 'Escape' );
		await page.locator( '.components-panel__body-title button', { hasText: 'Preview data' } ).click();
		await page.getByRole( 'button', { name: 'Settings', exact: true } ).click();
		// Saved on the demo site, so the panel says Saved and not Unsaved changes (the next run finds the 2 already there).
		const save = page.getByRole( 'button', { name: 'Save', exact: true } );
		if ( await save.isEnabled() ) {
			await save.click();
			await page.waitForTimeout( 2500 );
		}
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
