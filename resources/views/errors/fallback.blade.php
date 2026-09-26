<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | Vua Beach</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f8f9; color: #173d49; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { box-sizing: border-box; width: min(92vw, 610px); padding: 3rem; text-align: center; background: #fff; border: 1px solid #dbe9ec; border-radius: 22px; box-shadow: 0 18px 60px rgba(20, 65, 78, .12); }
        .code { margin: 0; color: #17718a; font-size: .85rem; font-weight: 800; letter-spacing: .13em; }
        h1 { margin: .65rem 0 1rem; font-size: clamp(1.8rem, 6vw, 2.8rem); line-height: 1.1; }
        p { margin: 0 auto 1.6rem; max-width: 440px; color: #607983; line-height: 1.65; }
        a { display: inline-block; padding: .75rem 1.2rem; border-radius: 10px; background: #17718a; color: #fff; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <p class="code">LỖI {{ $status }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">Về trang chủ</a>
    </main>
</body>
</html>
