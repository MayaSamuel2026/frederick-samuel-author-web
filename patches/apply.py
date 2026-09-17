from pathlib import Path
import re, sys

root = Path(sys.argv[1])
html_files = [root / 'index.html', *sorted((root / 'books').glob('*.html'))]

MOBILE_CSS = r'''
/* MOBILE-V6 responsive qualification */
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{min-width:0}
img{height:auto}
a,button{-webkit-tap-highlight-color:transparent;touch-action:manipulation}
section[id]{scroll-margin-top:92px}
.menu:focus-visible,.mobile a:focus-visible,.btn:focus-visible,.contact-submit:focus-visible,.cycle-nav a:focus-visible,.navrow a:focus-visible,.back:focus-visible{outline:2px solid currentColor;outline-offset:4px}

@media (prefers-reduced-motion:reduce){
 html{scroll-behavior:auto}
 *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
 .reveal{opacity:1!important;transform:none!important}
 .track{animation:none!important}
}

@media(max-width:920px){
 .home .shell{width:calc(100% - 40px)}
 .home .topline{height:auto;min-height:30px;padding:7px 20px;line-height:1.4;font-size:8px;letter-spacing:.13em;text-align:center}
 .home .nav{height:68px}
 .home .nav .shell{width:calc(100% - 32px)}
 .home .navlinks{display:none}
 .home .brand{font-size:18px;letter-spacing:.12em;white-space:nowrap}
 .home .brand small{display:none}
 .home .menu{display:grid;place-items:center;width:44px;height:44px;padding:0;border-radius:0;cursor:pointer;line-height:1}
 .home .mobile{padding:6px 20px 14px;background:rgba(13,16,16,.98);border-bottom:1px solid var(--line);box-shadow:0 18px 30px rgba(0,0,0,.2)}
 .home .mobile.open{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0}
 .home .mobile a{display:flex;align-items:center;min-height:48px;padding:8px 4px;border-bottom:1px solid rgba(255,255,255,.08);font-size:10px;line-height:1.35}
 .home .hero{min-height:auto}
 .home .hero-inner{grid-template-columns:1fr!important;gap:40px!important;padding-top:56px!important;padding-bottom:60px!important}
 .home .hero h1{font-size:clamp(52px,12vw,78px)!important;line-height:.88;margin:22px 0 24px}
 .home .hero .lead{font-size:clamp(20px,4vw,24px);line-height:1.45}
 .home .hero-library{justify-self:stretch!important;max-width:720px!important;width:100%;padding-left:0}
 .home .hero-library-head{margin-bottom:20px}
 .home .hero-library-head strong{font-size:24px}
 .home .hero-covers{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important;max-width:720px}
 .home .hero-book-meta b{font-size:18px}
 .home .actions{gap:12px;margin-top:26px}
 .home .btn{min-height:48px;padding:0 18px}
 .home .section{padding:72px 0}
 .home .section-head{margin-bottom:40px}
 .home .section-head p{font-size:18px;line-height:1.55}
 .home .published-card{min-height:auto;padding:30px}
 .home .published-card p{font-size:14px;line-height:1.7}
 .home .card-meta p{font-size:13px;line-height:1.6}
 .home .hadal-intro{gap:28px;margin-bottom:42px;padding-bottom:34px}
 .home .hadal-intro p{font-size:22px;line-height:1.48}
 .home .about-grid,.home .rep-grid{gap:42px}
 .home .about-copy{font-size:15px;line-height:1.8}
 .home .rep-copy{font-size:21px;line-height:1.5}
 .home .rep-row{min-height:44px;align-items:center}
 .home .contact-section{padding:78px 0 84px}
 .home .contact-grid{gap:44px}
 .home .contact-intro p{font-size:19px}
 .home .contact-field label{font-size:9px}
 .home .contact-submit{min-height:50px}
 .home .footer{padding:32px 0 38px}
 .home .footer .shell{align-items:flex-start}
 .home .book-modal{overscroll-behavior:contain}
 .home .modal-close{width:48px;height:48px;right:14px;top:14px;font-size:24px}
 .home .modal-shell{width:calc(100% - 36px);grid-template-columns:1fr!important;gap:32px!important;align-items:start;margin:0 auto;padding:76px 0 46px;min-height:auto}
 .home .modal-cover{max-width:260px;margin:auto}
 .home .modal-copy h2{font-size:clamp(46px,12vw,62px);line-height:.92}
 .home .modal-copy .tag{font-size:22px}
 .home .modal-copy .sum{font-size:17px;line-height:1.65}

 .book-detail .shell{width:calc(100% - 36px)}
 .book-detail .nav{height:68px}
 .book-detail .nav .shell{gap:14px}
 .book-detail .brand{font-size:16px;letter-spacing:.1em;white-space:nowrap}
 .book-detail .back{display:flex;align-items:center;justify-content:flex-end;min-height:44px;font-size:9px;line-height:1.3;text-align:right}
 .book-detail .hero{padding:34px 0 54px}
 .book-detail .grid{grid-template-columns:1fr;gap:34px}
 .book-detail .cover{width:min(78vw,320px);max-width:320px;margin:0 auto}
 .book-detail .copy h1{font-size:clamp(46px,13vw,64px);line-height:.9;margin:18px 0 20px;overflow-wrap:normal}
 .book-detail .tag{font-size:22px;line-height:1.4;margin-bottom:24px}
 .book-detail .summary{font-size:18px;line-height:1.65}
 .book-detail .summary p{margin-bottom:20px}
 .book-detail .themes{gap:8px;margin-top:24px}
 .book-detail .theme{display:inline-flex;align-items:center;min-height:36px;padding:8px 11px}
 .book-detail .context{padding:54px 0 58px}
 .book-detail .context-grid{grid-template-columns:1fr;gap:24px}
 .book-detail .context h2{font-size:38px;line-height:1.05}
 .book-detail .context p{font-size:18px;line-height:1.65}
 .book-detail .navrow{display:grid;grid-template-columns:1fr;gap:0;margin-top:32px;padding-top:12px}
 .book-detail .navrow a{display:flex;align-items:center;min-height:48px;padding:10px 0;border-bottom:1px solid rgba(20,20,20,.11);line-height:1.35}
 .book-detail .navrow a:last-child{justify-content:flex-end;text-align:right}
 .book-detail .footer{padding:28px 0}
}

@media(max-width:620px){
 .home .shell{width:calc(100% - 32px)}
 .home .nav .shell{width:calc(100% - 28px)}
 .home .mobile{padding-left:16px;padding-right:16px}
 .home .mobile.open{grid-template-columns:1fr}
 .home .hero-inner{padding-top:46px!important;padding-bottom:50px!important;gap:32px!important}
 .home .hero h1{font-size:clamp(48px,15vw,64px)!important}
 .home .hero .lead{font-size:20px}
 .home .hero-library-head{grid-template-columns:1fr;gap:7px}
 .home .hero-library-head strong{text-align:left}
 .home .section{padding:62px 0}
 .home .section h2{font-size:clamp(43px,12vw,54px);line-height:.95}
 .home .book-grid,.home .hadal-grid{grid-template-columns:1fr;gap:42px}
 .home .published-card{padding:26px}
 .home .published-card h3{font-size:clamp(43px,12vw,54px)}
 .home .cover-wrap:after{display:none}
 .home .card-meta{padding-top:16px}
 .home .card-meta h3,.home .hadal-grid .card-meta h3{font-size:29px;line-height:1.02}
 .home .hadal-mark{width:58px;height:58px;font-size:32px}
 .home .hadal-intro p{font-size:20px}
 .home .about-big{font-size:clamp(34px,10vw,48px)}
 .home .about-copy .pull{font-size:22px}
 .home .rep-grid .big{font-size:clamp(43px,12vw,58px)}
 .home .rep-row{display:block;padding:14px 0}
 .home .rep-row span{display:block}
 .home .rep-row span:last-child{text-align:left;margin-top:7px}
 .home .contact-section{padding:66px 0 72px}
 .home .contact-intro h2{font-size:clamp(48px,14vw,60px)}
 .home .contact-actions{display:block}
 .home .contact-submit{width:100%;margin-top:20px}
 .home .footer .shell{display:block}
 .home .footer p{text-align:left;margin-top:12px;line-height:1.6}
 .home .modal-cover{max-width:230px}
 .home .modal-copy h2{font-size:clamp(43px,13vw,54px)}

 .book-detail .shell{width:calc(100% - 30px)}
 .book-detail .hero{padding-top:28px}
 .book-detail .cover{width:min(82vw,290px)}
 .book-detail .copy h1{font-size:clamp(44px,14vw,58px)}
 .book-detail .tag{font-size:21px}
 .book-detail .summary,.book-detail .context p{font-size:17px}
 .book-detail .context h2{font-size:35px}
}

@media(max-width:440px){
 .home .topline{font-size:7px;letter-spacing:.1em}
 .home .brand{font-size:16px;letter-spacing:.1em}
 .home .hero-covers{grid-template-columns:1fr!important;gap:28px!important}
 .home .hero-book{max-width:300px}
 .home .actions{display:grid;grid-template-columns:1fr}
 .home .btn{width:100%}
 .home .section-head{margin-bottom:34px}
 .home .published-card{padding:24px}
 .home .contact-email{font-size:18px;overflow-wrap:anywhere}
 .home .contact-field input,.home .contact-field select,.home .contact-field textarea{font-size:18px}

 .book-detail .brand{font-size:14px;letter-spacing:.08em}
 .book-detail .back{font-size:8px;letter-spacing:.11em;max-width:112px}
 .book-detail .copy h1{font-size:clamp(42px,14vw,54px)}
}
'''

