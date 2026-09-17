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

print('Applied canonical What The Snow Remembers title and cache-busting cover path')
