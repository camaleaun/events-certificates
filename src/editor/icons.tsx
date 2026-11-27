/**
 * The icons of the elements of a certificate (the blocks of its design: docs/decisions/0047), drawn here as the icons of the block editor are:
 * 24 x 24, a 1.5 stroke in the current color, so they sit with the WordPress icons around them (the inserter, the layers, the toolbar and the
 * sidebar). The WordPress icon library has none for a signature, a QR code with its code, or a drawing of filled paths; for the rest its icons
 * (title, square, image, code, grid) stood for an element, and these say what the element is.
 */
import { Circle, Path, Rect, SVG } from '@wordpress/components';
import type { Element } from './model';

const stroke = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' } as const;
const solid = { fill: 'currentColor' } as const;

const icon = ( ...children: JSX.Element[] ) => (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		{ children }
	</SVG>
);

/** A text: a T on its line. */
export const text = icon( <Path key="a" d="M5.5 6.75h13M12 6.75v10.5M9.5 17.25h5" { ...stroke } /> );

/** A line: a stroke between two ends. */
export const line = icon(
	<Path key="a" d="M6.5 17.5l11-11" { ...stroke } />,
	<Circle key="b" cx="5.5" cy="18.5" r="1.75" { ...solid } />,
	<Circle key="c" cx="18.5" cy="5.5" r="1.75" { ...solid } />
);

/** A rectangle. */
export const rect = icon( <Rect key="a" x="4.75" y="6.75" width="14.5" height="10.5" rx="1.5" { ...stroke } /> );

/** An ellipse. */
export const ellipse = icon( <Path key="a" d="M4.75 12a7.25 5.25 0 1 0 14.5 0 7.25 5.25 0 1 0-14.5 0z" { ...stroke } /> );

/** A shape (a path): a curve between two anchors, as a pen draws it. */
export const shape = icon(
	<Path key="a" d="M6 16.5c0-6.5 5.5-9 12-9" { ...stroke } />,
	<Rect key="b" x="3.75" y="15" width="3" height="3" { ...solid } />,
	<Rect key="c" x="17.25" y="6" width="3" height="3" { ...solid } />
);

/** An image: a frame, a hill and a sun. */
export const picture = icon(
	<Rect key="a" x="4.75" y="5.75" width="14.5" height="12.5" rx="1.5" { ...stroke } />,
	<Path key="b" d="M5.5 16.5l3.75-4.25 2.75 2.75 2-2 4.5 4" { ...stroke } />,
	<Circle key="c" cx="15.25" cy="9.75" r="1.25" { ...solid } />
);

/** A drawing of paths (an SVG): a shape between the brackets of code. */
export const drawing = icon(
	<Path key="a" d="M7.5 7L2.75 12l4.75 5M16.5 7l4.75 5-4.75 5" { ...stroke } />,
	<Path key="b" d="M12 8.25l3 3.75-3 3.75L9 12z" { ...solid } />
);

/** The place of a signature: a scribble over a line. */
export const signature = icon(
	<Path key="a" d="M4 19.25h16" { ...stroke } />,
	<Path key="b" d="M4.5 15c1.6-3.4 2.9-8.5 4.2-8.5.9 0 .3 3.7-.2 6.2-.3 1.5.4 1.6 1.2.4 1-1.5 1.8-3.4 2.9-3.4.8 0 .1 2.3.9 2.3 1.2 0 1.8-1.9 3.3-1.9 1 0 .6 1.5 1.5 1.5.9 0 1.5-1 2.2-1.6" { ...stroke } />
);

/** The QR code: its three finders and some modules. */
export const qr = icon(
	<Rect key="a" x="4.75" y="4.75" width="5.5" height="5.5" rx=".75" { ...stroke } />,
	<Rect key="b" x="13.75" y="4.75" width="5.5" height="5.5" rx=".75" { ...stroke } />,
	<Rect key="c" x="4.75" y="13.75" width="5.5" height="5.5" rx=".75" { ...stroke } />,
	<Path key="d" d="M7 7h1.25v1.25H7zM16 7h1.25v1.25H16zM7 16h1.25v1.25H7zM13.75 13.75H16v2.25h-2.25zM17.5 13.75h1.75V15.5H17.5zM13.75 17.5h1.75v1.75h-1.75zM17.25 17.25h2v2h-2z" { ...solid } />
);

/** The verification: the QR code, with the code and the hint beside it. */
export const verification = icon(
	<Rect key="a" x="4.75" y="6.75" width="6.5" height="6.5" rx=".75" { ...stroke } />,
	<Path key="b" d="M7.25 9.25h1.5v1.5h-1.5z" { ...solid } />,
	<Path key="c" d="M14 8h5.25M14 12h5.25M4.75 17h14.5" { ...stroke } />
);

/** The community's logo: a badge with its ribbons (the place where its picture goes). */
export const logo = icon(
	<Circle key="a" cx="12" cy="9.5" r="5" { ...stroke } />,
	<Path key="b" d="M9 13.75l-1.75 5.5 4.75-2 4.75 2-1.75-5.5" { ...stroke } />
);

/** The icon of an element (its type, and the slot that makes an image the community's logo). */
export function elementIcon( type: Element[ 'type' ] | 'logo', slot?: string ) {
	if ( type === 'logo' || ( type === 'image' && slot === 'logo' ) ) {
		return logo;
	}
	return (
		{
			text,
			line,
			rect,
			ellipse,
			path: shape,
			image: picture,
			svg: drawing,
			signature,
			qr,
			verification,
		} as Record< string, JSX.Element >
	 )[ type ];
}
