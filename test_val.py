
import json, subprocess
locales = ['locales/en.json', 'locales/ckb.json', 'locales/ar.json']
data = {}
print('=== Locale JSON Validation ===')
for loc in locales:
    try:
        with open(loc, 'r', encoding='utf-8') as f:
            data[loc] = json.load(f)
        print(f'{loc}: Valid JSON')
    except Exception as e:
        print(f'{loc}: Invalid/Error:', e)
        data[loc] = None

print('
=== Comparing Locale Keys ===')
if all(data.values()):
    def get_paths(d, parent=''):
        paths = set()
        if isinstance(d, dict):
            for k, v in d.items():
                path = f'{parent}.{k}' if parent else k
                paths.add(path)
                paths.update(get_paths(v, path))
        return paths
    paths_en = get_paths(data['locales/en.json'])
    paths_ckb = get_paths(data['locales/ckb.json'])
    paths_ar = get_paths(data['locales/ar.json'])
    if paths_en == paths_ckb and paths_en == paths_ar:
        print('All three locale key sets exactly match')
    else:
        print('Mismatch in locale key sets')
        if not (paths_en == paths_ckb):
            print('en vs ckb differences:', paths_en ^ paths_ckb)
        if not (paths_en == paths_ar):
            print('en vs ar differences:', paths_en ^ paths_ar)
else:
    print('Failed to analyze because JSON is invalid.')

print('
=== PHP Linting ===')
for php_file in ['src/project_settings.php', 'src/includes/project_settings.php']:
    try:
        res = subprocess.run(['php', '-l', php_file], capture_output=True, text=True)
        if res.returncode == 0:
            print(f'{php_file}: OK')
        else:
            print(f'{php_file}: FAILED
{res.stdout.strip()}
{res.stderr.strip()}')
    except Exception as e:
        print(f'{php_file}: Error -', e)

print('
=== Curl Requests ===')
for lang in ['en', 'ckb', 'ar']:
    url = f'http://localhost/project_1/src/project_settings.php?lang={lang}'
    try:
        res = subprocess.run(['curl', '-I', '-s', '-w', '%{http_code}', '-o', '/dev/null', url], capture_output=True, text=True)
        print(f'{url} -> HTTP {res.stdout.strip()}')
    except Exception as e:
        print(f'{url} -> Error:', e)
