<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('id_card_print', 'ID Card Print') }}</title>
    <link rel="shortcut icon" class="favicon_show" href="{{ app_setting()->favicon }}">
    <style>
        :root {
            --khmer-font-family: "Noto Sans Khmer", "Khmer OS Battambang", "Khmer OS Siemreap", "Khmer OS", "Leelawadee UI", sans-serif;
            --latin-font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            --ink: #16324f;
            --muted: #577188;
            --line: #d9e2ec;
            --bg: #eef3f8;
            --brand-a: #103c78;
            --brand-b: #1f7a8c;
            --card-preview-width: 320px;
            --card-preview-height: 512px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--latin-font-family);
            font-weight: 400;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body.km-ui,
        body.km-ui button,
        body.km-ui input,
        body.km-ui textarea,
        body.km-ui select {
            font-family: var(--khmer-font-family);
            font-weight: 400;
            line-height: 1.72;
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px 16px;
        }

        .card {
            width: var(--card-preview-width);
            min-height: var(--card-preview-height);
            background: #fff;
            border-radius: 4mm;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(15, 30, 56, 0.18);
            position: relative;
        }

        .header {
            background: linear-gradient(135deg, var(--brand-a) 0%, var(--brand-b) 100%);
            color: #fff;
            padding: 18px 18px 88px 18px;
            position: relative;
        }

        .header::after {
            content: "";
            position: absolute;
            right: -34px;
            top: 22px;
            width: 118px;
            height: 118px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

            .eyebrow {
                font-size: 12px;
                letter-spacing: 1.4px;
                text-transform: uppercase;
                opacity: 0.88;
        }

        .header-logo {
            width: 54px;
            height: 54px;
            display: block;
            margin: 10px auto 0;
            object-fit: contain;
            filter: drop-shadow(0 6px 14px rgba(8, 26, 51, 0.18));
        }

        .org-name {
            font-size: 17px;
            font-weight: 400;
            line-height: 1.5;
            margin: 12px auto 0;
            max-width: 230px;
            text-align: center;
            position: relative;
            z-index: 1;
            font-family: "Khmer M1", "Khmer OS Muol Light", var(--khmer-font-family);
        }

        .qr-note {
            position: absolute;
            right: 18px;
            top: 18px;
            font-size: 11px;
            line-height: 1.45;
            text-align: right;
            opacity: 0.86;
        }

        .content {
            background: #fff;
            padding: 76px 18px 18px 18px;
            position: relative;
            overflow: visible;
        }

        .content-watermark {
            position: absolute;
            left: 50%;
            top: 116px;
            width: 170px;
            height: 170px;
            transform: translateX(-50%);
            opacity: 0.04;
            pointer-events: none;
            z-index: 0;
        }

        .content-watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .avatar-wrap {
            position: absolute;
            left: 50%;
            top: 0;
            transform: translate(-50%, -50%);
            z-index: 2;
        }

        .avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            object-fit: contain;
            object-position: center top;
            border: 5px solid #fff;
            background: #fff;
            box-shadow: 0 4px 10px rgba(16, 60, 120, 0.08);
        }

        .identity {
            text-align: center;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        .name {
            font-size: 20px;
            font-weight: 400;
            color: #103c78;
            line-height: 1.6;
            font-family: "Khmer M1", "Khmer OS Muol Light", var(--khmer-font-family);
        }

        .position {
            font-size: 12px;
            color: #4e647a;
            margin-top: 5px;
            line-height: 1.75;
        }

        .info-table {
            margin-top: 18px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .row {
            display: flex;
        }

        .row + .row {
            border-top: 1px solid var(--line);
        }

        .label,
        .value {
            padding: 10px 12px;
            font-size: 12px;
            line-height: 1.7;
        }

        .label {
            width: 44%;
            background: #f8fbff;
            color: var(--muted);
            font-weight: 400;
        }

        .value {
            width: 56%;
            color: var(--ink);
            font-weight: 400;
            word-break: break-word;
        }

        .footer-content {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            margin-top: 18px;
            position: relative;
            z-index: 1;
        }

        .qr-column {
            width: 100px;
            text-align: center;
        }

        .qr-box {
            padding: 8px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
        }

        .qr-box img {
            width: 84px;
            height: 84px;
            display: block;
            margin: 0 auto;
        }

        .qr-help {
            font-size: 10px;
            color: #5c7288;
            margin-top: 6px;
            line-height: 1.45;
        }

        .signature-column {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-end;
        }

        .signature-block {
            width: 168px;
            text-align: center;
        }

        .signature-image {
            height: 118px;
            width: 168px;
            object-fit: contain;
            object-position: center;
            display: block;
            margin: 0 auto;
        }

        .signature-line {
            width: 168px;
            margin: 0 auto 6px;
            font-size: 11px;
            color: #20354a;
            text-align: center;
            line-height: 1.4;
            font-weight: 400;
            font-family: "Khmer M1", "Khmer OS Muol Light", var(--khmer-font-family);
        }

        .link {
            font-size: 10px;
            color: #52708c;
            word-break: break-all;
            line-height: 1.5;
            margin-top: 10px;
        }

        .footer {
            background: #103c78;
            color: #fff;
            padding: 10px 16px;
            font-size: 11px;
            text-align: center;
            line-height: 1.65;
        }

        @page {
            size: 54mm 86mm;
            margin: 0;
        }

        @media print {
            html,
            body {
                background: var(--bg) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                width: 54mm;
                height: 86mm;
                overflow: hidden;
            }

            .page,
            .card,
            .header,
            .content,
            .info-table,
            .label,
            .footer,
            .qr-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                min-height: 86mm;
                padding: 0;
                display: flex;
                align-items: flex-start;
                justify-content: flex-start;
                width: 54mm;
                height: 86mm;
                overflow: hidden;
            }

            .card {
                box-shadow: none;
                break-inside: avoid;
                width: 54mm;
                height: 86mm;
                margin: 0;
                border-radius: 3mm;
                overflow: hidden;
            }

            .header {
                padding: 2.4mm 2.4mm 11.6mm 2.4mm;
            }

            .header::after {
                right: -6mm;
                top: 3.2mm;
                width: 15mm;
                height: 15mm;
            }

            .eyebrow {
                display: none;
            }

            .header-logo {
                width: 8.5mm;
                height: 8.5mm;
                margin-top: 1.2mm;
            }

            .org-name {
                font-size: 7.2px;
                line-height: 1.25;
                margin-top: 2.2mm;
                max-width: 34mm;
            }

            .qr-note {
                right: 2.4mm;
                top: 2.4mm;
                font-size: 5px;
                line-height: 1.2;
                display: none;
            }

            .content {
                padding: 8.6mm 2.4mm 2.2mm 2.4mm;
            }

            .content-watermark {
                top: 13mm;
                width: 18mm;
                height: 18mm;
            }

            .avatar {
                width: 18mm;
                height: 18mm;
                border-width: 2px;
            }

            .identity {
                margin-top: 1.2mm;
            }

            .name {
                font-size: 8.6px;
                line-height: 1.2;
            }

            .position {
                font-size: 5.6px;
                line-height: 1.2;
                margin-top: 0.6mm;
            }

            .info-table {
                margin-top: 1.8mm;
                border-radius: 2.2mm;
            }

            .label,
            .value {
                padding: 1mm 1.2mm;
                font-size: 5.8px;
                line-height: 1.2;
            }

            .footer-content {
                gap: 1.6mm;
                margin-top: 1.8mm;
                align-items: flex-end;
            }

            .qr-column {
                width: 14mm;
            }

            .qr-box {
                padding: 1mm;
                border-radius: 2mm;
            }

            .qr-box img {
                width: 11mm;
                height: 11mm;
            }

            .qr-help {
                font-size: 4.8px;
                line-height: 1.2;
                margin-top: 0.6mm;
            }

            .signature-block {
                width: 16mm;
            }

            .signature-line {
                width: 16mm;
                margin-bottom: 0.6mm;
                font-size: 4.8px;
                line-height: 1.2;
            }

            .signature-image {
                width: 16mm;
                height: 11mm;
            }

            .footer {
                padding: 1.4mm 2.4mm;
                font-size: 4.8px;
                line-height: 1.15;
            }
        }
    </style>
</head>
<body class="{{ app()->getLocale() === 'km' ? 'km-ui' : '' }}">
    @php
        $signatureAssetPath = public_path('assets/idcard/signature.jpg');
        $signatureUrl = asset('assets/idcard/signature.jpg') . '?v=' . (file_exists($signatureAssetPath) ? filemtime($signatureAssetPath) : time());
    @endphp
    <div class="page">
        <div class="card">
            <div class="header">
                <div class="eyebrow">{{ localize('employee_id_card', 'Employee ID Card') }}</div>
                <img src="{{ app_setting()->logo }}" alt="{{ app_setting()->title }} logo" class="header-logo">
                <div class="org-name">{{ app_setting()->title }}</div>
                <div class="qr-note">
                    <div>{{ localize('scan_qr', 'Scan QR') }}</div>
                    <div>{{ localize('view_profile', 'View profile') }}</div>
                </div>
            </div>

            <div class="content">
                <div class="content-watermark" aria-hidden="true">
                    <img src="{{ app_setting()->logo }}" alt="">
                </div>

                <div class="avatar-wrap">
                    <img src="{{ $profileImageUrl }}" alt="{{ $employeeName }}" class="avatar">
                </div>

                <div class="identity">
                    <div class="name">{{ $employeeName ?? '-' }}</div>
                    <div class="position">{{ $positionName }}</div>
                </div>

                <div class="info-table">
                    @foreach (($cardRows ?? []) as $row)
                        <div class="row">
                            <div class="label">{{ $row['label'] ?? '-' }}</div>
                            <div class="value">{{ $row['value'] ?? '-' }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="footer-content">
                    <div class="qr-column">
                        <div class="qr-box">
                            <img src="data:image/png;base64,{{ $qrCodePng }}" alt="QR Code">
                        </div>
                        <div class="qr-help">{{ localize('scan_to_view_profile', 'Scan to view profile') }}</div>
                    </div>

                    <div class="signature-column">
                        <div class="signature-block">
                            <div class="signature-line">{{ localize('department_director_signature', 'ប្រធានមន្ទីរសុខាភិបាលខេត្ត') }}</div>
                            <img src="{{ $signatureUrl }}" alt="{{ localize('department_director_signature', 'ប្រធានមន្ទីរសុខាភិបាលខេត្ត') }}" class="signature-image">
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer">
                {{ localize('address', 'Address') }}: {{ app_setting()->address }}
            </div>
        </div>
    </div>

    <script>
        (function () {
            function triggerPrintWhenReady() {
                if (!{{ !empty($autoPrint) ? 'true' : 'false' }}) {
                    return;
                }

                window.setTimeout(function () {
                    window.print();
                }, 180);
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function () {
                    triggerPrintWhenReady();
                });
            } else {
                triggerPrintWhenReady();
            }
        })();
    </script>
</body>
</html>
