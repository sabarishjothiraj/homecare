<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Nursing Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            text-align: center;
        }
        h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .success-icon {
            font-size: 80px;
            color: #10b981;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .status {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #d1fae5;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #065f46;
        }
        .status-value {
            color: #10b981;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-secondary {
            background: #10b981;
        }
        .btn-secondary:hover {
            background: #059669;
        }
        .next-steps {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        .next-steps h3 {
            color: #92400e;
            margin-bottom: 15px;
        }
        .next-steps ol {
            color: #78350f;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>🏥 Home Nursing Application</h1>
        <p>Your Laravel application is successfully installed and running!</p>
        
        <div class="status">
            <div class="status-item">
                <span class="status-label">Laravel Version:</span>
                <span class="status-value">{{ app()->version() }}</span>
            </div>
            <div class="status-item">
                <span class="status-label">PHP Version:</span>
                <span class="status-value">{{ PHP_VERSION }}</span>
            </div>
            <div class="status-item">
                <span class="status-label">Environment:</span>
                <span class="status-value">{{ config('app.env') }}</span>
            </div>
            <div class="status-item">
                <span class="status-label">Status:</span>
                <span class="status-value">✓ Ready</span>
            </div>
        </div>

        <div class="next-steps">
            <h3>📋 Next Steps:</h3>
            <ol>
                <li>Configure your database in <code>.env</code> file</li>
                <li>Run <code>php artisan migrate</code> to create database tables</li>
                <li>Run <code>php artisan db:seed</code> to populate default data</li>
                <li>Run <code>php artisan storage:link</code> to create storage symlink</li>
                <li>Visit <code>/admin</code> to access the admin panel</li>
            </ol>
        </div>

        <div>
            <a href="/admin" class="btn">Admin Panel</a>
            <a href="{{ route('home') }}" class="btn btn-secondary">Public Website</a>
        </div>

        <p style="margin-top: 30px; font-size: 0.9em; color: #999;">
            Built with ❤️ using Laravel Framework
        </p>
    </div>
</body>
</html>

