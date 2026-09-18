from pathlib import Path
from urllib.parse import urljoin
from datetime import date
import html
import json
import os
import re
import sys

root = Path(sys.argv[1])
BASE = "https://fredericksamuel.com/"
SEO_START = "<!-- SEO-V6.3 START -->"
SEO_END = "<!-- SEO-V6.3 END -->"
TODAY = date.today().isoformat()
GOOGLE_SITE_VERIFICATION = os.getenv("GOOGLE_SITE_VERIFICATION", "").strip()
GA4_MEASUREMENT_ID = os.getenv("GA4_MEASUREMENT_ID", "").strip() or "G-NB2P64CP00"

def clean_text(value):
    value = re.sub(r"<[^>]+>", " ", value or "")
    return re.sub(r"\s+", " ", html.unescape(value)).strip()

def find(pattern, text, default=""):
    m = re.search(pattern, text, re.I | re.S)
    return clean_text(m.group(1)) if m else default

def description_of(text, fallback):
    m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)', text, re.I)
    if not m:
        m = re.search(r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']', text, re.I)
    return html.unescape(m.group(1)).strip() if m else fallback

def page_url(path):
    rel = path.relative_to(root).as_posix()
    return BASE if rel == "index.html" else urljoin(BASE, rel)

def first_cover(text):
    m = re.search(r'<img[^>]+src=["\']([^"\']+\.(?:webp|png|jpe?g)(?:\?[^"\']*)?)', text, re.I)
    if not m:
        return urljoin(BASE, "assets/covers/by-blood-ink-and-salt.webp")
    src = m.group(1).split("?", 1)[0]
    return urljoin(BASE, src)

def remove_old_seo(text):
    return re.sub(
        re.escape(SEO_START) + r".*?" + re.escape(SEO_END) + r"\s*",
        "",
        text,
        flags=re.S,
    )

def jsonld_for(path, text, title, desc, canonical, image):
    if path.name == "index.html":
        return [
            {
                "@context": "https://schema.org",
                "@type": "Person",
                "@id": BASE + "#frederick-samuel",
                "name": "Frederick Samuel",
                "url": BASE,
                "jobTitle": "Novelist",
                "description": desc,
                "knowsAbout": [
                    "historical fiction",
                    "literary fiction",
                    "speculative fiction",
                    "science fiction",
                    "fantasy"
                ],
            },
            {
                "@context": "https://schema.org",
                "@type": "WebSite",
                "@id": BASE + "#website",
                "url": BASE,
                "name": "Frederick Samuel",
                "description": desc,
                "inLanguage": "en",
                "publisher": {"@id": BASE + "#frederick-samuel"},
            },
        ]
    if path.name == "for-agents.html":
        return [
            {
                "@context": "https://schema.org",
                "@type": "ProfilePage",
                "@id": canonical + "#profile",
                "url": canonical,
                "name": title,
                "description": desc,
                "mainEntity": {
                    "@type": "Person",
                    "@id": BASE + "#frederick-samuel",
                    "name": "Frederick Samuel",
                    "url": BASE,
                    "jobTitle": "Novelist",
                },
                "inLanguage": "en",
            }
        ]

    series = find(r'<div[^>]+class=["\'][^"\']*series[^"\']*["\'][^>]*>(.*?)</div>', text)
    book = {
        "@context": "https://schema.org",
        "@type": "Book",
        "@id": canonical + "#book",
        "name": title.replace(" — Frederick Samuel", ""),
        "url": canonical,
        "description": desc,
        "image": image,
        "inLanguage": "en",
        "author": {"@id": BASE + "#frederick-samuel"},
    }
    if "Hadal Cycle" in series:
        book["isPartOf"] = {
            "@type": "CreativeWorkSeries",
            "name": "The Hadal Cycle",
            "url": BASE + "#hadal",
        }
    if "By Blood, Ink & Salt" in title:
        book["isbn"] = "9798292440642"
        book["datePublished"] = "2025"

    breadcrumb = {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Frederick Samuel",
                "item": BASE,
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": book["name"],
                "item": canonical,
            },
        ],
    }
    return [book, breadcrumb]

