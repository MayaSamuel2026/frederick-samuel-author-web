from pathlib import Path
import sys

root = Path(sys.argv[1])
current_cover = 'assets/covers/the-snowblind-protocol.webp'
unqualified_replacement = 'assets/covers/what-the-snow-remembers.webp'

# Keep the approved new title, but preserve the currently deployed cover artwork
# until the exact replacement file (including the author name) is available.
for path in [root / 'index.html', *sorted((root / 'books').glob('*.html'))]:
    if not path.exists():
        continue
    text = path.read_text(encoding='utf-8')
    text = text.replace(unqualified_replacement, current_cover)
    text = text.replace('The Snowblind Protocol', 'What The Snow Remembers')
    path.write_text(text, encoding='utf-8')

print('Applied What The Snow Remembers title while preserving the qualified current cover asset')
