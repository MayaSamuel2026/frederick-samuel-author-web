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
    s = s.replace('Frederick Samuel Author Website V5.8', 'Frederick Samuel Author Website V5.9')
    s = s.replace('Frederick Samuel Author Website V5.7', 'Frederick Samuel Author Website V5.9')
    s = s.replace('Frederick Samuel Author Website V5.6', 'Frederick Samuel Author Website V5.9')
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

# V5.9 contact section: same editorial system, real Hostinger/PHP submission endpoint.
contact_css = r'''
/* V5.9 contact */
.contact-section{position:relative;padding:118px 0 124px;background:radial-gradient(circle at 88% 12%,rgba(156,59,49,.11),transparent 28%),#0c0f10;border-top:1px solid rgba(255,255,255,.08);overflow:hidden}
.contact-section:after{content:"";position:absolute;right:-170px;bottom:-280px;width:620px;height:620px;border:1px solid rgba(255,255,255,.045);border-radius:50%;box-shadow:0 0 0 85px rgba(255,255,255,.014),0 0 0 170px rgba(255,255,255,.008);pointer-events:none}
.contact-grid{position:relative;z-index:1;display:grid;grid-template-columns:minmax(300px,.82fr) minmax(420px,1.18fr);gap:clamp(55px,8vw,125px);align-items:start}
.contact-intro h2{font:400 clamp(54px,6.4vw,94px)/.9 var(--serif);letter-spacing:-.03em;margin:24px 0 28px;max-width:650px}.contact-intro h2 em{font-weight:400;color:#aaa69d}
.contact-intro p{max-width:520px;font:400 20px/1.55 var(--serif);color:#a8aba7;margin-bottom:30px}.contact-email{display:inline-block;padding-bottom:7px;border-bottom:1px solid rgba(255,255,255,.28);font:400 clamp(18px,1.8vw,25px)/1.2 var(--serif);color:#eee9df;transition:.25s}.contact-email:hover{color:#fff;border-color:#fff}
.contact-form{border-top:1px solid rgba(255,255,255,.18);padding-top:8px}.contact-fields{display:grid;grid-template-columns:1fr 1fr;column-gap:28px}.contact-field{position:relative;padding:25px 0 18px;border-bottom:1px solid rgba(255,255,255,.15)}.contact-field.full{grid-column:1/-1}.contact-field label{display:block;margin-bottom:12px;font-size:8px;letter-spacing:.2em;text-transform:uppercase;color:#777d79}.contact-field input,.contact-field select,.contact-field textarea{width:100%;border:0;outline:0;background:transparent;color:#f1ede5;font:400 20px/1.4 var(--serif);padding:0;border-radius:0}.contact-field input::placeholder,.contact-field textarea::placeholder{color:#656a67}.contact-field select{appearance:none;cursor:pointer;background-image:linear-gradient(45deg,transparent 50%,#858985 50%),linear-gradient(135deg,#858985 50%,transparent 50%);background-position:calc(100% - 12px) 48%,calc(100% - 7px) 48%;background-size:5px 5px,5px 5px;background-repeat:no-repeat;padding-right:32px}.contact-field select option{background:#111516;color:#f1ede5}.contact-field textarea{min-height:145px;resize:vertical}.contact-field:focus-within{border-color:#d8d4ca}.contact-field:focus-within label{color:#c6c3bb}
.contact-honey{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}
.contact-actions{display:flex;align-items:center;justify-content:space-between;gap:24px;padding-top:25px}.contact-note{max-width:430px;font-size:9px;line-height:1.6;letter-spacing:.04em;color:#717571}.contact-submit{min-height:52px;padding:0 24px;border:1px solid #eee9df;background:#eee9df;color:#101313;font-size:9px;letter-spacing:.17em;text-transform:uppercase;cursor:pointer;transition:.25s}.contact-submit:hover{background:#fff;border-color:#fff;transform:translateY(-2px)}
@media(max-width:920px){.navlinks{display:none}.menu{display:block}.contact-grid{grid-template-columns:1fr;gap:50px}.contact-intro{max-width:720px}}
@media(max-width:620px){.contact-section{padding:82px 0 88px}.contact-fields{grid-template-columns:1fr}.contact-field{grid-column:1/-1}.contact-actions{display:block}.contact-submit{width:100%;margin-top:20px}.contact-intro h2{font-size:52px}.contact-intro p{font-size:18px}}
'''
if '/* V5.9 contact */' not in s:
    s = s.replace('</style>', contact_css + '\n</style>', 1)

# Add contact to both desktop and mobile navigation.
if 'href="index.html#contact"' not in s:
    s = s.replace('<a href="index.html#representation">Representation</a>', '<a href="index.html#representation">Representation</a><a href="index.html#contact">Contact</a>')

contact_html = r'''
<section class="contact-section" id="contact"><div class="shell contact-grid">
<div class="contact-intro reveal">
<div class="sec-no">06 · Contact</div>
<h2>Start a<br/><em>conversation.</em></h2>
<p>For literary representation, publishing and rights enquiries, interviews, events or reader correspondence, you can write directly or use the form.</p>
<a class="contact-email" href="mailto:frederick@fredericksamuel.com">frederick@fredericksamuel.com</a>
</div>
<form class="contact-form reveal" action="contact.php" method="post" accept-charset="UTF-8">
<div class="contact-fields">
<div class="contact-field"><label for="contact-name">Name</label><input id="contact-name" name="name" type="text" autocomplete="name" maxlength="120" placeholder="Your name" required/></div>
<div class="contact-field"><label for="contact-email">Email</label><input id="contact-email" name="email" type="email" autocomplete="email" maxlength="200" placeholder="you@example.com" required/></div>
<div class="contact-field full"><label for="contact-topic">Regarding</label><select id="contact-topic" name="topic" required><option value="" selected disabled>Select an enquiry</option><option value="representation">Literary representation</option><option value="rights">Publishing &amp; rights</option><option value="media">Media &amp; interviews</option><option value="events">Events &amp; speaking</option><option value="reader">Reader correspondence</option><option value="other">Other</option></select></div>
<div class="contact-field full"><label for="contact-message">Message</label><textarea id="contact-message" name="message" maxlength="5000" placeholder="Write your message here…" required></textarea></div>
<div class="contact-honey" aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off"/></div>
</div>
<div class="contact-actions"><div class="contact-note">Your details are used only to respond to this enquiry and are not added to a mailing list.</div><button class="contact-submit" type="submit">Send message →</button></div>
</form>
</div></section>
'''
if 'id="contact"' not in s:
    s = s.replace('</main>', contact_html + '\n</main>', 1)

index.write_text(s, encoding='utf-8')
print('Applied V5.9 release patch with contact form')
