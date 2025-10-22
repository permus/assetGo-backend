<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Locations QR Codes - {{ $companyName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2563eb;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .header p {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .qr-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .qr-image {
            width: 150px;
            height: 150px;
            margin: 0 auto 15px auto;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .location-name {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }
        .location-type {
            font-size: 12px;
            color: #6b7280;
            background-color: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
        }
        .location-address {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .location-url {
            font-size: 10px;
            color: #9ca3af;
            word-break: break-all;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .footer p {
            color: #6b7280;
            margin: 0;
            font-size: 12px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Locations QR Codes</h1>
        <p>{{ $companyName }} • Generated on {{ $generatedAt }}</p>
    </div>

    <div class="qr-grid">
        @foreach($qrCodes as $index => $qrData)
            @if($index > 0 && $index % 6 == 0)
                <div class="page-break"></div>
            @endif
            <div class="qr-card">
                <img src="{{ $qrData['qr_base64'] }}" alt="QR Code for {{ $qrData['location']->name }}" class="qr-image">
                <div class="location-name">{{ $qrData['location']->name }}</div>
                <div class="location-type">{{ $qrData['location']->type->name ?? 'Unknown Type' }}</div>
                @if($qrData['location']->address)
                    <div class="location-address">{{ $qrData['location']->address }}</div>
                @endif
                @if($qrData['location']->description)
                    <div class="location-address">{{ $qrData['location']->description }}</div>
                @endif
                <div class="location-url">{{ $qrData['location']->public_url }}</div>
            </div>
        @endforeach
    </div>

    <div class="footer">
        <p>This document contains QR codes for all locations in {{ $companyName }}. Each QR code links to the location's public page.</p>
        <p>Total locations: {{ count($qrCodes) }} • Generated on {{ $generatedAt }}</p>
    </div>
</body>
</html>
