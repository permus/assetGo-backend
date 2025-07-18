<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QR Code - {{ $location->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .qr-container {
            border: 2px solid #000;
            padding: 20px;
            margin: 20px auto;
            max-width: 500px;
            background: white;
        }
        .location-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .location-path {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        .qr-code {
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 300px;
            height: auto;
        }
        .location-url {
            font-size: 12px;
            color: #888;
            margin-top: 15px;
            word-break: break-all;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <div class="location-name">{{ $location->name }}</div>
        
        @if($location->full_path !== $location->name)
            <div class="location-path">{{ $location->full_path }}</div>
        @endif
        
        <div class="qr-code">
            <img src="data:image/png;base64,{{ $qrImageData }}" alt="QR Code for {{ $location->name }}">
        </div>
        
        <div class="location-url">{{ $location->public_url }}</div>
        
        @if($location->description)
            <div style="margin-top: 15px; font-size: 12px; color: #666;">
                {{ $location->description }}
            </div>
        @endif
    </div>
    
    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }} | {{ config('app.name') }}
    </div>
</body>
</html>