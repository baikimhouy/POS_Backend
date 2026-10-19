"""
Add acubi-style products to StylePOS through the backend API.

How to run (backend must be running: php artisan serve):
    pip install requests
    python add_acubi_products.py

Options:
    python add_acubi_products.py --dry-run          # just show what would be added
    python add_acubi_products.py --email you@x.com --password secret
    python add_acubi_products.py --base http://localhost:8000

Safe to run twice: products that already exist (same name) are skipped.
Photos (optional): put files in an "images" folder next to this script, named like the
product slug, e.g.  images/sheer-layering-tee.jpg  - they are uploaded automatically.
"""
import argparse
import sys
from http.cookies import SimpleCookie
from pathlib import Path
from urllib.parse import unquote

try:
    import requests
except ImportError:
    sys.exit("Please run:  pip install requests")

# ───────────────────────── EDIT YOUR PRODUCTS HERE ─────────────────────────
# (category, name, slug, description, price USD, colors, sizes, stock per color/size)
CATALOG = [
    # ── Tops
    ("Tops", "Sheer Layering Tee", "sheer-layering-tee", "Lightweight sheer tee made for layering.", 11.0, ["Black", "Cream"], ["XS", "S", "M", "L"], 10),
    ("Tops", "Fitted Long Sleeve Tee", "fitted-long-sleeve-tee", "Slim ribbed long sleeve with a clean neckline.", 13.0, ["Black", "Grey", "Cream"], ["S", "M", "L"], 10),
    ("Tops", "Cropped Knit Vest", "cropped-knit-vest", "Fine-knit cropped vest, wear alone or over a tee.", 16.0, ["Grey", "Cream", "Black"], ["S", "M", "L"], 8),
    ("Tops", "Ribbed Halter Top", "ribbed-halter-top", "Stretch rib halter with a minimal cut.", 10.0, ["Black", "Beige"], ["S", "M"], 8),
    ("Tops", "Baby Cardigan", "baby-cardigan", "Soft cropped cardigan with small pearl buttons.", 19.0, ["Pink", "Cream", "Grey"], ["S", "M"], 7),
    ("Tops", "Satin Camisole", "satin-camisole", "Smooth satin cami with thin adjustable straps.", 12.0, ["Black", "Cream"], ["S", "M", "L"], 8),
    # ── Bottoms
    ("Bottoms", "Low-Rise Wide Trousers", "low-rise-wide-trousers", "Relaxed low-rise trousers with a long, clean drop.", 27.0, ["Black", "Grey", "Khaki"], ["26", "28", "30", "32"], 6),
    ("Bottoms", "Pleated Mini Skirt", "pleated-mini-skirt", "Knife-pleat mini skirt with a hidden zip.", 18.0, ["Black", "Grey"], ["S", "M", "L"], 8),
    ("Bottoms", "Cargo Midi Skirt", "cargo-midi-skirt", "Midi skirt with soft cargo pockets and an elastic back.", 24.0, ["Khaki", "Black"], ["S", "M", "L"], 6),
    # ── Outerwear
    ("Outerwear", "Cropped Blazer", "cropped-blazer", "Structured cropped blazer, one-button front.", 38.0, ["Black", "Grey"], ["S", "M", "L"], 5),
    ("Outerwear", "Oversized Knit Cardigan", "oversized-knit-cardigan", "Chunky oversized cardigan with dropped shoulders.", 32.0, ["Cream", "Grey", "Brown"], ["M", "L"], 6),
    ("Outerwear", "Mini Trench Coat", "mini-trench-coat", "Short trench with a belted waist.", 45.0, ["Beige", "Black"], ["S", "M", "L"], 4),
    # ── Dresses
    ("Dresses", "Slip Dress", "slip-dress", "Bias-cut satin slip dress, midi length.", 29.0, ["Black", "Cream", "Brown"], ["S", "M", "L"], 6),
    ("Dresses", "Knit Mini Dress", "knit-mini-dress", "Fitted rib-knit mini dress with a square neck.", 26.0, ["Grey", "Black"], ["S", "M"], 6),
    # ── Accessories
    ("Accessories", "Mini Shoulder Bag", "mini-shoulder-bag", "Small structured shoulder bag with a gold clasp.", 22.0, ["Black", "Brown"], ["OS"], 8),
    ("Accessories", "Pearl Claw Clip", "pearl-claw-clip", "Matte claw clip with pearl detail.", 4.5, ["Black", "Beige"], ["OS"], 20),
    ("Accessories", "Silk Hair Scarf", "silk-hair-scarf", "Square silk-feel scarf for hair or bag.", 6.0, ["Cream", "Pink", "Black"], ["OS"], 15),
    ("Accessories", "Ribbed Leg Warmers", "ribbed-leg-warmers", "Knee-length ribbed leg warmers.", 7.0, ["Grey", "Black", "Cream"], ["OS"], 12),
    ("Accessories", "Silver Chain Belt", "silver-chain-belt", "Thin silver chain belt, adjustable.", 8.0, ["Silver"], ["OS"], 12),
    # ── Shoes
    ("Shoes", "Ballet Flats", "ballet-flats", "Soft leather-look ballet flats with a round toe.", 28.0, ["Black", "Cream"], ["36", "37", "38", "39"], 5),
    ("Shoes", "Mary Jane Shoes", "mary-jane-shoes", "Classic strap Mary Janes with a low block heel.", 34.0, ["Black", "Brown"], ["36", "37", "38", "39"], 4),
]
COST_RATIO = 0.45          # cost price = 45% of selling price
LOW_STOCK_AT = 3
# ───────────────────────────────────────────────────────────────────────────


