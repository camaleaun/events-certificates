/**
 * Whether the dark colors are offered: the "Light and dark" choice of Settings → Certificate colors (window.evcertEditor.darkMode, from
 * includes/admin-app.php). Off by default: a certificate is light, as the PDF always is. Without the setting (tests) the editor offers both.
 * The dark values already saved in a design are kept either way.
 */
export function darkEnabled(): boolean {
	const config = typeof window !== 'undefined' ? ( window as unknown as { evcertEditor?: { darkMode?: boolean } } ).evcertEditor : undefined;
	return config?.darkMode !== false;
}
