#!/usr/bin/env python3
"""Generate the oversized background fixture for the E2E test
"oversized background image still emails a small PDF attachment"
(my-certificates-email.spec.ts).

Produces ojs-test/cert-assets/cert_bg_big.png: 3200x2240, >1 MB.
Photo-like content — a smooth gradient with low-amplitude per-pixel
jitter — so it is heavy as lossless PNG yet compresses well once the
plugin downscales and re-encodes it as JPEG.

Requires Pillow: pip install Pillow
"""
import os
import random

from PIL import Image

random.seed(20260720)
W, H = 3200, 2240
TW, TH = 320, 224

tile = Image.new("RGB", (TW, TH))
px = tile.load()
for y in range(TH):
    for x in range(TW):
        base = 120 + int(60 * x / TW) + int(40 * y / TH)
        j = lambda: random.randint(-7, 7)
        px[x, y] = (
            max(0, min(255, base + j())),
            max(0, min(255, base - 20 + j())),
            max(0, min(255, base + 30 + j())),
        )

img = Image.new("RGB", (W, H))
for ty in range(H // TH):
    for tx in range(W // TW):
        shifted = tile.point(
            lambda v, d=((tx + ty * 10) % 21 - 10): max(0, min(255, v + d))
        )
        img.paste(shifted, (tx * TW, ty * TH))

out = os.path.join(
    os.path.dirname(__file__), "..", "..", "..", "ojs-test", "cert-assets", "cert_bg_big.png"
)
out = os.path.normpath(out)
img.save(out, "PNG", compress_level=6)
size_mb = os.path.getsize(out) / 1048576
print(f"{out}: {size_mb:.2f} MB {img.size}")
assert size_mb > 1.0, "fixture must exceed 1 MB to trigger the downscaler"