def enhance(path):
    text = path.read_text(encoding="utf-8")
    text = remove_old_seo(text)

    title = find(r"<title>(.*?)</title>", text, "Frederick Samuel")
    h1 = find(r"<h1[^>]*>(.*?)</h1>", text, title)
    canonical = page_url(path)
    fallback = (
        "Frederick Samuel — novelist writing literary, historical and speculative fiction."
        if path.name == "index.html"
        else f"{h1} by Frederick Samuel."
    )
    desc = description_of(text, fallback)
    image = first_cover(text)
    page_type = "website" if path.name in ("index.html", "for-agents.html") else "book"

    ld = jsonld_for(path, text, title, desc, canonical, image)
    block = [
        SEO_START,
        f'<link rel="canonical" href="{html.escape(canonical, quote=True)}"/>',
        '<meta name="author" content="Frederick Samuel"/>',
        '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1"/>',
        '<meta name="googlebot" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1"/>',
        f'<meta property="og:type" content="{page_type}"/>',
        '<meta property="og:site_name" content="Frederick Samuel"/>',
        '<meta property="og:locale" content="en_US"/>',
        f'<meta property="og:title" content="{html.escape(title, quote=True)}"/>',
        f'<meta property="og:description" content="{html.escape(desc, quote=True)}"/>',
        f'<meta property="og:url" content="{html.escape(canonical, quote=True)}"/>',
        f'<meta property="og:image" content="{html.escape(image, quote=True)}"/>',
        '<meta name="twitter:card" content="summary_large_image"/>',
        f'<meta name="twitter:title" content="{html.escape(title, quote=True)}"/>',
        f'<meta name="twitter:description" content="{html.escape(desc, quote=True)}"/>',
        f'<meta name="twitter:image" content="{html.escape(image, quote=True)}"/>',
    ]
    for obj in ld:
        block.append(
            '<script type="application/ld+json">'
            + json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
            + "</script>"
        )
    if path.name == "index.html" and GOOGLE_SITE_VERIFICATION:
        block.append(
            f'<meta name="google-site-verification" content="{html.escape(GOOGLE_SITE_VERIFICATION, quote=True)}"/>'
        )
    if GA4_MEASUREMENT_ID:
        ga_id = html.escape(GA4_MEASUREMENT_ID, quote=True)
        block.append(f'''<script id="fsGoogleAnalyticsConsent">
(function(){{
  const GA_ID="{ga_id}";
  const KEY="fs_analytics_consent";
  function loadGA(){{
    if(document.getElementById("fs-ga4-script")) return;
    window.dataLayer=window.dataLayer||[];
    window.gtag=function(){{dataLayer.push(arguments);}};
    gtag("js",new Date());
    gtag("config",GA_ID,{{anonymize_ip:true}});
    const s=document.createElement("script");
    s.id="fs-ga4-script"; s.async=true;
    s.src="https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(GA_ID);
    document.head.appendChild(s);
  }}
  function removeBanner(){{
    const b=document.getElementById("fsAnalyticsConsent"); if(b) b.remove();
  }}
  function grant(){{localStorage.setItem(KEY,"granted");removeBanner();loadGA();}}
  function deny(){{localStorage.setItem(KEY,"denied");removeBanner();}}
  if(localStorage.getItem(KEY)==="granted"){{loadGA();return;}}
  if(localStorage.getItem(KEY)==="denied") return;
  window.addEventListener("DOMContentLoaded",function(){{
    const b=document.createElement("div");
    b.id="fsAnalyticsConsent";
    b.setAttribute("role","dialog");
    b.setAttribute("aria-label","Analytics consent");
    b.style.cssText="position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:720px;margin:auto;background:#111516;color:#f1ede5;border:1px solid rgba(255,255,255,.18);padding:16px 18px;font:14px/1.5 system-ui,-apple-system,sans-serif;box-shadow:0 16px 45px rgba(0,0,0,.35)";
    b.innerHTML='<div style="margin-bottom:12px">Optional analytics help improve this site. No analytics are loaded unless you accept.</div><div style="display:flex;gap:10px;flex-wrap:wrap"><button id="fsAcceptAnalytics" type="button" style="min-height:42px;padding:0 16px;border:1px solid #f1ede5;background:#f1ede5;color:#111516">Accept analytics</button><button id="fsDeclineAnalytics" type="button" style="min-height:42px;padding:0 16px;border:1px solid rgba(255,255,255,.35);background:transparent;color:#f1ede5">Decline</button></div>';
    document.body.appendChild(b);
    document.getElementById("fsAcceptAnalytics").addEventListener("click",grant);
    document.getElementById("fsDeclineAnalytics").addEventListener("click",deny);
  }});
}})();
</script>''')
    block.append(SEO_END)
    seo = "\n".join(block) + "\n"
    if "</head>" not in text:
        raise SystemExit(f"No </head> in {path}")
    text = text.replace("</head>", seo + "</head>", 1)
    text = text.replace("Frederick Samuel Author Website V6.2", "Frederick Samuel Author Website V6.4")
    text = text.replace("Frederick Samuel Author Website V6.3", "Frederick Samuel Author Website V6.4")
    path.write_text(text, encoding="utf-8")

