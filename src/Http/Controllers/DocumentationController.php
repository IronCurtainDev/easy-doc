<?php

namespace EasyDoc\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class DocumentationController extends Controller
{
    public function index()
    {
        if (!config('easy-doc.viewer.enabled', false)) {
            abort(404);
        }

        $docsPath = config('easy-doc.output.path', public_path('docs'));
        $files = [];

        if (File::isDirectory($docsPath)) {
            $allFiles = File::files($docsPath);

            foreach ($allFiles as $file) {
                $extension = $file->getExtension();
                $filename = $file->getFilename();

                $type = $this->getFileType($filename);
                $icon = $this->getFileIcon($extension, $type);

                $files[] = [
                    'name' => $filename,
                    'type' => $type,
                    'icon' => $icon,
                    'extension' => $extension,
                    'size' => $this->formatBytes($file->getSize()),
                    'url' => url('docs/' . $filename),
                    'modified' => date('Y-m-d H:i', $file->getMTime()),
                ];
            }

            $apiDocPath = $docsPath . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'index.html';
            if (File::exists($apiDocPath)) {
                $files[] = [
                    'name' => 'API Documentation (HTML)',
                    'type' => 'ApiDoc',
                    'icon' => '[HTML]',
                    'extension' => 'html',
                    'size' => '-',
                    'url' => url('docs/api/index.html'),
                    'modified' => date('Y-m-d H:i', File::lastModified($apiDocPath)),
                ];
            }
        }

        return $this->renderView($files);
    }

    protected function getFileType(string $filename): string
    {
        if ($filename === 'index.html') {
            return 'Swagger UI';
        }
        if (str_contains($filename, 'swagger')) {
            return 'Swagger 2.0';
        }
        if (str_contains($filename, 'openapi')) {
            return 'OpenAPI 3.0';
        }
        if (str_contains($filename, 'postman')) {
            return 'Postman Collection';
        }
        return 'Other';
    }