MOBILE_JS = r'''
<script id="mobileNavController">
(function(){
  const menu=document.querySelector('.menu');
  const mobile=document.getElementById('mobileNav');
  if(!menu||!mobile) return;
  const setOpen=(open)=>{
    mobile.classList.toggle('open',open);
    menu.setAttribute('aria-expanded',String(open));
    menu.setAttribute('aria-label',open?'Close navigation':'Open navigation');
  };
  menu.addEventListener('click',()=>setOpen(!mobile.classList.contains('open')));
  mobile.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setOpen(false)));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
  window.addEventListener('resize',()=>{if(window.innerWidth>920)setOpen(false)},{passive:true});
})();
</script>
'''

for path in html_files:
    if not path.exists():
        continue
    s = path.read_text(encoding='utf-8')
    # Standardize deployment on WebP so Hostinger only needs one image format.
    s = re.sub(r'<source\s+srcset="[^"]+\.avif"\s+type="image/avif"\s*/>', '', s)
    s = s.replace('<source id="modalSourceAvif" type="image/avif"/>', '')
    s = s.replace("  document.getElementById('modalSourceAvif').srcset=b.image.avif;\n", '')
    s = re.sub(r'"image":\{"avif":"[^"]+\.avif","webp":', '"image":{"webp":', s)
    s = s.replace('Frederick Samuel Author Website V5.9', 'Frederick Samuel Author Website V6.0')
    s = s.replace('Frederick Samuel Author Website V5.8', 'Frederick Samuel Author Website V6.0')
    s = s.replace('Frederick Samuel Author Website V5.7', 'Frederick Samuel Author Website V6.0')
    s = s.replace('Frederick Samuel Author Website V5.6', 'Frederick Samuel Author Website V6.0')
    # Canonical title rename: update visible headings, cards, metadata, page title and alt text.
    s = s.replace('The Snowblind Protocol', 'What The Snow Remembers')
    # Mobile viewport + page scope. Do not prevent user zoom.
    s = s.replace('content="width=device-width,initial-scale=1"', 'content="width=device-width,initial-scale=1,viewport-fit=cover"')
    if path == root / 'index.html':
        if '<body class="home">' not in s:
            s = s.replace('<body>', '<body class="home">', 1)
    else:
        if '<body class="book-detail">' not in s:
            s = s.replace('<body>', '<body class="book-detail">', 1)
    if '/* MOBILE-V6 responsive qualification */' not in s:
        s = s.replace('</style>', MOBILE_CSS + '\n</style>', 1)
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