agent_html = r'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
<meta name="description" content="For literary agents and publishers: selected fiction projects, published work and representation contact for novelist Frederick Samuel."/>
<meta name="generator" content="Frederick Samuel Author Website V6.4"/>
<title>For Agents &amp; Publishers — Frederick Samuel</title>
<style>
:root{--ink:#0b0d0e;--paper:#f1ede5;--muted:#969a96;--line:rgba(255,255,255,.13);--rust:#9c3b31;--serif:"Iowan Old Style","Palatino Linotype",Palatino,Baskerville,Georgia,serif;--sans:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--ink);color:#f7f5ef;font-family:var(--sans)}a{color:inherit;text-decoration:none}.shell{width:min(1180px,90vw);margin:auto}.nav{height:76px;border-bottom:1px solid var(--line);display:flex;align-items:center;position:sticky;top:0;background:rgba(11,13,14,.95);backdrop-filter:blur(14px);z-index:10}.nav .shell{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{font:400 20px var(--serif);letter-spacing:.14em}.back{font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:#b8bbb7}.hero{padding:clamp(72px,9vw,128px) 0 90px;background:radial-gradient(circle at 82% 16%,rgba(156,59,49,.13),transparent 29%),#0b0d0e}.eyebrow,.label{font-size:9px;letter-spacing:.2em;text-transform:uppercase;color:#8d918d}.hero h1{font:400 clamp(58px,8vw,112px)/.88 var(--serif);letter-spacing:-.035em;max-width:900px;margin:22px 0 28px}.hero h1 em{font-weight:400;color:#aaa79f}.lead{font:400 clamp(21px,2.2vw,29px)/1.5 var(--serif);color:#c5c8c4;max-width:800px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:34px}.btn{display:inline-flex;min-height:48px;align-items:center;justify-content:center;padding:0 20px;border:1px solid rgba(255,255,255,.25);font-size:9px;letter-spacing:.15em;text-transform:uppercase}.btn.light{background:#f1ede5;color:#171918;border-color:#f1ede5}.paper{background:var(--paper);color:#171918;padding:82px 0}.dark{padding:82px 0}.section-head{display:grid;grid-template-columns:.35fr 1fr;gap:6vw;margin-bottom:44px}.section-head h2{font:400 clamp(42px,5vw,68px)/.96 var(--serif);margin:0}.section-head p{font:400 19px/1.65 var(--serif);color:#575650;margin:0;max-width:720px}.credentials{display:grid;grid-template-columns:1fr 1fr;gap:18px}.credential{padding:28px;border:1px solid rgba(20,20,20,.14);background:#e9e2d7}.credential h3,.project h3{font:400 34px/1.05 var(--serif);margin:12px 0}.credential p,.project p{line-height:1.65;color:#5a5852;margin:0}.projects{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.project{border:1px solid rgba(255,255,255,.13);padding:26px;background:#0f1212}.project p{color:#aeb1ad}.project .label{color:#777d79}.project a{display:inline-block;margin-top:18px;font-size:9px;letter-spacing:.14em;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.22);padding-bottom:4px}.materials{display:grid;grid-template-columns:.7fr 1.3fr;gap:7vw;align-items:start}.materials h2{font:400 clamp(44px,5vw,70px)/.95 var(--serif);margin:0}.materials p{font:400 20px/1.65 var(--serif);color:#c0c3bf;margin-top:0}.list{border-top:1px solid var(--line)}.row{display:grid;grid-template-columns:.45fr 1fr;gap:20px;padding:15px 0;border-bottom:1px solid var(--line);font-size:11px;line-height:1.5}.row span:first-child{text-transform:uppercase;letter-spacing:.13em;color:#7d817d}.contact{padding:90px 0 100px;background:#ece5da;color:#171918}.contact h2{font:400 clamp(48px,6vw,82px)/.92 var(--serif);margin:16px 0 24px}.contact p{font:400 20px/1.6 var(--serif);max-width:760px;color:#575650}.contact a.email{display:inline-block;margin-top:16px;font:400 25px/1.2 var(--serif);border-bottom:1px solid #777168;padding-bottom:5px}.footer{padding:28px 0;color:#747975;font-size:8px;letter-spacing:.14em;text-transform:uppercase}
@media(max-width:760px){.shell{width:calc(100% - 34px)}.nav{height:68px}.brand{font-size:16px}.back{font-size:8px}.section-head,.materials{grid-template-columns:1fr;gap:22px}.credentials,.projects{grid-template-columns:1fr}.hero{padding-top:66px}.hero h1{font-size:clamp(52px,15vw,70px)}.paper,.dark{padding:62px 0}.row{grid-template-columns:1fr;gap:6px}.actions{display:grid}.btn{width:100%}.contact{padding:66px 0 74px}.contact a.email{font-size:20px;overflow-wrap:anywhere}}
</style>
</head>
<body>
<nav class="nav"><div class="shell"><a class="brand" href="index.html">FREDERICK SAMUEL</a><a class="back" href="index.html">← Back to the site</a></div></nav>
<main>
<section class="hero"><div class="shell">
<div class="eyebrow">Representation · rights · publishing</div>
<h1>For agents &amp; <em>publishers.</em></h1>
<p class="lead">Frederick Samuel is seeking literary representation for selected unpublished fiction across historical, literary and speculative work. This page gathers the public-facing project information in one place; manuscripts, synopses and additional materials are available privately on request.</p>
<div class="actions"><a class="btn light" href="mailto:frederick@fredericksamuel.com?subject=Literary%20representation%20enquiry">Representation enquiry →</a><a class="btn" href="#projects">Selected projects ↓</a></div>
</div></section>

<section class="paper"><div class="shell">
<div class="section-head"><div class="label">Published foundation</div><div><h2>Two published novels.</h2><p>Published work provides a public record of the fiction while the projects below represent the broader body of writing now moving toward representation and publication.</p></div></div>
<div class="credentials">
<article class="credential"><div class="label">Published novel</div><h3>The Weaver’s Debt</h3><p>A novel about inheritance, promises and the moral burden carried forward when familiar stories are allowed to speak for the people inside them.</p><a class="btn" style="margin-top:20px;border-color:#777168" href="books/the-weavers-debt.html">View novel →</a></article>
<article class="credential"><div class="label">Historical literary fiction · 2025</div><h3>By Blood, Ink &amp; Salt</h3><p>A historical reimagining of Shek Ying—Zheng Yi Sao—and the red-sailed maritime world she helped reshape.</p><a class="btn" style="margin-top:20px;border-color:#777168" href="books/by-blood-ink-and-salt.html">View novel →</a></article>
</div>
</div></section>

<section class="dark" id="projects"><div class="shell">
<div class="section-head"><div class="label">Selected projects</div><div><h2>Different forms. Recurring pressures.</h2><p style="color:#aeb1ad">Historical, literary and speculative projects connected by recurring concerns with memory, silence, identity, science and the consequences of what people choose to preserve.</p></div></div>
<div class="projects">
<article class="project"><div class="label">Historical literary fiction</div><h3>A Thread of Stars</h3><p>A novel centred on Caroline Herschel and a life shaped by astronomy, discovery and the distance between recognition and authorship.</p><a href="books/a-thread-of-stars.html">Project page →</a></article>
<article class="project"><div class="label">Historical war fiction</div><h3>The Gustav Sonata</h3><p>A wartime novel about truth, consequence and what remains after official versions of history have moved on.</p><a href="books/the-gustav-sonata.html">Project page →</a></article>
<article class="project"><div class="label">Historical fiction</div><h3>The Quiet Blade</h3><p>A restrained historical novel built around silence, discipline and the moral cost of violence.</p><a href="books/the-quiet-blade.html">Project page →</a></article>
<article class="project"><div class="label">Speculative fantasy</div><h3>Thrindle</h3><p>A standalone speculative fantasy in which place, memory and perception become inseparable.</p><a href="books/thrindle.html">Project page →</a></article>
<article class="project"><div class="label">Speculative techno-thriller</div><h3>The Silent Protocol</h3><p>A technological thriller about signals, control and the danger of systems that hear more than people intend.</p><a href="books/the-silent-protocol.html">Project page →</a></article>
<article class="project"><div class="label">Psychological science fiction</div><h3>What The Snow Remembers</h3><p>A snowbound psychological science-fiction novel in which memory is both mystery and moral problem.</p><a href="books/the-snowblind-protocol.html">Project page →</a></article>
<article class="project"><div class="label">Ten-volume speculative series</div><h3>The Hadal Cycle</h3><p>A speculative arc from the hadal trench to Mars, Europa and a transformed Earth, centred on adaptation, memory and the changing definition of humanity.</p><a href="index.html#hadal">Explore the cycle →</a></article>
</div>
</div></section>

<section class="dark" style="padding-top:10px"><div class="shell materials">
<div><div class="label">Materials</div><h2>Available privately.</h2></div>
<div><p>Public pages are intentionally concise. Query materials can be supplied directly to agents, editors and publishers rather than posted openly.</p>
<div class="list">
<div class="row"><span>Query package</span><span>Project-specific query letter and concise pitch</span></div>
<div class="row"><span>Manuscript materials</span><span>Synopsis, opening pages and full manuscript where applicable</span></div>
<div class="row"><span>Series materials</span><span>Hadal Cycle overview, individual-volume information and sequence context</span></div>
<div class="row"><span>Published work</span><span>Retail links, publication information and public reviews</span></div>
<div class="row"><span>Rights &amp; contact</span><span>Available directly by email</span></div>
</div></div>
</div></section>

<section class="contact"><div class="shell"><div class="label">Representation contact</div><h2>Start with the project.</h2><p>For representation, publishing or rights enquiries, please contact Frederick Samuel directly. Include the project or genre you are interested in and the materials you would like to receive.</p><a class="email" href="mailto:frederick@fredericksamuel.com?subject=Literary%20representation%20enquiry">frederick@fredericksamuel.com</a></div></section>
</main>
<footer class="footer"><div class="shell">Frederick Samuel · novelist · fredericksamuel.com</div></footer>
</body>
</html>
'''

(root / "for-agents.html").write_text(agent_html, encoding="utf-8")

index = root / "index.html"
homepage = index.read_text(encoding="utf-8")
if 'href="for-agents.html"' not in homepage:
    needle = '<div class="rep-row"><span>Materials</span><span>Manuscripts and project information on request</span></div>'
    replacement = needle + '\n<a class="btn dark rep-agent-link" href="for-agents.html" style="margin-top:26px">For agents &amp; publishers →</a>'
    if needle not in homepage:
        raise SystemExit("Representation insertion anchor missing")
    homepage = homepage.replace(needle, replacement, 1)
index.write_text(homepage, encoding="utf-8")

pages = [root / "index.html", root / "for-agents.html", *sorted((root / "books").glob("*.html"))]
for path in pages:
    enhance(path)

# robots.txt
(root / "robots.txt").write_text(
    "User-agent: *\n"
    "Allow: /\n"
    "Disallow: /contact.php\n"
    f"Sitemap: {BASE}sitemap.xml\n",
    encoding="utf-8",
)

# XML sitemap with cover image discovery for book pages.
url_rows = []
for path in pages:
    text = path.read_text(encoding="utf-8")
    loc = page_url(path)
    image = first_cover(text)
    title = find(r"<h1[^>]*>(.*?)</h1>", text, find(r"<title>(.*?)</title>", text, "Frederick Samuel"))
    if path.parent.name == "books":
        image_xml = (
            "\n    <image:image>"
            f"<image:loc>{html.escape(image)}</image:loc>"
            f"<image:title>{html.escape(title)}</image:title>"
            "</image:image>"
        )
    else:
        image_xml = ""
    url_rows.append(
        "  <url>\n"
        f"    <loc>{html.escape(loc)}</loc>\n"
        f"    <lastmod>{TODAY}</lastmod>"
        f"{image_xml}\n"
        "  </url>"
    )

sitemap = (
    '<?xml version="1.0" encoding="UTF-8"?>\n'
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
    'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n'
    + "\n".join(url_rows)
    + "\n</urlset>\n"
)
(root / "sitemap.xml").write_text(sitemap, encoding="utf-8")

print(f"SEO V6.3: enhanced {len(pages)} indexable pages, generated robots.txt and sitemap.xml")
