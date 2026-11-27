#!/usr/bin/env python3
"""
Draws the Basic template's background: large rectangles and circles (frames, a set of circles in the middle and in each corner) and the band behind the name, as ONE vector drawing of filled paths. It is a single SVG element of the design in the template's "Lines" color with an opacity,
so it follows the light and the dark mode like any other color. Plain, large shapes, no thin lines: they print and scale well.

Writes templates/basic-background.svg (the drawing, to look at and edit), templates/basic-background.png (the same drawing in grey and
transparency: the plugin puts it in the Media Library, where the Basic template uses it) and puts the same paths in the background element of
templates/basic-design.json (the template reads its drawing from there). Own artwork. Plain Python 3 (Pillow for the PNG):

    python3 bin/make-basic-background.py
"""
import json
import os

W, H = 1123, 794  # A4 landscape at 96 dpi: the drawing's own units.
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LEVELS = 16  # steps of transparency in the PNG.
GRAY, ALPHA = 118, 48  # the PNG's grey and its strongest transparency (of 255): about #E4E6F0 on white and #272A2E on the dark page.
BAND = (0, 261.4, W, 98.7)  # x, y, width, height of the band behind the name: edge to edge (the name sits in its middle).


def rect(x, y, w, h, clockwise=True):
    points = [(x, y), (x + w, y), (x + w, y + h), (x, y + h)]
    return points if clockwise else points[::-1]


def frame(x, y, w, h, t):
    """A rectangle outline `t` thick: the rectangle and, turning the other way, the hole (a ring with any fill rule)."""
    return 'M%s' % path_points(rect(x, y, w, h)) + 'ZM%s' % path_points(rect(x + t, y + t, w - 2 * t, h - 2 * t, False)) + 'Z'


def path_points(points):
    return 'L'.join('%g %g' % p for p in points)


def disc(cx, cy, r, clockwise=True):
    sweep = 1 if clockwise else 0
    return 'M%g %gA%g %g 0 1 %d %g %gA%g %g 0 1 %d %g %gZ' % (cx - r, cy, r, r, sweep, cx + r, cy, r, r, sweep, cx - r, cy)


def ring(cx, cy, r, t):
    """A circle outline `t` thick, and a solid disc when t is 0."""
    return disc(cx, cy, r) if not t else disc(cx, cy, r) + disc(cx, cy, r - t, False)


def geometry():
    """The drawing as plain shapes: ('rect', x, y, w, h), ('frame', x, y, w, h, thickness), ('ring', cx, cy, r, thickness; 0 is a disc)."""
    x, y, w, h = BAND
    shapes = [('rect', x, y, w, h), ('frame', 18, 18, W - 36, H - 36, 6), ('frame', 34, 34, W - 68, H - 68, 2)]
    cx, cy = W / 2, H / 2 + 12
    for r, t in ((328, 5), (272, 14), (215, 4), (158, 0)):                       # large circles in the middle
        shapes.append(('ring', cx, cy, r, t))
    for px, py in ((0, 0), (W, 0), (0, H), (W, H)):                              # a set in each corner, reaching into its quadrant
        for r, t in ((250, 6), (196, 16), (136, 4), (84, 0)):
            shapes.append(('ring', px, py, r, t))
    return shapes


def shape_path(shape):
    kind = shape[0]
    if kind == 'rect':
        return 'M%sZ' % path_points(rect(*shape[1:]))
    if kind == 'frame':
        return frame(*shape[1:])
    return ring(*shape[1:])


def paths():
    return [''.join(shape_path(shape) for shape in geometry())]


def write_png(path, scale=2, smooth=2):
    """The same shapes as a PNG: grey and transparency only (no color of its own), so it shows on a light page and on a dark one.
    scale: pixels for each unit of the drawing (2246 x 1588: sharp when printed); smooth: drawn this much larger, then shrunk."""
    from PIL import Image, ImageDraw
    k = scale * smooth
    mask = Image.new('L', (W * k, H * k), 0)
    d = ImageDraw.Draw(mask)
    for shape in geometry():
        if shape[0] == 'rect':
            _, x, y, w, h = shape
            d.rectangle([x * k, y * k, (x + w) * k - 1, (y + h) * k - 1], fill=255)
        elif shape[0] == 'frame':
            _, x, y, w, h, t = shape
            d.rectangle([x * k, y * k, (x + w) * k - 1, (y + h) * k - 1], fill=255)
            d.rectangle([(x + t) * k, (y + t) * k, (x + w - t) * k - 1, (y + h - t) * k - 1], fill=0)
        else:
            _, cx, cy, r, t = shape
            d.ellipse([(cx - r) * k, (cy - r) * k, (cx + r) * k, (cy + r) * k], fill=255)
            if t:
                d.ellipse([(cx - r + t) * k, (cy - r + t) * k, (cx + r - t) * k, (cy + r - t) * k], fill=0)
    mask = mask.resize((W * scale, H * scale), Image.LANCZOS)
    # Compressed: the grey is one color, so the picture is an indexed one with LEVELS steps of transparency (the steps are too small to
    # see) and a transparency for each: 4 bits for each pixel, and long runs of the same value that deflate well.
    index = mask.point(lambda v: round(v / 255 * (LEVELS - 1)))
    index.putpalette([GRAY] * 3 * LEVELS)
    transparency = bytes(round(i * ALPHA / (LEVELS - 1)) for i in range(LEVELS))
    index.save(path, optimize=True, transparency=transparency, compress_level=9)


if __name__ == '__main__':
    data = paths()
    svg = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">' % (W, H, W, H),
           '<title>Basic certificate background: frames, circles and the band of the name</title>',
           '<g fill="#C9CEE0" fill-opacity="0.5">']
    svg += ['<path d="%s"/>' % d for d in data]
    svg += ['</g>', '</svg>']
    open(os.path.join(ROOT, 'templates/basic-background.svg'), 'w').write('\n'.join(svg) + '\n')
    write_png(os.path.join(ROOT, 'templates/basic-background.png'))

    path = os.path.join(ROOT, 'templates/basic-design.json')
    design = json.load(open(path))
    page = design['page']
    elements = [e for e in design['elements'] if e['id'] != 'background']
    background = {'id': 'background', 'name': 'Background', 'type': 'svg', 'x': 0, 'y': 0, 'w': page['w'], 'h': page['h'], 'vw': W, 'vh': H,
                  'color': '$line', 'opacity': 0.5, 'paths': [{'d': d} for d in data]}
    design['elements'] = [background] + elements
    open(path, 'w').write(json.dumps(design, indent='\t', ensure_ascii=False) + '\n')
    print(len(data), 'path,', sum(len(d) for d in data), 'characters')
