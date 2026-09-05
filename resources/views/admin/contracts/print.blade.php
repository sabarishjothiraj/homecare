<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Contract - {{ $contract->contract_number }}</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #f3f4f6;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #1f2937;
            color: #fff;
        }

        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 4px;
            padding: 8px 12px;
            text-decoration: none;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        .toolbar a.secondary {
            background: #4b5563;
        }

        iframe {
            width: 100%;
            height: calc(100% - 52px);
            border: 0;
            background: #fff;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>Contract {{ $contract->contract_number }}</div>
        <div>
            <a href="{{ route('admin.contracts.download', $contract) }}" class="secondary">Download PDF</a>
            <button type="button" id="print-btn">Print</button>
        </div>
    </div>

    <iframe id="pdf-frame" src="{{ $pdfUrl }}#toolbar=1&navpanes=0"></iframe>

    <script>
        (function () {
            const frame = document.getElementById('pdf-frame');
            const printBtn = document.getElementById('print-btn');

            function printFrame() {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (e) {
                    window.print();
                }
            }

            frame.addEventListener('load', function () {
                setTimeout(printFrame, 700);
            }, { once: true });

            printBtn.addEventListener('click', printFrame);
        })();
    </script>
</body>
</html>
