from pathlib import Path
import re
import sys

root = Path(sys.argv[1])
MARKER = "/* MOBILE-V6.2 mobile Hadal sequence correction */"
CACHE_TAG = "mobile61-20260918"

CSS = r'''
/* MOBILE-V6.1 corrective pass */
@media(max-width:680px){
  .home .published-book,
  .home .published-book:nth-child(even){
    grid-template-columns:minmax(0,1fr) !important;
    min-height:0 !important;
    width:100% !important;
  }
  .home .published-book:nth-child(even) .published-cover,
  .home .published-book:nth-child(even) .published-copy{
    order:initial !important;
  }
  .home .published-cover{
    width:100% !important;
    min-width:0 !important;
    min-height:0 !important;
    height:auto !important;
  }
  .home .published-cover picture{
    display:block !important;
    width:100% !important;
    height:auto !important;
  }
  .home .published-cover img{
    display:block !important;
    width:100% !important;
    height:auto !important;
    min-height:0 !important;
    max-width:100% !important;
    object-fit:contain !important;
    object-position:center !important;
  }
  .home .published-copy{
    width:100% !important;
    min-width:0 !important;
  }
}
@media(max-width:620px){
  .home .hadal-latest-book{
    grid-template-columns:minmax(0,1fr) !important;
    gap:22px !important;
  }
  .home .hadal-latest-cover{
    display:block !important;
    width:min(100%,300px) !important;
    max-width:300px !important;
    margin:0 auto 4px !important;
    aspect-ratio:2/3 !important;
    overflow:hidden !important;
    background:#111 !important;
  }
  .home .hadal-latest-cover picture{
    display:block !important;
    width:100% !important;
    height:100% !important;
  }
  .home .hadal-latest-cover img{
    display:block !important;
    width:100% !important;
    height:100% !important;
    max-width:none !important;
    aspect-ratio:auto !important;
    object-fit:cover !important;
    object-position:center center !important;
  }
  .home .hadal-grid .cover-wrap{
    aspect-ratio:2/3 !important;
    overflow:hidden !important;
    background:#111 !important;
  }
  .home .hadal-grid .cover-wrap picture{
    display:block !important;
    width:100% !important;
    height:100% !important;
  }
  .home .hadal-grid .cover-wrap img{
    display:block !important;
    width:100% !important;
    height:100% !important;
    max-width:none !important;
    object-fit:cover !important;
    object-position:center center !important;
  }
  .home .contact-field label{font-size:11px !important;}
  .home .contact-submit{font-size:11px !important;letter-spacing:.14em !important;}
}
'''

def cache_bust(text, filename):
    pat = re.escape(filename) + r'(?:\?v=[A-Za-z0-9._-]+)?'
    return re.sub(pat, filename + '?v=' + CACHE_TAG, text)

def normalise_img(text, filename, width, height):
    pattern = re.compile(
        r'<img\b[^>]*?src="[^"]*' + re.escape(filename) + r'(?:\?v=[^"]*)?"[^>]*>',
        re.I
    )

    def repl(match):
        tag = match.group(0)
        tag = re.sub(
            r'\s(?:width|height|loading|fetchpriority)="[^"]*"',
            '',
            tag,
            flags=re.I
        )
        tag = re.sub(r'\s*/?>$', '', tag)
        return (
            tag
            + f' width="{width}" height="{height}"'
            + ' loading="eager" fetchpriority="auto"/>'
        )

    return pattern.sub(repl, text)

pages = [root / 'index.html', *sorted((root / 'books').glob('*.html'))]

for path in pages:
    if not path.exists():
        continue

    text = path.read_text(encoding='utf-8')

    if MARKER not in text:
        if '</style>' not in text:
            raise SystemExit(f'No style block found in {path}')
        text = text.replace('</style>', CSS + '\n</style>', 1)

    text = text.replace(
        'Frederick Samuel Author Website V6.0',
        'Frederick Samuel Author Website V6.1'
    )

    text = text.replace('Frederick Samuel Author Website V6.1', 'Frederick Samuel Author Website V6.2')\n\n    text = cache_bust(text, 'the-listening-tide.webp')
    text = cache_bust(text, 'verdant-ascension.webp')

    text = normalise_img(text, 'the-listening-tide.webp', 400, 640)
    text = normalise_img(text, 'verdant-ascension.webp', 400, 600)

    path.write_text(text, encoding='utf-8')

print(f'Applied V6.2 mobile corrective pass to {len(pages)} HTML pages')
