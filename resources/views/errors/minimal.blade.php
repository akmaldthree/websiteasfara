<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — Asfara</title>
    <style>
        :root{color-scheme:light;--ink:#20231f;--sage:#eef0e8;--sand:#f8f4ec;--line:#c8c9be;--accent:#8b7451}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,var(--sage),var(--sand));color:var(--ink);font-family:Arial,sans-serif}.error-shell{width:min(92vw,720px);padding:clamp(38px,8vw,84px);background:rgba(255,255,255,.72);border:1px solid var(--line);text-align:center}.wordmark{display:inline-block;margin-bottom:42px;color:inherit;font-size:15px;font-weight:700;letter-spacing:.38em;text-decoration:none}.code{display:block;color:var(--accent);font-size:13px;letter-spacing:.24em}.error-shell h1{margin:14px 0 18px;font-family:Georgia,serif;font-size:clamp(38px,7vw,72px);font-weight:400;line-height:1.05}.error-shell p{max-width:520px;margin:0 auto 30px;color:#5a5d56;line-height:1.7}.actions{display:flex;flex-wrap:wrap;justify-content:center;gap:12px}.actions a{padding:13px 22px;border:1px solid var(--ink);color:inherit;font-size:12px;font-weight:700;letter-spacing:.08em;text-decoration:none}.actions a:first-child{background:var(--ink);color:white}@media(max-width:520px){.error-shell{padding:36px 24px}.wordmark{margin-bottom:30px}.actions a{width:100%}}
    </style>
</head>
<body>
<main class="error-shell">
    <a class="wordmark" href="{{ url('/') }}">ASFARA</a>
    <span class="code">@yield('code')</span>
    <h1>@yield('heading')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        <a href="{{ url('/') }}">KEMBALI KE BERANDA</a>
        @hasSection('secondary')@yield('secondary')@endif
    </div>
</main>
</body>
</html>