    protected function getFileIcon(string $extension, string $type): string
    {
        if (str_contains($type, 'Swagger UI')) {
            return '[UI]';
        }
        if (str_contains($type, 'Swagger')) {
            return '[SW]';
        }
        if (str_contains($type, 'OpenAPI')) {
            return '[OA]';
        }
        if (str_contains($type, 'Postman')) {
            return '[PM]';
        }

        return match ($extension) {
            'json' => '[JSON]',
            'yml', 'yaml' => '[YAML]',
            'html' => '[HTML]',
            default => '[FILE]',
        };
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    protected function renderView(array $files): string
    {
        $appName = config('app.name', 'API');
        $authHeaders = config('easy-doc.auth_headers', []);

        $headersList = '';
        foreach ($authHeaders as $header) {
            $required = ($header['required'] ?? true) ? '(required)' : '(optional)';
            $headersList .= '<li><code>' . $header['name'] . '</code> ' . $required . '</li>';
        }

        $fileRows = '';
        foreach ($files as $file) {
            $fileRows .= '<tr>';
            $fileRows .= '<td>' . $file['icon'] . ' ' . $file['name'] . '</td>';
            $fileRows .= '<td><span class="badge">' . $file['type'] . '</span></td>';
            $fileRows .= '<td>' . $file['size'] . '</td>';
            $fileRows .= '<td>' . $file['modified'] . '</td>';
            $fileRows .= '<td>';
            $fileRows .= '<a href="' . $file['url'] . '" target="_blank" class="btn">View</a>';
            $fileRows .= '<a href="' . $file['url'] . '" download class="btn btn-secondary">Download</a>';
            $fileRows .= '</td>';
            $fileRows .= '</tr>';
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $appName . ' - API Documentation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #e4e4e7;
            padding: 2rem;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, #4f46e5, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .subtitle { color: #a1a1aa; margin-bottom: 2rem; }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .card h2 { font-size: 1.25rem; margin-bottom: 1rem; color: #f4f4f5; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        th { color: #a1a1aa; font-weight: 500; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: rgba(79, 70, 229, 0.2);
            color: #818cf8;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); margin-left: 0.5rem; }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.2); box-shadow: none; }
        ul { list-style: none; }
        ul li { padding: 0.5rem 0; color: #d4d4d8; }
        ul li code {
            background: rgba(79, 70, 229, 0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            color: #a5b4fc;
        }
        .footer { text-align: center; margin-top: 2rem; color: #71717a; font-size: 0.875rem; }
        .swagger-btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            background: linear-gradient(135deg, #49cc90, #3db07e);
            color: white;
            margin-bottom: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .swagger-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(73, 204, 144, 0.4); }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . $appName . ' API Documentation</h1>
        <p class="subtitle">Browse and download your API documentation files</p>

        <a href="/docs/index.html" class="swagger-btn" target="_blank">Open Swagger UI</a>
        <a href="/docs/scalar" class="swagger-btn" style="background: linear-gradient(135deg, #2D3748, #1A202C);" target="_blank">Open Scalar UI (Modern)</a>

        <div class="card">
            <h2>Documentation Files</h2>
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $fileRows . '
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Configured Authentication Headers</h2>
            <ul>' . $headersList . '</ul>
        </div>

        <div class="footer">
            Powered by <strong>Easy-Doc</strong> | Generate with <code>php artisan easy-doc:generate</code>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
    public function redoc()
    {
        if (!config('easy-doc.viewer.enabled', false)) {
            abort(404);
        }

        $openApiJsonUrl = asset('docs/openapi.json');
        $appName = config('app.name', 'API');
        $primaryColor = '#4f46e5'; // Indigo-600

        return '<!DOCTYPE html>
<html>
  <head>
    <title>' . $appName . ' - API Documentation</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; font-family: "Inter", sans-serif; background: #f9fafb; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
    </style>
  </head>
  <body>
    <redoc spec-url="' . $openApiJsonUrl . '"
        theme=\'{
            "colors": {
                "primary": {
                    "main": "' . $primaryColor . '"
                },
                "success": {
                    "main": "#10b981"
                },
                "warning": {
                    "main": "#f59e0b"
                },
                "error": {
                    "main": "#ef4444"
                },
                "text": {
                    "primary": "#1e293b",
                    "secondary": "#64748b"
                },
                "http": {
                    "get": "#3b82f6",
                    "post": "#10b981",
                    "put": "#f59e0b",
                    "delete": "#ef4444"
                }
            },
            "typography": {
                "fontFamily": "Inter, sans-serif",
                "fontSize": "14px",
                "lineHeight": "1.5",
                "headings": {
                    "fontFamily": "Inter, sans-serif",
                    "fontWeight": "700",
                    "lineHeight": "1.2"
                },
                "code": {
                    "fontFamily": "Fira Code, monospace",
                    "fontSize": "13px"
                }
            },
            "sidebar": {
                "backgroundColor": "#ffffff",
                "textColor": "#1e293b",
                "width": "300px",
                "groupItems": {
                    "textTransform": "uppercase"
                }
            },
            "rightPanel": {
                "backgroundColor": "#0f172a",
                "width": "40%"
            }
        }\'
        options=\'{
            "hideDownloadButton": true,
            "disableSearch": false,
            "scrollYOffset": 50,
            "expandResponses": "200,201",
            "requiredPropsFirst": true,
            "hideHostname": false,
            "pathInMiddlePanel": true
        }\'
    ></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"> </script>
  </body>
</html>';
    }
    public function scalar()
    {
        if (!config('easy-doc.viewer.enabled', false)) {
            abort(404);
        }

        $openApiJsonUrl = asset('docs/openapi.json');
        $appName = config('app.name', 'API');

        return '<!doctype html>
<html>
  <head>
    <title>' . $appName . ' - API Reference</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body { margin: 0; }
    </style>
  </head>
  <body>
    <script
      id="api-reference"
      data-url="' . $openApiJsonUrl . '"
      data-proxy-url="https://proxy.scalar.com"
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </body>
</html>';
    }
}
