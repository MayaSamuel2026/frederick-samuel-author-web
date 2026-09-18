from pathlib import Path
import sys

root = Path(sys.argv[1])
legacy_cover = 'assets/covers/the-snowblind-protocol.webp'
replacement_cover = 'assets/covers/what-the-snow-remembers.webp'
replacement_path = root / replacement_cover

if not replacement_path.exists():
    raise SystemExit(f'Missing qualified replacement cover: {replacement_cover}')

for path in [root / 'index.html', *sorted((root / 'books').glob('*.html'))]:
    if not path.exists():
        continue
    text = path.read_text(encoding='utf-8')
    text = text.replace(legacy_cover, replacement_cover)
    text = text.replace('The Snowblind Protocol', 'What The Snow Remembers')
    path.write_text(text, encoding='utf-8')

legacy_path = root / legacy_cover
if legacy_path.exists():
    legacy_path.unlink()

print('Applied qualified What The Snow Remembers cover and removed legacy Snowblind artwork')
