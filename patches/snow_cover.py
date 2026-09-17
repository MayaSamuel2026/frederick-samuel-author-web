from pathlib import Path
import base64
import hashlib
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

# The canonical cover candidate is a complete WebP Base64 payload in .001.
# Replace the earlier truncated intermediate source at release time, while the
# workflow's SHA-256 guard remains the final artwork-integrity authority.
source = Path('cover-source/what-the-snow-remembers.001.b64')
target = Path('cover-source/what-the-snow-remembers.b64')
if not source.exists():
    raise SystemExit('Canonical What The Snow Remembers cover source is missing')
payload = base64.b64decode(source.read_text(encoding='ascii'), validate=True)
if payload[:4] != b'RIFF' or payload[8:12] != b'WEBP':
    raise SystemExit('Canonical What The Snow Remembers source is not a WebP image')
target.write_bytes(source.read_bytes())
print(f'What The Snow Remembers candidate: {len(payload)} bytes, sha256={hashlib.sha256(payload).hexdigest()}')
print('Applied canonical What The Snow Remembers title, cover path and release source')
