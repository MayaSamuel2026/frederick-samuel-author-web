from pathlib import Path
import re, sys

root = Path(sys.argv[1])
html_files = [root / 'index.html', *sorted((root / 'books').glob('*.html'))]

for path in html_files:
    if not path.exists():
        continue
    s = path.read_text(encoding='utf-8')
    # Standardize deployment on WebP so Hostinger only needs one image format.
    s = re.sub(r'<source\s+srcset="[^"]+\.avif"\s+type="image/avif"\s*/>', '', s)
    s = s.replace('<source id="modalSourceAvif" type="image/avif"/>', '')
    s = s.replace("  document.getElementById('modalSourceAvif').srcset=b.image.avif;\n", '')
    s = re.sub(r'"image":\{"avif":"[^"]+\.avif","webp":', '"image":{"webp":', s)
    s = s.replace('Frederick Samuel Author Website V5.7', 'Frederick Samuel Author Website V5.8')
    s = s.replace('Frederick Samuel Author Website V5.6', 'Frederick Samuel Author Website V5.8')
    path.write_text(s, encoding='utf-8')

index = root / 'index.html'
s = index.read_text(encoding='utf-8')
old = '''/* V5.6 editorial refinement */
.hero{min-height:70vh;background:linear-gradient(90deg,rgba(116,37,31,0) 52%,rgba(116,37,31,.075) 100%),linear-gradient(115deg,#0b0d0e 0%,#101414 58%,#090b0c 100%)}
.hero-inner{align-items:center;padding-top:8.5vw;padding-bottom:7vw}
.hero-side{align-self:center}
'''
new = '''/* V5.8 hero refinement */
.hero{min-height:min(900px,82vh);background:radial-gradient(circle at 84% 16%,rgba(126,42,35,.11),transparent 26%),linear-gradient(115deg,#0b0d0e 0%,#101414 54%,#090b0c 100%)}
.hero-inner{grid-template-columns:minmax(0,1.15fr) minmax(370px,460px);gap:clamp(36px,5vw,84px);align-items:center;padding-top:clamp(84px,8.5vw,132px);padding-bottom:clamp(62px,6.5vw,108px)}
.hero h1{max-width:760px;font-size:clamp(72px,8.4vw,134px)}
.hero .lead{max-width:690px}
.hero-library{width:100%;max-width:460px;justify-self:end;padding-left:0;align-self:center}
.hero-library-head{margin-bottom:26px}
.hero-library-head strong{font-size:28px;line-height:1.06;text-align:left}
.hero-covers{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.hero-book{display:block}
.hero-book picture{display:block}
.hero-book img{width:100%;height:auto;aspect-ratio:2/3;object-fit:cover;border:1px solid rgba(255,255,255,.08);box-shadow:0 26px 56px rgba(0,0,0,.5)}
.hero-book-meta{padding-top:14px}
.hero-book-meta b{font-size:20px}
.hero-side{align-self:center}
'''
if old in s:
    s = s.replace(old, new)
# Responsive hero rules from V5.7 -> V5.8.
s = s.replace('''@media(max-width:900px){
 .hero-inner{grid-template-columns:1fr;padding-top:80px}
 .hero-library{padding-left:0;max-width:620px}
 .hero-covers{grid-template-columns:repeat(2,minmax(0,1fr))}
 .hero-library-head strong{text-align:left}
}
@media(max-width:560px){
 .hero h1{font-size:54px}
 .hero-covers{gap:14px}
 .hero-library-head{grid-template-columns:1fr}
 .hero-library-head strong{font-size:20px}
}
''','''@media(max-width:1100px){
 .hero-inner{grid-template-columns:1fr;gap:34px;padding-top:88px}
 .hero-library{justify-self:start;max-width:720px}
 .hero-covers{grid-template-columns:repeat(2,minmax(220px,1fr));max-width:720px}
 .hero-library-head strong{text-align:left}
}
@media(max-width:640px){
 .hero h1{font-size:54px}
 .hero-covers{gap:14px;grid-template-columns:1fr 1fr}
 .hero-library-head{grid-template-columns:1fr}
 .hero-library-head strong{font-size:20px}
}
@media(max-width:480px){
 .hero-covers{grid-template-columns:1fr}
}
''')
index.write_text(s, encoding='utf-8')
print('Applied V5.8 release patch')
