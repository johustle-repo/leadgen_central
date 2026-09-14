<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Maintenance — LeadGen Central</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #07111f;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            max-width: 26rem;
            text-align: center;
        }
        img {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 1.375rem;
            font-weight: 600;
            margin: 0 0 10px;
            color: #ffffff;
        }
        p {
            font-size: 0.9375rem;
            line-height: 1.6;
            color: #94a3b8;
            margin: 0;
        }
        .retry {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            font-size: 0.8125rem;
            color: #67e8f9;
        }
        .dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #67e8f9;
            animation: pulse 1.4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="/logo.png" alt="LeadGen Central">
        <h1>We'll be right back</h1>
        <p>LeadGen Central is undergoing brief scheduled maintenance. This usually only takes a few minutes — this page will refresh automatically.</p>
        <div class="retry">
            <span class="dot"></span>
            Checking again shortly&hellip;
        </div>
    </div>
</body>
</html>
