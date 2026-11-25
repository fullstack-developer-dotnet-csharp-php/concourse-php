import random, sys, urllib.request, urllib.error, urllib.parse, time

base = sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080'
seed = int(sys.argv[2]) if len(sys.argv) > 2 else 1
rounds = int(sys.argv[3]) if len(sys.argv) > 3 else 80
random.seed(seed)

junk = ['', ' ', '%00', '<script>alert(1)</script>', "' OR 1=1 --", '../../etc/passwd', 'a' * 5000, '‮\u0000😀', '{{7*7}}', '${7*7}', '-1', '9999999999999999999', 'null', '[]', 'universal studios', 'zzzzqqq', 'ñandú', '%', '&=&=', 'q[]=1']
paths = ['/', '/attractions', '/suggest', '/sitemap.xml', '/robots.txt', '/llms.txt', '/orders', '/pay/return', '/pay/result/x', '/pay/checkout/x',
         '/attractions/{j}/{j}', '/attractions/{j}/x/tickets/{j}', '/admin/logs', '/admin/settings', '/nope/{j}', '/favicon.ico']
params = ['q', 'city', 'category', 'page', 'month', 'file', 'level', 'limit', 'order_id', 'next']

def hit(method, path, data=None):
    req = urllib.request.Request(base + path, data=data, method=method)
    try:
        r = urllib.request.urlopen(req, timeout=40)
        return r.status, r.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()
    except Exception as e:
        return 0, str(e).encode()

bad = []
stats = {}
for i in range(rounds):
    p = random.choice(paths)
    while '{j}' in p:
        p = p.replace('{j}', urllib.parse.quote(random.choice(junk)[:200], safe=''), 1)
    qs = urllib.parse.urlencode({random.choice(params): random.choice(junk) for _ in range(random.randint(0, 3))})
    method = random.choice(['GET', 'GET', 'GET', 'POST'])
    data = urllib.parse.urlencode({random.choice(params): random.choice(junk) for _ in range(random.randint(0, 4))}).encode() if method == 'POST' else None
    url = p + ('?' + qs if qs else '')
    s, body = hit(method, url, data)
    stats[s] = stats.get(s, 0) + 1
    text = body.decode('utf-8', 'replace')
    flags = []
    if s >= 500 or s == 0:
        flags.append('status')
    if any(m in text for m in ('Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'Stack trace:#')) and 'Stack trace' not in text[:0]:
        flags.append('php-message')
    if '<script>alert(1)</script>' in text:
        flags.append('unescaped-xss')
    if flags:
        bad.append((method, url[:140], s, flags))
    time.sleep(0.15)

print('status counts', stats)
for b in bad:
    print('FLAG', b)
print('flags', len(bad))
