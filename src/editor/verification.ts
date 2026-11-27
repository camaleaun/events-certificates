/**
 * The verification element: the QR code, the code and the hint as one element, laid out as a stack (the code under
 * the QR code, the hint under the code) or as a row (the text beside the QR code), aligned left, center or right.
 *
 * The same arithmetic as Render\verification_parts() in includes/render.php: the editor draws what the PDF draws.
 */
import type { Align, VerificationEl } from './model';

export type PartBox = { kind: 'qr' | 'code' | 'hint'; x: number; y: number; w: number; h: number; align: Align; size: number; weight: number; color: string; lh: number };

/** The parts of a verification element, as boxes on the page. */
export function verificationParts( el: VerificationEl ): PartBox[] {
	const x = el.x;
	const y = el.y;
	const w = Math.max( 20, el.w );
	const q = Math.max( 8, el.qr ?? 44 );
	const gap = Math.max( 0, el.gap ?? 2 );
	const arrange = el.arrange === 'row' ? 'row' : 'stack';
	const align: Align = el.align === 'left' || el.align === 'right' ? el.align : 'center';
	const cs = el.code?.size ?? 6.5;
	const hs = el.hint?.size ?? 6;
	const hlh = el.hint?.lh ?? 1.3;
	const ch = cs * 1.3;
	const hh = hs * hlh * 2;
	const code = { size: cs, weight: el.code?.weight ?? 600, color: el.code?.color ?? '$muted', lh: 1 };
	const hint = { size: hs, weight: el.hint?.weight ?? 400, color: el.hint?.color ?? '$muted', lh: hlh };

	if ( arrange === 'stack' ) {
		const qx = align === 'left' ? x : align === 'right' ? x + w - q : x + ( w - q ) / 2;
		return [
			{ kind: 'qr', x: qx, y, w: q, h: q, align, size: 0, weight: 0, color: '$text', lh: 1 },
			{ kind: 'code', x, y: y + q + gap, w, h: ch, align, ...code },
			{ kind: 'hint', x, y: y + q + gap + ch + gap, w, h: hh, align, ...hint },
		];
	}
	const space = Math.max( 8, gap * 4 );
	let tw = Math.max( 20, w - q - space );
	const bh = ch + gap + hh;
	const ty = y + Math.max( 0, ( q - bh ) / 2 );
	let qx: number;
	let tx: number;
	let ta: Align;
	if ( align === 'right' ) {
		qx = x + w - q;
		tx = x;
		ta = 'right';
	} else if ( align === 'left' ) {
		qx = x;
		tx = x + q + space;
		ta = 'left';
	} else {
		tw = Math.min( tw, 150 );
		const start = x + ( w - ( q + space + tw ) ) / 2;
		qx = start;
		tx = start + q + space;
		ta = 'left';
	}
	return [
		{ kind: 'qr', x: qx, y, w: q, h: q, align, size: 0, weight: 0, color: '$text', lh: 1 },
		{ kind: 'code', x: tx, y: ty, w: tw, h: ch, align: ta, ...code },
		{ kind: 'hint', x: tx, y: ty + ch + gap, w: tw, h: hh, align: ta, ...hint },
	];
}
