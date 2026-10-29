#!/usr/bin/env python3
"""Draws the Kindo mark (a bold K with a small spark: "noor" means light) and writes every logo and icon the project uses.
Run from the repo root:  python3 design/brand/make_brand.py     (needs Pillow)
To use a different logo, replace the outputs in public/brand/ and the app icons, or change the shapes below and run again."""
import math, os
from PIL import Image, ImageDraw

INK, ORCHID, BUTTER, LAVENDER, WHITE = '#120c15', '#e697f2', '#fff0ad', '#cabaef', '#ffffff'
SS = 8   # supersampling for smooth edges

def thick_line(d, p, q, w, fill, k):
    (x1, y1), (x2, y2) = [(a * k, b * k) for a, b in (p, q)]
    w *= k; dx, dy = x2 - x1, y2 - y1; n = math.hypot(dx, dy); nx, ny = -dy / n * w / 2, dx / n * w / 2
    d.polygon([(x1 + nx, y1 + ny), (x2 + nx, y2 + ny), (x2 - nx, y2 - ny), (x1 - nx, y1 - ny)], fill=fill)
    for x, y in ((x1, y1), (x2, y2)): d.ellipse([x - w / 2, y - w / 2, x + w / 2, y + w / 2], fill=fill)

def spark(d, cx, cy, r, fill, k):
    pts = []
    for i in range(8):
        a = math.pi / 4 * i - math.pi / 2; rr = r if i % 2 == 0 else r * 0.38
        pts.append(((cx + math.cos(a) * rr) * k, (cy + math.sin(a) * rr) * k))
    d.polygon(pts, fill=fill)

def draw_glyph(d, k, k_color, spark_color):
    """The K and the spark, in a 100 x 100 box."""
    thick_line(d, (34, 27), (34, 73), 13, k_color, k)
    thick_line(d, (40, 52), (62, 33), 13, k_color, k)
    thick_line(d, (45, 50), (68, 73), 13, k_color, k)
    spark(d, 79, 21, 10, spark_color, k)

def mark(size, bg=INK, fg=ORCHID, accent=BUTTER, radius=26, full_bleed=False, scale=1.0):
    big = size * SS; im = Image.new('RGBA', (big, big), (0, 0, 0, 0)); d = ImageDraw.Draw(im)
    if bg:
        d.rounded_rectangle([0, 0, big - 1, big - 1], radius=0 if full_bleed else big * radius / 100, fill=bg)
    g = Image.new('RGBA', (big, big), (0, 0, 0, 0)); gd = ImageDraw.Draw(g)
    draw_glyph(gd, big / 100, fg, accent)
    if scale != 1.0:   # shrink the glyph toward the centre (for maskable / adaptive icons that need a safe zone)
        s = int(big * scale); g = g.resize((s, s), Image.LANCZOS); canvas = Image.new('RGBA', (big, big), (0, 0, 0, 0)); canvas.paste(g, ((big - s) // 2, (big - s) // 2)); g = canvas
    im.alpha_composite(g)
    return im.resize((size, size), Image.LANCZOS)

def save(im, path, **kw):
    os.makedirs(os.path.dirname(path), exist_ok=True); im.save(path, **kw)

SVG = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" role="img" aria-label="Kindo">
  <rect width="100" height="100" rx="26" fill="#120c15"/>
  <g stroke="#e697f2" stroke-width="13" stroke-linecap="round" fill="none"><path d="M34 27V73"/><path d="M40 52L62 33"/><path d="M45 50L68 73"/></g>
  <path d="M79 11L81.8 18.2L89 21L81.8 23.8L79 31L76.2 23.8L69 21L76.2 18.2Z" fill="#fff0ad"/>
</svg>
'''

def main():
    root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
    P = lambda *a: os.path.join(root, *a)
    open(P('public/brand/logo.svg'), 'w').write(SVG); open(P('design/brand/logo.svg'), 'w').write(SVG)
    for s in (32, 48, 64, 96, 128, 180, 192, 256, 512, 1024): save(mark(s), P(f'public/brand/mark-{s}.png'))
    save(mark(180), P('public/brand/apple-touch-icon.png'))
    ico = [mark(s) for s in (16, 32, 48)]; ico[2].save(P('public/favicon.ico'), format='ICO', sizes=[(16, 16), (32, 32), (48, 48)])
    # light version for dark backgrounds, and a transparent glyph
    save(mark(512, bg=LAVENDER, fg=INK, accent=ORCHID), P('public/brand/mark-lavender-512.png'))
    save(mark(512, bg=None), P('public/brand/glyph-512.png'))

    # ---- Kindo Parent: dark tile, pink K.  Kindo Junior: soft lavender tile, dark K (friendlier for children). ----
    apps = {'kindo_parent': dict(bg=INK, fg=ORCHID, accent=BUTTER), 'kindo_junior': dict(bg=LAVENDER, fg=INK, accent=ORCHID)}
    for app, style in apps.items():
        A = lambda *a: P('apps', app, *a)
        # Android legacy icons (square with rounded corners baked in)
        for folder, px in (('mdpi', 48), ('hdpi', 72), ('xhdpi', 96), ('xxhdpi', 144), ('xxxhdpi', 192)):
            p = A('android/app/src/main/res', f'mipmap-{folder}', 'ic_launcher.png')
            if os.path.isdir(os.path.dirname(p)): save(mark(px, **style), p)
        # iOS: full-bleed squares (the system rounds them), every size listed in Contents.json
        icons = A('ios/Runner/Assets.xcassets/AppIcon.appiconset')
        if os.path.isdir(icons):
            import json, re
            for img in json.load(open(os.path.join(icons, 'Contents.json')))['images']:
                if 'filename' not in img: continue
                w = float(img['size'].split('x')[0]); sc = int(img['scale'].replace('x', ''))
                im = mark(int(round(w * sc)), full_bleed=True, **style).convert('RGB')   # the App Store icon must not have transparency
                im.save(os.path.join(icons, img['filename']))
        # web (also what the web demo shows)
        if os.path.isdir(A('web')):
            save(mark(32, **style), A('web/favicon.png'))
            save(mark(192, **style), A('web/icons/Icon-192.png')); save(mark(512, **style), A('web/icons/Icon-512.png'))
            save(mark(192, full_bleed=True, scale=0.78, **style), A('web/icons/Icon-maskable-192.png')); save(mark(512, full_bleed=True, scale=0.78, **style), A('web/icons/Icon-maskable-512.png'))
    print('brand assets written')

main()
