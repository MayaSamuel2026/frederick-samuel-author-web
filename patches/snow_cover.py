from pathlib import Path
import sys

root = Path(sys.argv[1])
old = 'assets/covers/the-snowblind-protocol.webp'
new = 'assets/covers/what-the-snow-remembers.webp'

for path in [root / 'index.html', *sorted((root / 'books').glob('*.html'))]:
    if not path.exists():
        continue
    text = path.read_text(encoding='utf-8')
    text = text.replace(old, new)
    text = text.replace('The Snowblind Protocol', 'What The Snow Remembers')
    path.write_text(text, encoding='utf-8')

# The canonical cover is stored as a complete, valid Base64 source in .001.
# A previous intermediate file was truncated; make the release assembler consume
# the canonical source and let the workflow checksum guard the exact artwork.
source = Path('cover-source/what-the-snow-remembers.001.b64')
target = Path('cover-source/what-the-snow-remembers.b64')
if not source.exists():
    raise SystemExit('Canonical What The Snow Remembers cover source is missing')
target.write_bytes(source.read_bytes())

print('Applied canonical What The Snow Remembers title, cover path and release source')
