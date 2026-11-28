/**
 * The icons of the elements of a certificate (the blocks of its design: docs/decisions/0047), drawn here as the icons of the block editor are:
 * 24 x 24, a 1.5 stroke in the current color, so they sit with the WordPress icons around them (the inserter, the layers, the toolbar and the
 * sidebar). The WordPress icon library has none for a signature, a QR code with its code, or a drawing of filled paths; for the rest its icons
 * (title, square, image, code, grid) stood for an element, and these say what the element is.
 */
import { Circle, Path, Rect, SVG } from '@wordpress/components';
import { calendar as calendarIcon, group as groupIcon, image as imageIcon, paragraph as paragraphIcon, separator as separatorIcon, square as squareIcon } from '@wordpress/icons';
import type { Element } from './model';

const stroke = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' } as const;
const solid = { fill: 'currentColor' } as const;

const icon = ( ...children: JSX.Element[] ) => (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		{ children }
	</SVG>
);

/** An ellipse. */
export const ellipse = icon( <Path key="a" d="M4.75 12a7.25 5.25 0 1 0 14.5 0 7.25 5.25 0 1 0-14.5 0z" { ...stroke } /> );

/** A shape (a path): a curve between two anchors, as a pen draws it. */
export const shape = icon(
	<Path key="a" d="M6 16.5c0-6.5 5.5-9 12-9" { ...stroke } />,
	<Rect key="b" x="3.75" y="15" width="3" height="3" { ...solid } />,
	<Rect key="c" x="17.25" y="6" width="3" height="3" { ...solid } />
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
export function elementIcon( type: Element[ 'type' ] | 'logo' | 'date-of-emission', slot?: string ) {
	if ( type === 'logo' || ( type === 'image' && slot === 'logo' ) ) {
		return logo;
	}
	if ( type === 'date-of-emission' || ( type === 'text' && slot === 'date-of-emission' ) ) {
		return calendarIcon;
	}
	return (
		{
			// The icons of the core blocks that are the same thing (Paragraph, Separator, Image); the others are ours.
			text: paragraphIcon,
			line: separatorIcon,
			rect: squareIcon,
			ellipse,
			path: shape,
			image: imageIcon,
			svg: drawing,
			// The icon of the core's Group block.
			group: groupIcon,
			signature,
			qr,
			verification,
		} as Record< string, JSX.Element >
	 )[ type ];
}

/** Cut: scissors (the icons of the block editor have none). */
export const cut = icon(
	<Path key="a" d="M7.5 15.25a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5zM7.5 4.25a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5zM9.4 8.4l9.1 9.6M9.4 15.6l9.1-9.6" { ...stroke } />
);

/**
 * The six alignments of an element to the edge or the center of its box: a line (the edge) and two bars that lean on it. They are not the justify
 * icons (those are the anchor) nor the align icons of text.
 */
export const alignEdge = {
	left: icon( <Path key="a" d="M4.75 4v16" { ...stroke } />, <Path key="b" d="M4.75 8.5h11" { ...stroke } />, <Path key="c" d="M4.75 15h7" { ...stroke } /> ),
	centerH: icon( <Path key="a" d="M12 4v16" { ...stroke } />, <Path key="b" d="M6.5 8.5h11" { ...stroke } />, <Path key="c" d="M8.5 15h7" { ...stroke } /> ),
	right: icon( <Path key="a" d="M19.25 4v16" { ...stroke } />, <Path key="b" d="M19.25 8.5h-11" { ...stroke } />, <Path key="c" d="M19.25 15h-7" { ...stroke } /> ),
	top: icon( <Path key="a" d="M4 4.75h16" { ...stroke } />, <Path key="b" d="M8.5 4.75v11" { ...stroke } />, <Path key="c" d="M15 4.75v7" { ...stroke } /> ),
	middleV: icon( <Path key="a" d="M4 12h16" { ...stroke } />, <Path key="b" d="M8.5 6.5v11" { ...stroke } />, <Path key="c" d="M15 8.5v7" { ...stroke } /> ),
	bottom: icon( <Path key="a" d="M4 19.25h16" { ...stroke } />, <Path key="b" d="M8.5 19.25v-11" { ...stroke } />, <Path key="c" d="M15 19.25v-7" { ...stroke } /> ),
};
