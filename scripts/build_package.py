#!/usr/bin/env python3
"""Build the install package using an explicit distribution allowlist."""
from pathlib import Path
import hashlib
import json
import zipfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = '0.1.0-beta.1'
NAME = f'connect-cms-yuyucalendar-{VERSION}'
SCOPES = (
    'app/Plugins/User/Yuyucalendar',
    'resources/views/plugins/user/yuyucalendar',
    'resources/views/plugins/user/calendars/yuyucalendar',
    'integrations/yuyutodo',
)
FILES = (
    'database/seeders/YuyuCalendarPluginSeeder.php',
    'resources/views/plugins/user/calendars/yuyucalendar_return_scripts.blade.php',
    'README.md', 'LICENSE', 'docs/yuyu-calendar.md',
)

def main():
    files = [ROOT / name for name in FILES]
    for scope in SCOPES:
        files.extend(p for p in (ROOT / scope).rglob('*') if p.is_file())
    files = sorted(set(files))
    assert all(p.exists() for p in files)
    manifest = {
        'version': VERSION,
        'connect_cms': '1.44.1',
        'files': {p.relative_to(ROOT).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in files},
    }
    output = ROOT / 'downloads'
    output.mkdir(exist_ok=True)
    destination = output / f'{NAME}.zip'
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
        for p in files:
            info = zipfile.ZipInfo(p.relative_to(ROOT).as_posix(), (2026, 10, 4, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, p.read_bytes())
        info = zipfile.ZipInfo('PACKAGE-MANIFEST.json', (2026, 10, 4, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
    digest = hashlib.sha256(destination.read_bytes()).hexdigest()
    (output / f'{NAME}.zip.sha256').write_text(f'{digest}  {NAME}.zip\n')
    print(f'{destination}: {len(files)} source files, SHA256 {digest}')

if __name__ == '__main__':
    main()