class Api:
    """Talks to the backend. Cookies are handled by hand because the backend sets them for the
    domain "localhost", which Python's built-in cookie jar silently refuses to send back."""

    def __init__(self, base: str, origin: str):
        self.base = base.rstrip("/")
        self.cookies: dict[str, str] = {}
        # Sanctum treats requests as "from the SPA" only when Origin/Referer is the frontend.
        self.headers = {"Accept": "application/json", "Origin": origin, "Referer": origin + "/"}

    def _remember(self, r):
        for raw in r.raw.headers.getlist("Set-Cookie"):
            c = SimpleCookie()
            c.load(raw)
            for name, morsel in c.items():
                self.cookies[name] = morsel.value

    def call(self, method, path, **kw):
        headers = dict(self.headers)
        headers["Cookie"] = "; ".join(f"{k}={v}" for k, v in self.cookies.items())
        if "XSRF-TOKEN" in self.cookies:
            headers["X-XSRF-TOKEN"] = unquote(self.cookies["XSRF-TOKEN"])
        r = requests.request(method, f"{self.base}{path}", headers=headers, timeout=30, **kw)
        self._remember(r)
        if not r.ok:
            raise RuntimeError(f"{method} {path} -> {r.status_code}: {r.text[:400]}")
        return r.json() if r.content else {}

    def login(self, email, password):
        self.call("GET", "/sanctum/csrf-cookie")
        self.call("POST", "/api/v1/login", json={"email": email, "password": password})


def sku_for(slug: str, color: str, size: str) -> str:
    code = "".join(w[0] for w in slug.split("-")).upper()
    return f"ACU-{code}-{color[:3].upper()}-{size}".replace(" ", "")[:60]


def find_image(slug: str):
    for ext in ("jpg", "jpeg", "png", "webp"):
        p = Path(__file__).parent / "images" / f"{slug}.{ext}"
        if p.is_file():
            return p
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://localhost:8000", help="backend address")
    ap.add_argument("--origin", default="http://localhost:5173", help="frontend address (must match SANCTUM_STATEFUL_DOMAINS)")
    ap.add_argument("--email", default="admin@stylepos.test")
    ap.add_argument("--password", default="password")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    if a.dry_run:
        total = sum(len(c) * len(s) for _, _, _, _, _, c, s, _ in CATALOG)
        print(f"{len(CATALOG)} products, {total} color/size options (dry run, nothing sent)")
        for cat, name, _, _, price, colors, sizes, stock in CATALOG:
            print(f"  [{cat}] {name}  ${price:.2f}  {'/'.join(colors)}  {'/'.join(sizes)}  x{stock}")
        return

    api = Api(a.base, a.origin)
    try:
        api.login(a.email, a.password)
    except Exception as e:  # noqa: BLE001
        sys.exit(f"Login failed: {e}\nIs 'php artisan serve' running, and do the email/password match an admin?")
    print("Logged in.")

    cats = {c["name"]: c["id"] for c in api.call("GET", "/api/v1/categories")["data"]}
    existing = set()
    page = 1
    while True:
        res = api.call("GET", "/api/v1/products", params={"per_page": 100, "page": page})
        existing |= {p["name"].lower() for p in res["data"]}
        if page >= res["meta"]["last_page"]:
            break
        page += 1

    added = skipped = 0
    for cat, name, slug, desc, price, colors, sizes, stock in CATALOG:
        if name.lower() in existing:
            print(f"  skip   {name} (already exists)")
            skipped += 1
            continue
        if cat not in cats:
            cats[cat] = api.call("POST", "/api/v1/categories", json={"name": cat})["data"]["id"]
            print(f"  + category {cat}")

        variants = [
            {
                "sku": sku_for(slug, color, size),
                "size": size,
                "color": color,
                "cost_price": round(price * COST_RATIO, 2),
                "selling_price": price,
                "stock_quantity": stock,
                "low_stock_threshold": LOW_STOCK_AT,
            }
            for color in colors
            for size in sizes
        ]
        try:
            product = api.call("POST", "/api/v1/products", json={
                "name": name, "category_id": cats[cat], "brand": "ACUBI",
                "description": desc, "variants": variants,
            })["data"]
        except RuntimeError as e:
            print(f"  FAILED {name}: {e}")
            continue

        img = find_image(slug)
        if img:
            with img.open("rb") as f:
                api.call("POST", f"/api/v1/products/{product['id']}/image", files={"image": (img.name, f)})
        print(f"  added  {name}  ({len(variants)} options{', with photo' if img else ''})")
        added += 1

    print(f"\nDone. Added {added}, skipped {skipped}.")


if __name__ == "__main__":
    main()