# Accessible, stateful mobile navigation. Remove legacy inline toggle so one click has one state transition.
s = re.sub(r'\s+onclick="document\.querySelector\(\'\.mobile\'\)\.classList\.toggle\(\'open\'\)"', '', s)
if 'aria-controls="mobileNav"' not in s:
    s = s.replace('<button aria-label="Open navigation" class="menu">☰</button>', '<button type="button" aria-label="Open navigation" aria-controls="mobileNav" aria-expanded="false" class="menu">☰</button>', 1)
if 'id="mobileNav"' not in s:
    s = s.replace('<div class="mobile">', '<div class="mobile" id="mobileNav" role="navigation" aria-label="Mobile navigation">', 1)
if 'id="mobileNavController"' not in s:
    s = s.replace('</body>', MOBILE_JS + '\n</body>', 1)

index.write_text(s, encoding='utf-8')

# Fail the build immediately if any mobile contract regresses.
for path in html_files:
    text = path.read_text(encoding='utf-8')
    if 'viewport-fit=cover' not in text:
        raise SystemExit(f'Mobile QA failed: viewport-fit missing in {path.name}')
    if '/* MOBILE-V6 responsive qualification */' not in text:
        raise SystemExit(f'Mobile QA failed: responsive layer missing in {path.name}')
    if path == index:
        if '<body class="home">' not in text:
            raise SystemExit('Mobile QA failed: homepage scope missing')
    elif '<body class="book-detail">' not in text:
        raise SystemExit(f'Mobile QA failed: book-detail scope missing in {path.name}')

homepage = index.read_text(encoding='utf-8')
for required in ['aria-controls="mobileNav"', 'aria-expanded="false"', 'id="mobileNav"', 'id="mobileNavController"']:
    if required not in homepage:
        raise SystemExit(f'Mobile QA failed: homepage navigation contract missing {required}')
if "classList.toggle('open')\">☰" in homepage:
    raise SystemExit('Mobile QA failed: legacy inline mobile toggle remained')

print(f'Applied V6.0 mobile optimization and qualified {len(html_files)} HTML pages')